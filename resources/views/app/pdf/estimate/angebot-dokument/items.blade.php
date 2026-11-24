@php($render = \App\Services\EstimateDocument\DocumentRenderer::class)
<div class="doc-packages">
    @foreach ($estimate->items as $item)
        @php($package = $render::workPackage($item->description))
        <div class="doc-package">
            <h3>{{ $item->name }}</h3>
            @foreach ($package['intro'] as $line)
                <p>{{ $line }}</p>
            @endforeach
            <table>
                @if ($package['rows'])
                    <thead>
                        <tr>
                            <th style="width: 38%">@lang('pdf_angebot_activity')</th>
                            <th>@lang('pdf_angebot_description')</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($package['rows'] as $row)
                            <tr>
                                <td>{{ $row['title'] }}</td>
                                <td>{{ $row['text'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                @endif
                <tbody>
                    <tr class="doc-sum">
                        <td>
                            @lang('pdf_angebot_sum')
                            @if ((float) $item->quantity != 1.0)
                                <span class="doc-quantity">({{ rtrim(rtrim(number_format((float) $item->quantity, 2, ',', '.'), '0'), ',') }}@if ($item->unit_name) {{ $item->unit_name }}@endif × {!! $render::money($item->price, $currency) !!})</span>
                            @endif
                        </td>
                        <td class="num">{!! $render::money($item->total, $currency) !!}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @endforeach
</div>
