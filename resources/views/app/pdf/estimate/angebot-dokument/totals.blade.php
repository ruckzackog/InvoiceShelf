@php($render = \App\Services\EstimateDocument\DocumentRenderer::class)
<table class="doc-costs">
    <thead>
        <tr>
            <th>@lang('pdf_angebot_position')</th>
            <th class="num">@lang('pdf_angebot_amount')</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($estimate->items as $item)
            <tr>
                <td>{{ $item->name }}</td>
                <td class="num">{!! $render::money($item->total, $currency) !!}</td>
            </tr>
        @endforeach
        @if ($estimate->discount > 0 && $estimate->discount_per_item !== 'YES')
            <tr>
                <td>
                    @lang('pdf_discount_label')
                    @if ($estimate->discount_type === 'percentage') ({{ $estimate->discount }} %) @endif
                </td>
                <td class="num">− {!! $render::money($estimate->discount_val, $currency) !!}</td>
            </tr>
        @endif
        <tr class="doc-subtotal">
            <td>@lang('pdf_angebot_net')</td>
            <td class="num">{!! $render::money($estimate->sub_total - ($estimate->discount_per_item !== 'YES' ? $estimate->discount_val : 0) - ($estimate->tax_included ? $estimate->tax : 0), $currency) !!}</td>
        </tr>
        @foreach ($taxes ?? [] as $tax)
            <tr>
                <td>
                    @if ($estimate->tax_included)
                        @lang('pdf_angebot_tax_included', ['tax' => $tax->name.($tax->calculation_type === 'fixed' ? '' : ' '.(float) $tax->percent.' %')])
                    @else
                        @lang('pdf_angebot_tax_added', ['tax' => $tax->name.($tax->calculation_type === 'fixed' ? '' : ' '.(float) $tax->percent.' %')])
                    @endif
                </td>
                <td class="num">{!! $render::money($tax->amount, $currency) !!}</td>
            </tr>
        @endforeach
        <tr class="doc-total">
            <td>@lang('pdf_angebot_gross')</td>
            <td class="num">{!! $render::money($estimate->total, $currency) !!}</td>
        </tr>
    </tbody>
</table>
