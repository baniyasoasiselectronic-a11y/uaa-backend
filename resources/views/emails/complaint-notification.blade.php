<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;color:#222">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 0">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden">
          <tr>
            <td style="background:#0B0B0B;padding:22px 28px">
              <span style="color:#C9A227;font-size:11px;letter-spacing:.14em;text-transform:uppercase">United Arab Agencies</span>
              <h1 style="color:#fff;font-size:20px;margin:6px 0 0">New Complaint Submitted</h1>
            </td>
          </tr>
          <tr>
            <td style="padding:24px 28px">
              <p style="margin:0 0 16px;font-size:13px;color:#666">
                Ticket <strong style="color:#111">{{ $complaint->ticket_number }}</strong> ·
                Submitted {{ $complaint->created_at?->format('d M Y, H:i') }}
              </p>

              <table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="font-size:14px;border-collapse:collapse">
                <tr><td style="width:140px;color:#888">Name</td><td><strong>{{ $complaint->name }}</strong></td></tr>
                <tr><td style="color:#888">Email</td><td><a href="mailto:{{ $complaint->email }}">{{ $complaint->email }}</a></td></tr>
                <tr><td style="color:#888">Phone</td><td>{{ $complaint->phone ?: '—' }}</td></tr>
                <tr><td style="color:#888">Property</td><td>{{ $complaint->property?->title ?? '—' }}</td></tr>
                <tr><td style="color:#888">Unit</td><td>{{ $complaint->unit?->unit_number ?? '—' }}</td></tr>
                <tr><td style="color:#888">Category</td><td>{{ $complaint->category ?: '—' }}</td></tr>
              </table>

              <p style="margin:20px 0 6px;color:#888;font-size:13px;text-transform:uppercase;letter-spacing:.08em">Description</p>
              <p style="margin:0;white-space:pre-line;font-size:14px;line-height:1.6;background:#f9f7f2;border:1px solid #eee;border-radius:6px;padding:14px">{{ $complaint->description }}</p>

              @if($complaint->attachments->count())
                <p style="margin:20px 0 6px;color:#888;font-size:13px;text-transform:uppercase;letter-spacing:.08em">Attachments</p>
                <ul style="margin:0;padding-left:18px;font-size:13px">
                  @foreach($complaint->attachments as $a)
                    <li><a href="{{ asset('storage/'.$a->path) }}">{{ $a->original_name }}</a></li>
                  @endforeach
                </ul>
              @endif

              <p style="margin:26px 0 0;font-size:12px;color:#999">
                Manage this ticket in the admin panel under Complaints.
              </p>
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
