@php
    use App\Models\MoveReport;
    use App\Support\MoveInspection;
    // $pdf = true when dompdf renders this page; the browser view and the PDF share one layout.
    $pdf = $pdf ?? false;
    $saved = (array) $r->rooms;
    // the database returns JSON keys alphabetically — show rooms in walk-through order
    $order = array_values(array_unique(array_merge(array_keys(MoveInspection::rooms()), array_keys($saved))));
    $saved = array_merge(array_flip($order), $saved);
    $saved = array_filter($saved, 'is_array');
    $charged = [];
    $photoCount = 0;
    foreach ($saved as $rid => $room) {
        foreach ((array) ($room['items'] ?? []) as $it) {
            $photoCount += count((array) ($it['photos'] ?? []));
            if (($it['status'] ?? 'ok') !== 'ok' || (float) ($it['price'] ?? 0) > 0) {
                $charged[] = ['area' => $room['name'] ?? ucfirst($rid), 'label' => $it['label'] ?? '', 'status' => $it['status'] ?? 'ok', 'notes' => $it['notes'] ?? '', 'price' => (float) ($it['price'] ?? 0)];
            }
        }
        $photoCount += count((array) ($room['photos'] ?? []));
    }
    $placeholder = '—';
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
@unless($pdf)<meta name="viewport" content="width=device-width,initial-scale=1">@endunless
<title>{{ $r->reference }} — {{ $r->type_label }} Report</title>
<style>
@page{margin:14mm 12mm}
body{font-family:{!! $pdf ? "'DejaVu Sans'" : 'Arial,Helvetica,sans-serif' !!},sans-serif;color:#1a1a1a;margin:0;background:{{ $pdf ? '#fff' : '#eee' }};font-size:{{ $pdf ? '9pt' : '12.5px' }}}
table{width:100%;border-collapse:collapse}
.bar{background:#111;color:#fff;padding:12px 20px}
.bar button{background:#c9a227;border:0;color:#111;font-weight:bold;padding:9px 18px;border-radius:6px;cursor:pointer;float:right;margin-top:-3px}
.page{max-width:840px;margin:20px auto;background:#fff;padding:34px 38px}
.pdf .page{max-width:none;margin:0;padding:0}
.head td{border-bottom:3px solid #c9a227;padding:0 0 10px}
.brand{font-size:17pt;font-weight:bold;letter-spacing:1px}
.brand small{display:block;font-size:8pt;font-weight:normal;color:#777;letter-spacing:1px;margin-top:4px}
.title{text-align:right;font-size:15pt;font-weight:bold}
.ref{font-size:9pt;font-weight:bold;color:#9a6200}
.info{margin:14px 0 4px}
.info td{width:33.33%;padding:5px 8px 5px 0;vertical-align:top}
.info span{display:block;font-size:7pt;text-transform:uppercase;letter-spacing:1px;color:#888;margin-bottom:2px}
.info b{font-size:9.5pt}
h2{font-size:10pt;text-transform:uppercase;letter-spacing:1px;margin:18px 0 8px;border-left:4px solid #c9a227;padding-left:8px}
.room{margin-bottom:12px}
.room th.rn{background:#f4efe3;color:#1a1a1a;text-align:left;font-size:9.5pt;padding:6px 8px;border-left:4px solid #c9a227;text-transform:none;letter-spacing:0}
.room th,.charges th{background:#111;color:#fff;text-align:left;font-size:7.5pt;padding:6px 8px;text-transform:uppercase;letter-spacing:.5px}
.items td,.charges td{padding:5px 8px;border-bottom:1px solid #e5e5e5;vertical-align:top}
.items tr{page-break-inside:avoid}
.r,.room th.r,.charges th.r{text-align:right}
.st{font-weight:bold;font-size:8pt}.ok{color:#14753b}.maintenance{color:#b45309}.damaged{color:#b00020}
.dmg td{background:#fdf1f1}.mnt td{background:#fdf7e8}
.photos{font-size:8pt;color:#555;margin-top:3px}
.photos a{color:#0b5cad;text-decoration:underline}
.sum{width:55%;margin-left:45%;margin-top:6px}
.sum td{font-size:10pt;padding:4px 8px;border-bottom:0}
.sum .total td{font-weight:bold;font-size:12pt;border-top:2px solid #111;color:#b00020}
.sign{margin-top:26px;page-break-inside:avoid}
.sign td{width:46%;vertical-align:bottom;padding:0}
.sign td.gap{width:8%}
.sign .space{height:78px;text-align:left}
.sign .space img{height:64px}
.sign .line{border-top:1px solid #333;padding-top:5px;font-size:8.5pt;color:#444}
.sign .line b{color:#111}
.foot{margin-top:20px;font-size:7.5pt;color:#999;text-align:center}
@media print{body{background:#fff}.bar{display:none}.page{margin:0;max-width:none;padding:0}}
</style>
</head>
<body class="{{ $pdf ? 'pdf' : '' }}">
@unless($pdf)
<div class="bar"><button onclick="window.print()">Print / Save as PDF</button><span>{{ $r->reference }}</span></div>
@endunless
<div class="page">
  <table class="head"><tr>
    <td><div class="brand">UNITED ARAB AGENCIES<small>PROPERTY INSPECTION REPORT</small></div></td>
    <td class="title">{{ $r->type_label }} Report<div class="ref">{{ $r->reference }}</div></td>
  </tr></table>

  <table class="info">
    <tr>
      <td><span>Inspection date</span><b>{{ $r->report_date?->format('d M Y') ?? $placeholder }}</b></td>
      <td><span>Inspector</span><b>{{ $r->inspector ?: $placeholder }}</b></td>
      <td><span>Contract no.</span><b>{{ $r->contract_no ?: $placeholder }}</b></td>
    </tr>
    <tr>
      <td><span>Property / unit</span><b>{{ $r->property_name ?: $r->property?->title ?: $placeholder }}{{ $r->unit_label ? ' — '.$r->unit_label : '' }}</b></td>
      <td><span>Unit type</span><b>{{ $r->unit_type ?: $placeholder }}</b></td>
      <td><span>Bedrooms</span><b>{{ $r->beds ?: $placeholder }}</b></td>
    </tr>
    <tr>
      <td><span>Tenant</span><b>{{ $r->tenant_name }}</b></td>
      <td><span>Phone</span><b>{{ $r->tenant_phone ?: $placeholder }}</b></td>
      <td><span>Email</span><b>{{ $r->tenant_email ?: $placeholder }}</b></td>
    </tr>
    <tr>
      <td><span>Keys</span><b>{{ $r->keys_count ?? $placeholder }}</b></td>
      <td><span>Parking cards</span><b>{{ $r->parking_cards ?? $placeholder }}</b></td>
      <td><span>Photos</span><b>{{ $photoCount ?: 'None' }}</b></td>
    </tr>
  </table>

  <h2>Room inspection</h2>
  @foreach($saved as $rid => $room)
    @php($items = (array) ($room['items'] ?? []))
    @continue(! count($items))
    <table class="room items">
      <thead>
        <tr><th class="rn" colspan="4">{{ $room['name'] ?? ucfirst($rid) }}</th></tr>
        <tr><th style="width:34%">Item</th><th style="width:13%">Condition</th><th>Notes / photos</th><th class="r" style="width:14%">Charge (AED)</th></tr>
      </thead>
      <tbody>
      @foreach($items as $it)
        @php($s = $it['status'] ?? 'ok')
        @php($urls = MoveReport::photoUrls($it['photos'] ?? []))
        <tr class="{{ $s === 'damaged' ? 'dmg' : ($s === 'maintenance' ? 'mnt' : '') }}">
          <td>{{ ! empty($it['group']) ? $it['group'].' — ' : '' }}{{ $it['label'] ?? '' }}</td>
          <td><span class="st {{ $s }}">{{ MoveInspection::STATUSES[$s] ?? $s }}</span></td>
          <td>{{ $it['notes'] ?? '' }}@if(count($urls))<div class="photos">Photos: @foreach($urls as $n => $u)<a href="{{ $u }}">Photo {{ $n + 1 }}</a>{{ $loop->last ? '' : ' · ' }}@endforeach</div>@endif</td>
          <td class="r">{{ ($it['price'] ?? '') !== '' ? number_format((float) $it['price'], 2) : '' }}</td>
        </tr>
      @endforeach
      @php($roomUrls = MoveReport::photoUrls($room['photos'] ?? []))
      @if(count($roomUrls))
        <tr><td colspan="4"><div class="photos">Room photos: @foreach($roomUrls as $n => $u)<a href="{{ $u }}">Photo {{ $n + 1 }}</a>{{ $loop->last ? '' : ' · ' }}@endforeach</div></td></tr>
      @endif
      </tbody>
    </table>
  @endforeach

  <h2>Charges summary</h2>
  <table class="charges">
    <thead><tr><th style="width:20%">Area</th><th>Item</th><th style="width:14%">Condition</th><th class="r" style="width:16%">Amount (AED)</th></tr></thead>
    <tbody>
    @forelse($charged as $c)
      <tr><td>{{ $c['area'] }}</td><td>{{ $c['label'] }}{{ $c['notes'] ? ' — '.$c['notes'] : '' }}</td><td><span class="st {{ $c['status'] }}">{{ MoveInspection::STATUSES[$c['status']] ?? $c['status'] }}</span></td><td class="r">{{ number_format($c['price'], 2) }}</td></tr>
    @empty
      <tr><td colspan="4" style="text-align:center;color:#14753b;padding:12px">No charges — unit in good condition</td></tr>
    @endforelse
    </tbody>
  </table>
  <table class="sum">
    <tr><td>Subtotal</td><td class="r">AED {{ number_format((float) $r->subtotal, 2) }}</td></tr>
    <tr><td>VAT (5%)</td><td class="r">AED {{ number_format((float) $r->vat_amount, 2) }}</td></tr>
    <tr class="total"><td>Total due</td><td class="r">AED {{ number_format((float) $r->total_amount, 2) }}</td></tr>
  </table>

  @if($r->notes)
    <h2>Comments</h2>
    <p style="white-space:pre-line;margin:0">{{ $r->notes }}</p>
  @endif

  <table class="sign">
    <tr>
      <td class="space">@if($r->tenant_signature)<img src="{{ $r->tenant_signature }}" alt="">@endif</td>
      <td class="gap"></td>
      <td class="space">@if($r->inspector_signature)<img src="{{ $r->inspector_signature }}" alt="">@endif</td>
    </tr>
    <tr>
      <td class="line"><b>Tenant</b><br>{{ $r->tenant_name }}<br>Date: {{ $r->report_date?->format('d M Y') }}</td>
      <td class="gap"></td>
      <td class="line"><b>Inspector</b><br>{{ $r->inspector }}<br>Date: {{ $r->report_date?->format('d M Y') }}</td>
    </tr>
  </table>
  <div class="foot">Generated {{ now()->format('d M Y H:i') }} · United Arab Agencies · {{ $r->reference }}</div>
</div>
@if(! $pdf && request()->boolean('print'))<script>window.addEventListener('load',function(){setTimeout(function(){window.print()},400)})</script>@endif
</body>
</html>
