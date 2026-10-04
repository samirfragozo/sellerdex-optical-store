@extends('documents.layout')
@section('title', __('app.documents.invoice_title').' '.$sale->number)
@section('content')
    @php($fmt = fn ($v) => '$'.number_format((int) $v, 0, ',', '.'))
    <table>
        <tr>
            <td><strong>{{ __('app.documents.number_label') }}</strong> {{ $sale->number }}</td>
            <td class="right">{{ $sale->sold_at?->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td>{{ $sale->document_type->label() }}</td>
            <td class="right">{{ __('app.fields.seller') }}: {{ $sale->seller?->name }}</td>
        </tr>
    </table>
    <p class="muted">{{ $sale->document_type->legend() }}</p>
    @if ($sale->document_type === \App\Enums\SaleDocumentType::Quote && $sale->quote_valid_until)
        <p class="muted"><strong>{{ __('app.documents.quote_valid_until', ['date' => $sale->quote_valid_until->format('d/m/Y')]) }}</strong></p>
    @endif
    <p class="muted"><strong>{{ __('app.documents.not_an_invoice') }}</strong></p>
    @if ($sale->customer)
        <p>
            <strong>{{ __('app.fields.customer') }}:</strong> {{ $sale->customer->full_name }}<br>
            @if ($sale->customer->id_number) {{ $sale->customer->document_type?->label() }}: {{ $sale->customer->id_number }}<br> @endif
            @if ($sale->customer->phone) {{ __('app.documents.phone_label') }}: {{ $sale->customer->phone }} @endif
        </p>
    @endif
    <table class="items">
        <thead>
            <tr><th>{{ __('app.pos.quantity_short') }}</th><th>{{ __('app.fields.description') }}</th><th class="right">{{ __('app.documents.unit_value') }}</th><th class="right">{{ __('app.documents.total_value') }}</th></tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td>{{ $item->quantity }}</td>
                    <td>
                        {{ $item->description }}
                        @if ($item->warranty_months)
                            <br><span class="muted">{{ __('app.documents.warranty_term', ['months' => $item->warranty_months]) }}</span>
                        @endif
                    </td>
                    @if ((int) $item->line_total === 0)
                        <td class="right" colspan="2">{{ $item->product?->category?->key === 'service' ? __('app.documents.free') : __('app.documents.included') }}</td>
                    @else
                        <td class="right">{{ $fmt($item->unit_price) }}</td>
                        <td class="right">{{ $fmt($item->line_total) }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
    <p class="muted">{{ __('app.documents.warranty_note') }}</p>
    <table class="totals" style="margin-top:8px">
        @if ((float) $sale->surcharge_percent <= 0)
            <tr><td class="right">{{ __('app.fields.subtotal') }}</td><td class="right" style="width:90px">{{ $fmt($sale->subtotal) }}</td></tr>
            @if ($sale->discount > 0)
                <tr><td class="right">{{ __('app.fields.discount') }}</td><td class="right">{{ $fmt($sale->discount) }}</td></tr>
            @endif
        @endif
        <tr><td class="right"><strong>{{ __('app.fields.total') }}</strong></td><td class="right" style="width:90px"><strong>{{ $fmt($sale->total) }}</strong></td></tr>
        @if ($sale->tax_amount > 0)
            <tr><td class="right">{{ __('app.documents.tax_included') }}</td><td class="right">{{ $fmt($sale->tax_amount) }}</td></tr>
        @endif
        <tr><td class="right">{{ __('app.documents.paid') }}</td><td class="right">{{ $fmt($sale->totalPaid()) }}</td></tr>
        <tr><td class="right"><strong>{{ __('app.fields.balance') }}</strong></td><td class="right"><strong>{{ $fmt($sale->balance) }}</strong></td></tr>
    </table>
    <p class="muted">{{ __('app.fields.status') }}: {{ $sale->status->label() }}</p>
    <p style="margin-top:28px">_______________________________<br>{{ __('app.documents.signature') }}</p>
@endsection
