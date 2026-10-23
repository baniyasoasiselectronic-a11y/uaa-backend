<x-filament-widgets::widget>
    <div class="uaa-hero">
        <div class="date">{{ $weekday }}<b>{{ $day }}</b>{{ $month }}</div>
        <div class="eyebrow">United Arab Agencies · Control room</div>
        <h2>{{ $greeting }}, <em>{{ $name }}</em></h2>
        <p>{{ $onlyEnquiries ? 'Here are the enquiries that need a reply today.' : 'Here is what is happening across your buildings, tenants and enquiries today.' }}</p>
        <div class="uaa-quick">
            @foreach ($links as $l)
                <a href="{{ $l['url'] }}" class="{{ $l['hot'] && $l['count'] ? 'hot' : '' }}">
                    {{ $l['label'] }} <b>{{ $l['count'] }}</b>
                </a>
            @endforeach
        </div>
    </div>
</x-filament-widgets::widget>
