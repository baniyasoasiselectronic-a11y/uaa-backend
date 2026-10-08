@php
    use App\Models\MoveReport;
    use App\Support\MoveInspection;
    $saved = (array) $r->rooms;
    // the database returns JSON keys alphabetically — show rooms in walk-through order
    $order = array_values(array_unique(array_merge(array_keys(MoveInspection::rooms()), array_keys($saved))));
    $saved = array_merge(array_flip($order), $saved);
    $saved = array_filter($saved, 'is_array');
    $charged = [];
    foreach ($saved as $rid => $room) {
        foreach ((array) ($room['items'] ?? []) as $it) {
            if (($it['status'] ?? 'ok') !== 'ok') {
                $charged[] = ['area' => $room['name'] ?? ucfirst($rid), 'label' => $it['label'] ?? '', 'status' => $it['status'], 'notes' => $it['notes'] ?? '', 'price' => (float) ($it['price'] ?? 0)];
            }
        }
    }
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{{ $r->reference }} — {{ $r->type_label }} Report</title>
<style>
*{box-sizing:border-box}
body{font-family:Arial,Helvetica,sans-serif;color:#1a1a1a;margin:0;background:#eee;font-size:12.5px}
.bar{position:sticky;top:0;background:#111;color:#fff;padding:12px 20px;display:flex;justify-content:space-between;align-items:center;gap:12px;z-index:5}
.bar button{background:#c9a227;border:0;color:#111;font-weight:700;padding:9px 18px;border-radius:6px;cursor:pointer}
.page{max-width:840px;margin:20px auto;background:#fff;padding:34px 38px;box-shadow:0 4px 24px rgba(0,0,0,.15)}
.head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:3px solid #c9a227;padding-bottom:14px;margin-bottom:16px}
.brand{font-size:20px;font-weight:700;letter-spacing:.04em}
.brand small{display:block;font-size:11px;font-weight:400;color:#777;letter-spacing:.1em;margin-top:4px}
.badge{display:inline-block;padding:4px 12px;border-radius:99px;font-weight:700;font-size:12px;background:#e3f6e8;color:#14753b}
.badge.out{background:#fbf0dc;color:#9a6200}
h1{margin:0 0 6px;font-size:20px;text-align:right}
.grid{display:grid;grid-template-columns:repeat(3,1fr);gap:11px 18px;margin-bottom:10px}
.grid div span{display:block;font-size:10px;text-transform:uppercase;letter-spacing:.08em;color:#888;margin-bottom:2px}
.grid div b{font-size:13px}
h2{font-size:13px;text-transform:uppercase;letter-spacing:.08em;margin:20px 0 8px;border-left:4px solid #c9a227;padding-left:8px}
table{width:100%;border-collapse:collapse;margin-bottom:6px}
th{background:#111;color:#fff;text-align:left;font-size:10.5px;padding:7px 8px;text-transform:uppercase;letter-spacing:.05em}
td{padding:6px 8px;border-bottom:1px solid #e5e5e5;vertical-align:top}
td.r,th.r{text-align:right}
.st{font-weight:700;font-size:11px}.st.ok{color:#14753b}.st.maintenance{color:#b45309}.st.damaged{color:#b00020}
tr.dmg td{background:#fdf1f1}tr.mnt td{background:#fdf7e8}
.room{break-inside:avoid;margin-bottom:10px}
.room h3{margin:14px 0 5px;font-size:12.5px;background:#f4efe3;padding:6px 8px;border-left:4px solid #c9a227}
.sum td{font-size:13px;border-bottom:0;padding:4px 8px}
.sum tr.total td{font-weight:700;font-size:15px;border-top:2px solid #111;color:#b00020}
.photos{display:grid;grid-template-columns:repeat(4,1fr);gap:6px;margin-top:6px}
.photos img{width:100%;height:105px;object-fit:cover;border:1px solid #ddd;border-radius:4px}
.sign{display:flex;gap:40px;margin-top:34px;break-inside:avoid}
.sign div{flex:1;border-top:1px solid #333;padding-top:6px;font-size:11px;color:#555;min-height:90px}
.sign img{display:block;max-height:70px;margin:-84px 0 14px;position:relative}
.foot{margin-top:24px;font-size:10px;color:#999;text-align:center}
@media(max-width:640px){.page{padding:18px 14px}.grid{grid-template-columns:1fr 1fr}.photos{grid-template-columns:1fr 1fr}}
@media print{body{background:#fff}.bar{display:none}.page{box-shadow:none;margin:0;max-width:none;padding:0}@page{margin:12mm}}
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
    <div><span>Inspection date</span><b>{{ $r->report_date->format('d M Y') }}</b></div>
    <div><span>Inspector</span><b>{{ $r->inspector ?: '—' }}</b></div>
    <div><span>Contract no.</span><b>{{ $r->contract_no ?: '—' }}</b></div>
    <div><span>Property / unit</span><b>{{ $r->property_name ?: $r->property?->title ?: '—' }}{{ $r->unit_label ? ' — '.$r->unit_label : '' }}</b></div>
    <div><span>Unit type</span><b>{{ $r->unit_type ?: '—' }}</b></div>
    <div><span>Bedrooms</span><b>{{ $r->beds ?: '—' }}</b></div>
    <div><span>Tenant</span><b>{{ $r->tenant_name }}</b></div>
    <div><span>Phone</span><b>{{ $r->tenant_phone ?: '—' }}</b></div>
    <div><span>Email</span><b>{{ $r->tenant_email ?: '—' }}</b></div>
    <div><span>Keys</span><b>{{ $r->keys_count ?? '—' }}</b></div>
    <div><span>Parking cards</span><b>{{ $r->parking_cards ?? '—' }}</b></div>
  </div>

  <h2>Room inspection</h2>
  @foreach($saved as $rid => $room)
    @php($items = (array) ($room['items'] ?? []))
    @continue(! count($items))
    <div class="room">
      <h3>{{ $room['name'] ?? ucfirst($rid) }}</h3>
      <table>
        <thead><tr><th style="width:34%">Item</th><th style="width:14%">Condition</th><th>Notes</th><th class="r" style="width:15%">Charge (AED)</th></tr></thead>
        <tbody>
        @foreach($items as $it)
          @php($s = $it['status'] ?? 'ok')
          <tr class="{{ $s === 'damaged' ? 'dmg' : ($s === 'maintenance' ? 'mnt' : '') }}">
            <td>{{ ! empty($it['group']) ? $it['group'].' — ' : '' }}{{ $it['label'] ?? '' }}</td>
            <td><span class="st {{ $s }}">{{ MoveInspection::STATUSES[$s] ?? $s }}</span></td>
            <td>{{ $it['notes'] ?? '' }}</td>
            <td class="r">{{ $s !== 'ok' && ($it['price'] ?? '') !== '' ? number_format((float) $it['price'], 2) : '' }}</td>
          </tr>
          @php($urls = MoveReport::photoUrls($it['photos'] ?? []))
          @if(count($urls))
            <tr><td colspan="4"><div class="photos">@foreach($urls as $u)<img src="{{ $u }}" alt="">@endforeach</div></td></tr>
          @endif
        @endforeach
        </tbody>
      </table>
      @php($roomUrls = MoveReport::photoUrls($room['photos'] ?? []))
      @if(count($roomUrls))
        <div class="photos">@foreach($roomUrls as $u)<img src="{{ $u }}" alt="">@endforeach</div>
      @endif
    </div>
  @endforeach

  <h2>Charges summary</h2>
  <table>
    <thead><tr><th>Area</th><th>Item</th><th>Condition</th><th class="r">Amount (AED)</th></tr></thead>
    <tbody>
    @forelse($charged as $c)
      <tr><td>{{ $c['area'] }}</td><td>{{ $c['label'] }}{{ $c['notes'] ? ' — '.$c['notes'] : '' }}</td><td><span class="st {{ $c['status'] }}">{{ MoveInspection::STATUSES[$c['status']] }}</span></td><td class="r">{{ number_format($c['price'], 2) }}</td></tr>
    @empty
      <tr><td colspan="4" style="text-align:center;color:#888;padding:14px">✓ No charges — unit in good condition</td></tr>
    @endforelse
    </tbody>
  </table>
  <table class="sum" style="width:55%;margin-left:auto">
    <tr><td>Subtotal</td><td class="r">AED {{ number_format((float) $r->subtotal, 2) }}</td></tr>
    <tr><td>VAT (5%)</td><td class="r">AED {{ number_format((float) $r->vat_amount, 2) }}</td></tr>
    <tr class="total"><td>Total due</td><td class="r">AED {{ number_format((float) $r->total_amount, 2) }}</td></tr>
  </table>

  @if($r->notes)
    <h2>Comments</h2>
    <p style="white-space:pre-line;margin:0">{{ $r->notes }}</p>
  @endif

  <div class="sign">
    <div>@if($r->tenant_signature)<img src="{{ $r->tenant_signature }}" alt="">@endif Tenant signature — {{ $r->tenant_name }}</div>
    <div>@if($r->inspector_signature)<img src="{{ $r->inspector_signature }}" alt="">@endif Inspector signature — {{ $r->inspector }}</div>
  </div>
  <div class="foot">Generated {{ now()->format('d M Y H:i') }} · United Arab Agencies</div>
</div>
@if(request()->boolean('print'))<script>window.addEventListener('load',function(){setTimeout(function(){window.print()},400)})</script>@endif
</body>
</html>
