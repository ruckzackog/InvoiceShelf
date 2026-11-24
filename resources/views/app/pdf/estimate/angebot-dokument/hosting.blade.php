@php($render = \App\Services\EstimateDocument\DocumentRenderer::class)
<table class="doc-hosting">
    <thead>
        <tr>
            <th style="width: 30%">@lang('pdf_angebot_package')</th>
            <th>@lang('pdf_angebot_content')</th>
            <th class="num">@lang('pdf_angebot_per_month')</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($packages as $package)
            <tr @class(['doc-recommended' => $package['recommended']])>
                <td>
                    <strong>{{ $package['name'] }}</strong>
                    @if ($package['recommended'])<span class="doc-badge">@lang('pdf_angebot_recommended')</span>@endif
                </td>
                <td>{{ implode(', ', $package['lines']) }}</td>
                <td class="num">{!! $render::money($package['price'], $currency) !!}</td>
            </tr>
        @endforeach
    </tbody>
</table>
<p class="doc-note">@lang('pdf_angebot_hosting_net')</p>
