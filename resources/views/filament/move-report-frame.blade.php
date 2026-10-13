@php
    $r = $getRecord();
    $url = \App\Filament\Resources\MoveReportResource::printUrl($r);
@endphp
<iframe src="{{ $url }}" title="Report {{ $r->reference }}" loading="lazy"
        style="width:100%;height:1250px;border:1px solid rgba(128,128,128,.35);border-radius:10px;background:#fff"></iframe>
