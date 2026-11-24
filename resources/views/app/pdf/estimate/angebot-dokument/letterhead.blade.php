<div class="doc-letterhead">
    <div>
        @if ($logo ?? null)
            <img class="doc-logo" src="{{ \App\Space\ImageUtils::toBase64Src($logo) }}" alt="{{ $estimate->company?->name }}">
        @else
            <h3>{{ $estimate->company?->name }}</h3>
        @endif
    </div>
    <div class="doc-sender">
        {!! $company_address ?? '' !!}
    </div>
</div>
