@extends('documents.layout')
@use('App\Models\Prescription')
@php($rx = $order->prescription_snapshot ?? [])
@section('title', __('app.documents.lab_order_title'))
@section('content')
    <h3 style="text-align:center;margin:4px 0">{{ __('app.documents.lab_order_heading') }}</h3>
    <table>
        <tr>
            <td><strong>{{ __('app.lab_order.message.title', ['number' => $order->id, 'sale' => $order->saleItem?->sale?->number]) }}</strong></td>
            <td class="right">{{ now()->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td><strong>{{ __('app.fields.laboratory') }}:</strong> {{ $order->supplier?->name }}</td>
            <td class="right">
                @if ($order->sent_at) {{ __('app.fields.sent_at') }}: {{ $order->sent_at->format('d/m/Y') }} @endif
                @if ($order->expected_date) · {{ __('app.fields.expected_date') }}: {{ $order->expected_date->format('d/m/Y') }} @endif
            </td>
        </tr>
        <tr><td colspan="2"><strong>{{ __('app.documents.patient') }}:</strong> {{ $order->saleItem?->lensConfig?->patient?->full_name }}</td></tr>
    </table>
    @if ($order->remakeOf)<p><strong>{{ __('app.lab_order.is_remake') }}:</strong> {{ __('app.lab_order.remake_of', ['id' => $order->remakeOf->id]) }}</p>@endif
    <table class="items" style="margin-top:6px">
        <thead>
            <tr><th>{{ __('app.documents.eye') }}</th><th>{{ __('app.fields.sphere') }}</th><th>{{ __('app.fields.cylinder') }}</th><th>{{ __('app.fields.axis') }}</th><th>{{ __('app.fields.add') }}</th><th>{{ __('app.fields.prism') }}</th><th>{{ __('app.fields.prism_base') }}</th><th>{{ __('app.fields.pd') }}</th><th>{{ __('app.lab_order.height') }}</th></tr>
        </thead>
        <tbody>
            @foreach (['od', 'os'] as $side)
                <tr>
                    <td>{{ strtoupper($side) }}</td>
                    <td>{{ Prescription::formatDiopter($rx["{$side}_sphere"] ?? null) }}</td>
                    <td>{{ Prescription::formatDiopter($rx["{$side}_cylinder"] ?? null) }}</td>
                    <td>{{ $rx["{$side}_axis"] ?? '' }}</td>
                    <td>{{ Prescription::formatDiopter($rx["{$side}_add"] ?? null) }}</td>
                    <td>{{ $rx["{$side}_prism"] ?? '' }}</td>
                    <td>{{ filled($rx["{$side}_prism_base"] ?? null) ? __('app.prism_base.'.$rx["{$side}_prism_base"]) : '' }}</td>
                    <td>{{ $order->{"{$side}_pd"} }}</td>
                    <td>{{ $order->{"{$side}_height"} }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
    <p style="margin-top:8px"><strong>{{ __('app.lab_order.lens') }}:</strong> {{ $order->saleItem?->description }}</p>
    <p>
        <strong>{{ __('app.lab_order.sections.frame') }}:</strong>
        {{ $order->frame_type?->label() }} · A {{ $order->frame_a }} · B {{ $order->frame_b }} · DBL {{ $order->frame_dbl }}
        · {{ $order->frame_source?->label() }}@if ($frame) · {{ $frame }}@endif
        @if ($order->customer_frame_condition)<br>{{ __('app.fields.customer_frame_condition') }}: {{ $order->customer_frame_condition }}@endif
    </p>
    @if ($order->notes)<p><strong>{{ __('app.fields.notes') }}:</strong> {{ $order->notes }}</p>@endif
@endsection
