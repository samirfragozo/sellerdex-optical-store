@extends('documents.layout')
@section('title', __('app.documents.warranty_claim_title'))
@section('content')
    <h3 style="text-align:center;margin:4px 0">{{ __('app.documents.warranty_claim_title') }} #{{ $claim->id }}</h3>
    <table>
        <tr>
            <td><strong>{{ __('app.fields.received_at') }}:</strong> {{ $claim->received_at->format('d/m/Y') }}</td>
            <td class="right"><strong>{{ __('app.fields.sale') }}:</strong> {{ $claim->saleItem?->sale?->number }}</td>
        </tr>
        <tr><td colspan="2"><strong>{{ __('app.fields.customer') }}:</strong> {{ $claim->saleItem?->sale?->customer?->full_name }}</td></tr>
        <tr><td colspan="2"><strong>{{ __('app.fields.item') }}:</strong> {{ $claim->saleItem?->description }}</td></tr>
        <tr>
            <td><strong>{{ __('app.fields.type') }}:</strong> {{ $claim->type->label() }}</td>
            <td class="right"><strong>{{ __('app.fields.status') }}:</strong> {{ $claim->status->label() }}</td>
        </tr>
    </table>
    <p><strong>{{ __('app.warranty.fields.customer_description') }}:</strong> {{ $claim->customer_description }}</p>
    @if ($claim->resolution)
        <p><strong>{{ __('app.warranty.fields.resolution') }}:</strong> {{ $claim->resolution->label() }}</p>
    @endif
    @if ($claim->rejection_reason)
        <p><strong>{{ __('app.warranty.fields.rejection_reason') }}:</strong> {{ $claim->rejection_reason }}</p>
    @endif
    <p style="margin-top:8px">{{ __('app.documents.warranty_claim_note') }}</p>
    <p style="margin-top:32px;border-top:1px solid #000;width:50%;padding-top:2px">{{ __('app.documents.signature') }}</p>
@endsection
