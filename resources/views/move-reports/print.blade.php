<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $r->reference }} — {{ $r->type_label }} Report</title>
<style>
*{box-sizing:border-box}
body{font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;margin:0;background:#eee;font-size:13px}
.bar{position:sticky;top:0;background:#111;color:#fff;padding:12px 20px;display:flex;justify-content:space-between;align-items:center;gap:12px}
.bar button{background:#c9a227;border:0;color:#111;font-weight:700;padding:9px 18px;border-radius:6px;cursor:pointer}
.page{max-width:820px;margin:20px auto;background:#fff;padding:36px 40px;box-shadow:0 4px 24px rgba(0,0,0,.15)}
.head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #c9a227;padding-bottom:14px;margin-bottom:18px}
.brand{font-size:20px;font-weight:700;letter-spacing:.04em}
.brand small{display:block;font-size:11px;font-weight:400;color:#777;letter-spacing:.1em;margin-top:4px}
.badge{display:inline-block;padding:4px 12px;border-radius:99px;font-weight:700;font-size:12px;background:#e3f6e8;color:#14753b}
.badge.out{background:#fbf0dc;color:#9a6200}
h1{margin:0 0 6px;font-size:20px;text-align:right}
.grid{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px 20px;margin-bottom:18px}
.grid div span{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:#888;margin-bottom:2px}
.grid div b{font-size:13px}
h2{font-size:13px;text-transform:uppercase;letter-spacing:.08em;margin:22px 0 8px;border-left:4px solid #c9a227;padding-left:8px}
table{width:100%;border-collapse:collapse}
th{background:#111;color:#fff;text-align:left;font-size:11px;padding:8px;text-transform:uppercase;letter-spacing:.05em}
td{padding:8px;border-bottom:1px solid #e5e5e5;vertical-align:top}
td.r,th.r{text-align:right}
.total td{font-weight:700;font-size:15px;border-top:2px solid #111;color:#b00020}
.photos{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.photos img{width:100%;height:150px;object-fit:cover;border:1px solid #ddd;border-radius:4px}
.sign{display:flex;gap:40px;margin-top:46px}
.sign div{flex:1;border-top:1px solid #333;padding-top:6px;font-size:11px;color:#555}
.foot{margin-top:28px;font-size:10px;color:#999;text-align:center}
@media(max-width:640px){.page{padding:20px 16px}.grid{grid-template-columns:1fr 1fr}.photos{grid-template-columns:1fr 1fr}}
@media print{body{background:#fff}.bar{display:none}.page{box-shadow:none;margin:0;max-width:none;padding:0}@page{margin:14mm}}
</style>
</head>
<body>
<div class="bar"><span>{{ $r->reference }}</span><button onclick="window.print()">Print / Save as PDF</button></div>
<div class="page">
  <div class="head">
    <div class="brand">UNITED ARAB AGENCIES<small>PROPERTY INSPECTION REPORT</small></div>
    <div><h1>{{ $r->type_label }} Report</h1><div style="text-align:right"><span class="badge {{ $r->type === 'move_out' ? 'out' : '' }}">{{ $r->reference }}</span></div></div>
  </div>
  <div class="grid">
    <div><span>Date</span><b>{{ $r->report_date->format('d M Y') }}</b></div>
    <div><span>Inspector</span><b>{{ $r->inspector ?: '—' }}</b></div>
    <div><span>Unit type</span><b>{{ $r->unit_type ?: '—' }}</b></div>
    <div><span>Tenant</span><b>{{ $r->tenant_name }}</b></div>
    <div><span>Phone</span><b>{{ $r->tenant_phone ?: '—' }}</b></div>
    <div><span>Email</span><b>{{ $r->tenant_email ?: '—' }}</b></div>
    <div><span>Property / unit</span><b>{{ $r->property?->title ?: '—' }}{{ $r->unit_label ? ' — '.$r->unit_label : '' }}</b></div>
    <div><span>Electricity / water</span><b>{{ $r->electricity_reading ?: '—' }} / {{ $r->water_reading ?: '—' }}</b></div>
    <div><span>Keys handed</span><b>{{ $r->keys_count ?? '—' }}</b></div>
  </div>

  <h2>Inspection items &amp; charges</h2>
  <table>
    <thead><tr><th>Area</th><th>Item</th><th>Condition</th><th>Remarks</th><th class="r">Charge (AED)</th></tr></thead>
    <tbody>
    @forelse($r->items as $i)
      <tr><td>{{ $i->area ?: '—' }}</td><td>{{ $i->description }}</td><td>{{ \App\Models\MoveReport::CONDITIONS[$i->condition] ?? '—' }}</td><td>{{ $i->remarks }}</td><td class="r">{{ number_format($i->charge, 2) }}</td></tr>
    @empty
      <tr><td colspan="5" style="text-align:center;color:#888">No items recorded.</td></tr>
    @endforelse
      <tr class="total"><td colspan="4" class="r">TOTAL</td><td class="r">AED {{ number_format($r->total, 2) }}</td></tr>
    </tbody>
  </table>

  @if($r->notes)
    <h2>Notes</h2>
    <p style="white-space:pre-line;margin:0">{{ $r->notes }}</p>
  @endif

  @if(count($r->photoUrls()))
    <h2>Photos</h2>
    <div class="photos">@foreach($r->photoUrls() as $u)<img src="{{ $u }}" alt="">@endforeach</div>
  @endif

  <div class="sign"><div>Tenant signature</div><div>Inspector signature</div></div>
  <div class="foot">Generated {{ now()->format('d M Y H:i') }} · United Arab Agencies</div>
</div>
</body>
</html>
