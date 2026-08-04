@extends('documents.layout')
@section('title', __('app.documents.formula_title'))
@section('content')
    <h3 style="text-align:center;margin:4px 0">{{ __('app.documents.formula_heading') }}</h3>
    <table>
        <tr><td><strong>{{ __('app.documents.patient') }}:</strong> {{ $rx->customer->full_name }}</td><td class="right">{{ $rx->exam_date?->format('d/m/Y') }}</td></tr>
        <tr>
            <td>
                @if ($rx->customer->id_number) {{ $rx->customer->document_type->label() }}: {{ $rx->customer->id_number }} @endif
                @if (! is_null($rx->customer->age)) · {{ __('app.fields.age') }}: {{ $rx->customer->age }} @endif
            </td>
            <td class="right">@if ($rx->customer->phone) {{ __('app.documents.phone_label') }}: {{ $rx->customer->phone }} @endif</td>
        </tr>
        @if ($rx->customer->address)<tr><td colspan="2">{{ __('app.fields.address') }}: {{ $rx->customer->address }}</td></tr>@endif
    </table>
    @if ($rx->diagnosis)<p><strong>{{ __('app.documents.diagnosis_short') }}:</strong> {{ $rx->diagnosis }}</p>@endif
    <table class="items" style="margin-top:6px">
        <thead>
            <tr><th>{{ __('app.documents.eye') }}</th><th>{{ __('app.fields.sphere') }}</th><th>{{ __('app.fields.cylinder') }}</th><th>{{ __('app.fields.axis') }}</th><th>{{ __('app.fields.add') }}</th><th>{{ __('app.fields.va') }}</th><th>{{ __('app.fields.pd') }}</th></tr>
        </thead>
        <tbody>
            <tr><td>OD</td><td>{{ $rx->od_sphere }}</td><td>{{ $rx->od_cylinder }}</td><td>{{ $rx->od_axis }}</td><td>{{ $rx->od_add }}</td><td>{{ $rx->od_va }}</td><td>{{ $rx->od_pd }}</td></tr>
            <tr><td>OS</td><td>{{ $rx->os_sphere }}</td><td>{{ $rx->os_cylinder }}</td><td>{{ $rx->os_axis }}</td><td>{{ $rx->os_add }}</td><td>{{ $rx->os_va }}</td><td>{{ $rx->os_pd }}</td></tr>
        </tbody>
    </table>
    <p style="margin-top:8px">
        @if ($rx->lens_type) <strong>{{ __('app.fields.lens_type') }}:</strong> {{ $rx->lens_type->label() }}<br> @endif
        @if ($rx->filters) <strong>{{ __('app.fields.filters') }}:</strong> {{ implode(', ', (array) $rx->filters) }}<br> @endif
        @if ($rx->usage) <strong>{{ __('app.fields.usage') }}:</strong> {{ $rx->usage }}<br> @endif
        @if ($rx->control_period) <strong>{{ __('app.documents.control') }}:</strong> {{ $rx->control_period }}<br> @endif
        @if ($rx->drops) <strong>{{ __('app.fields.drops') }}:</strong> {{ $rx->drops }}<br> @endif
        @if ($rx->lensometry) <strong>{{ __('app.fields.lensometry') }}:</strong> {{ $rx->lensometry }} @endif
    </p>
@endsection
