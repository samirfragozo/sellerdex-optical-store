<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <title>@yield('title', __('app.documents.default_title'))</title>
    <style>
        @page { size: A5; margin: 12mm; }
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #111; margin: 0; }
        .doc { width: 100%; }
        .header { display: flex; align-items: center; border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 8px; }
        .header img { max-height: 56px; margin-right: 10px; }
        .biz-name { font-size: 15px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 3px 4px; }
        .items th, .items td { border-bottom: 1px solid #ccc; }
        .right { text-align: right; }
        .totals td { padding: 2px 4px; }
        .muted { color: #555; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
<div class="doc">
    <div class="header">
        @if ($logo)
            <img src="{{ $logo }}" alt="logo">
        @endif
        <div>
            <div class="biz-name">{{ $settings->name }}</div>
            <div class="muted">
                @if ($settings->tax_id) {{ __('app.documents.tax_id_label') }}: {{ $settings->tax_id }} @endif
                @if ($settings->address) · {{ $settings->address }} @endif
                @if ($settings->phones) · {{ __('app.documents.phone_label') }}: {{ $settings->phones }} @endif
            </div>
        </div>
    </div>
    @yield('content')
</div>
</body>
</html>
