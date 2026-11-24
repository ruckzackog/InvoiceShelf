<div class="doc-meta">
    <p class="doc-meta-line">
        @lang('pdf_estimate_number') {{ $estimate->estimate_number }}
        &nbsp;·&nbsp; @lang('pdf_estimate_date') {{ $estimate->formattedEstimateDate }}
        @if ($estimate->expiry_date)
            &nbsp;·&nbsp; @lang('pdf_estimate_expire_date') {{ $estimate->formattedExpiryDate }}
        @endif
    </p>
    <div class="doc-meta-columns">
        <div class="doc-address">
            <p class="doc-label">@lang('pdf_angebot_for')</p>
            {!! $billingAddress !!}
        </div>
        @if (array_filter($contact))
            <div class="doc-address">
                <p class="doc-label">@lang('pdf_angebot_contact')</p>
                @if ($contact['contactName'])<p>{{ $contact['contactName'] }}</p>@endif
                <p>{{ $estimate->company?->name }}</p>
                @if ($contact['contactEmail'])<p>{{ $contact['contactEmail'] }}</p>@endif
                @if ($contact['contactPhone'])<p>{{ $contact['contactPhone'] }}</p>@endif
            </div>
        @endif
    </div>
</div>
