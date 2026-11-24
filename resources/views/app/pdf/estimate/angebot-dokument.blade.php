{{--
    Offer document ("Angebot" with the visual editor).

    Rendered by Gotenberg (Chromium) when the template is listed in
    PDF_GOTENBERG_TEMPLATES, so it may use modern CSS. Styles come from
    resources/css/angebot-document.css, the same file the editor uses.
    The page footer is angebot-dokument/footer.blade.php.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>@lang('pdf_estimate_label') {{ $estimate->estimate_number }}</title>
    <style>
        html,
        body {
            margin: 0;
            padding: 0;
            background: #fff;
        }

        {!! \App\Services\EstimateDocument\DocumentStyles::css() !!}
    </style>
</head>
<body>
<div class="angebot-doc">
    @include('app.pdf.estimate.angebot-dokument.letterhead')

    @if ($estimate->document)
        {!! $estimate->getDocumentHtml() !!}
    @else
        @include('app.pdf.estimate.angebot-dokument.meta', [
            'billingAddress' => $billing_address ?: '',
            'contact' => ['contactName' => '', 'contactEmail' => '', 'contactPhone' => ''],
            'currency' => $estimate->customer?->currency,
        ])
        @include('app.pdf.estimate.angebot-dokument.items', ['currency' => $estimate->customer?->currency])
        @include('app.pdf.estimate.angebot-dokument.totals', ['currency' => $estimate->customer?->currency])
        @if ($notes)
            <div class="doc-notes">{!! $notes !!}</div>
        @endif
    @endif
</div>
</body>
</html>
