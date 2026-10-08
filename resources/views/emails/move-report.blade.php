<!DOCTYPE html>
<html>
<head><meta charset="utf-8"></head>
<body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,Helvetica,sans-serif;color:#222">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:24px 0">
    <tr><td align="center">
      <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden">
        <tr><td style="background:#0B0B0B;padding:22px 28px">
          <span style="color:#C9A227;font-size:11px;letter-spacing:.14em;text-transform:uppercase">United Arab Agencies</span>
          <h1 style="color:#fff;font-size:20px;margin:6px 0 0">{{ $report->type_label }} inspection report</h1>
        </td></tr>
        <tr><td style="padding:24px 28px">
          <p style="margin:0 0 16px;font-size:13px;color:#666">Reference <strong style="color:#111">{{ $report->reference }}</strong> · {{ $report->report_date?->format('d M Y') }}</p>
          <table role="presentation" width="100%" cellpadding="6" cellspacing="0" style="font-size:14px;border-collapse:collapse">
            <tr><td style="width:140px;color:#888">Tenant</td><td><strong>{{ $report->tenant_name }}</strong></td></tr>
            <tr><td style="color:#888">Property</td><td>{{ $report->property_name ?: '—' }}</td></tr>
            <tr><td style="color:#888">Unit</td><td>{{ $report->unit_label ?: '—' }}{{ $report->unit_type ? ' · '.$report->unit_type : '' }}</td></tr>
            <tr><td style="color:#888">Inspected by</td><td>{{ $report->inspector }}</td></tr>
            <tr><td style="color:#888">Charges to tenant</td><td><strong>AED {{ number_format((float) $report->total_amount, 2) }}</strong> <span style="color:#888">(incl. 5% VAT)</span></td></tr>
          </table>
          <p style="margin:24px 0 0"><a href="{{ $link }}" style="display:inline-block;background:#C9A227;color:#17120a;text-decoration:none;font-weight:bold;padding:12px 22px;border-radius:6px">Open the full report</a></p>
          <p style="margin:14px 0 0;font-size:12px;color:#888">The report opens in your browser. To keep a PDF copy choose Print → Save as PDF. The link stays valid for 60 days.</p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
