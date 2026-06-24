@extends('documents.layout')
@section('title', 'Factura '.$sale->number)
@section('content')
    @php($fmt = fn ($v) => '$'.number_format((int) $v, 0, ',', '.'))
    <table>
        <tr>
            <td><strong>No.</strong> {{ $sale->number }}</td>
            <td class="right">{{ $sale->sold_at?->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td>{{ $sale->document_type->label() }}</td>
            <td class="right">Vendedor: {{ $sale->seller?->name }}</td>
        </tr>
    </table>
    <p>
        <strong>Cliente:</strong> {{ $sale->customer->full_name }}<br>
        @if ($sale->customer->id_number) {{ $sale->customer->document_type->label() }}: {{ $sale->customer->id_number }}<br> @endif
        @if ($sale->customer->phone) Tel: {{ $sale->customer->phone }} @endif
    </p>
    <table class="items">
        <thead>
            <tr><th>Cant.</th><th>Descripción</th><th class="right">Vr. Unit.</th><th class="right">Vr. Total</th></tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $item)
                <tr>
                    <td>{{ $item->quantity }}</td>
                    <td>{{ $item->description }}</td>
                    @if ((int) $item->line_total === 0)
                        <td class="right" colspan="2">{{ $item->product?->sku === 'SRV-EXAMEN' ? 'GRATIS' : 'Incluido' }}</td>
                    @else
                        <td class="right">{{ $fmt($item->unit_price) }}</td>
                        <td class="right">{{ $fmt($item->line_total) }}</td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
    <table class="totals" style="margin-top:8px">
        @if ((float) $sale->surcharge_percent <= 0)
            <tr><td class="right">Subtotal</td><td class="right" style="width:90px">{{ $fmt($sale->subtotal) }}</td></tr>
            @if ($sale->discount > 0)
                <tr><td class="right">Descuento</td><td class="right">{{ $fmt($sale->discount) }}</td></tr>
            @endif
        @endif
        <tr><td class="right"><strong>Total</strong></td><td class="right" style="width:90px"><strong>{{ $fmt($sale->total) }}</strong></td></tr>
        <tr><td class="right">Abonado</td><td class="right">{{ $fmt($sale->totalPaid()) }}</td></tr>
        <tr><td class="right"><strong>Saldo</strong></td><td class="right"><strong>{{ $fmt($sale->balance) }}</strong></td></tr>
    </table>
    <p class="muted">Estado: {{ $sale->status->label() }}</p>
    <p style="margin-top:28px">_______________________________<br>Firma y sello</p>
@endsection
