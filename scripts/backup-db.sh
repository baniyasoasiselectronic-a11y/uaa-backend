#!/bin/bash
# Daily backup of the UAA admin-panel database (MySQL).
#
# - Reads the database settings from the Laravel .env on this server. No password is stored in this file.
# - Writes a compressed dump to ~/backups/uaa-db/ (outside public_html, so it cannot be downloaded from the web).
# - Checks that the dump is complete, keeps the last 30 days, writes a one-line log entry per run.
# - Exit code 0 = good backup made; anything else = failed (cron mails the output if MAILTO is set).
#
# Install (cPanel -> Cron Jobs, once per day):
#   /bin/bash $HOME/uaa-backend/scripts/backup-db.sh >> $HOME/backups/uaa-db/cron.log 2>&1
# Restore:   gunzip -c ~/backups/uaa-db/uaa-db_YYYY-MM-DD_HHMM.sql.gz | mysql -u USER -p DATABASE
#
# Optional settings (environment variables): APP_DIR (default ~/uaa-backend), BACKUP_DIR (default ~/backups/uaa-db),
# KEEP_DAYS (default 30).

set -uo pipefail

APP_DIR="${APP_DIR:-$HOME/uaa-backend}"
BACKUP_DIR="${BACKUP_DIR:-$HOME/backups/uaa-db}"
KEEP_DAYS="${KEEP_DAYS:-30}"
ENV_FILE="$APP_DIR/.env"
LOG="$BACKUP_DIR/backup.log"

log() { echo "$(date '+%Y-%m-%d %H:%M:%S') $*" | tee -a "$LOG"; }

mkdir -p "$BACKUP_DIR" || { echo "cannot create $BACKUP_DIR"; exit 1; }
chmod 700 "$BACKUP_DIR" 2>/dev/null || true

[ -r "$ENV_FILE" ] || { log "FAILED: cannot read $ENV_FILE"; exit 1; }

# value of KEY from .env, without surrounding quotes and without a trailing carriage return
envval() {
  grep -E "^$1=" "$ENV_FILE" | head -n1 | cut -d= -f2- | tr -d '\r' | sed -e 's/^"\(.*\)"$/\1/' -e "s/^'\(.*\)'\$/\1/"
}

DB_CONNECTION="$(envval DB_CONNECTION)"
DB_HOST="$(envval DB_HOST)"; DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="$(envval DB_PORT)"; DB_PORT="${DB_PORT:-3306}"
DB_DATABASE="$(envval DB_DATABASE)"
DB_USERNAME="$(envval DB_USERNAME)"
DB_PASSWORD="$(envval DB_PASSWORD)"

if [ -n "$DB_CONNECTION" ] && [ "$DB_CONNECTION" != "mysql" ] && [ "$DB_CONNECTION" != "mariadb" ]; then
  log "FAILED: DB_CONNECTION is '$DB_CONNECTION', this script only backs up MySQL/MariaDB"; exit 1
fi
[ -n "$DB_DATABASE" ] && [ -n "$DB_USERNAME" ] || { log "FAILED: DB_DATABASE / DB_USERNAME missing in $ENV_FILE"; exit 1; }
command -v mysqldump >/dev/null 2>&1 || { log "FAILED: mysqldump not found on this server"; exit 1; }

STAMP="$(date '+%Y-%m-%d_%H%M')"
OUT="$BACKUP_DIR/uaa-db_${STAMP}.sql.gz"
TMP="$OUT.partial"

# the password goes through the environment, not the command line, so it does not show in the process list
if ! MYSQL_PWD="$DB_PASSWORD" mysqldump --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" \
      --single-transaction --quick --routines --triggers --no-tablespaces --default-character-set=utf8mb4 \
      "$DB_DATABASE" 2>"$BACKUP_DIR/last-error.txt" | gzip -9 > "$TMP"; then
  log "FAILED: mysqldump error: $(head -c 300 "$BACKUP_DIR/last-error.txt")"
  rm -f "$TMP"; exit 1
fi
if [ -s "$BACKUP_DIR/last-error.txt" ] && grep -qiE 'error|denied|unknown' "$BACKUP_DIR/last-error.txt"; then
  log "FAILED: mysqldump reported: $(head -c 300 "$BACKUP_DIR/last-error.txt")"
  rm -f "$TMP"; exit 1
fi

# the dump must be a valid gzip file and end with the line mysqldump writes when it finished
if ! gzip -t "$TMP" 2>/dev/null || ! gunzip -c "$TMP" | tail -n 5 | grep -q 'Dump completed'; then
  log "FAILED: the dump is incomplete or damaged (kept as $TMP for inspection)"; exit 1
fi

mv "$TMP" "$OUT" && chmod 600 "$OUT"
SIZE="$(du -h "$OUT" | cut -f1)"
find "$BACKUP_DIR" -maxdepth 1 -name 'uaa-db_*.sql.gz' -mtime +"$KEEP_DAYS" -delete
COUNT="$(find "$BACKUP_DIR" -maxdepth 1 -name 'uaa-db_*.sql.gz' | wc -l | tr -d ' ')"
log "OK: $OUT ($SIZE); $COUNT backup(s) kept (last $KEEP_DAYS days)"
exit 0
