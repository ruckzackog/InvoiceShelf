{{-- Gotenberg page footer. It is its own HTML document and gets no styles from the page. --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        {!! \App\Services\EstimateDocument\DocumentStyles::css() !!}
        html, body { margin: 0; padding: 0; }
        body { -webkit-print-color-adjust: exact; }
    </style>
</head>
<body>
<div class="angebot-footer">
    <div>
        {{ $company?->name }}@if ($companyLine) · {{ $companyLine }}@endif
        @if ($company?->vat_id)<br>@lang('pdf_tax_id'): {{ $company->vat_id }}@endif
        @if ($footerLine) · {{ $footerLine }}@endif
    </div>
    <div class="angebot-footer-page">
        @lang('pdf_estimate_number') {{ $estimate->estimate_number }}<br>
        @lang('pdf_angebot_page') <span class="pageNumber"></span> / <span class="totalPages"></span>
    </div>
</div>
</body>
</html>
