@extends('documents.layout')
@use('App\Models\Prescription')
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
            <tr><th>{{ __('app.documents.eye') }}</th><th>{{ __('app.fields.sphere') }}</th><th>{{ __('app.fields.cylinder') }}</th><th>{{ __('app.fields.axis') }}</th><th>{{ __('app.fields.add') }}</th><th>{{ __('app.fields.prism') }}</th><th>{{ __('app.fields.prism_base') }}</th><th>{{ __('app.fields.va') }}</th><th>{{ __('app.fields.pd') }}</th></tr>
        </thead>
        <tbody>
            <tr><td>OD</td><td>{{ Prescription::formatDiopter($rx->od_sphere) }}</td><td>{{ Prescription::formatDiopter($rx->od_cylinder) }}</td><td>{{ $rx->od_axis }}</td><td>{{ Prescription::formatDiopter($rx->od_add) }}</td><td>{{ $rx->od_prism }}</td><td>{{ $rx->od_prism_base?->label() }}</td><td>{{ $rx->od_va }}</td><td>{{ $rx->od_pd }}</td></tr>
            <tr><td>OS</td><td>{{ Prescription::formatDiopter($rx->os_sphere) }}</td><td>{{ Prescription::formatDiopter($rx->os_cylinder) }}</td><td>{{ $rx->os_axis }}</td><td>{{ Prescription::formatDiopter($rx->os_add) }}</td><td>{{ $rx->os_prism }}</td><td>{{ $rx->os_prism_base?->label() }}</td><td>{{ $rx->os_va }}</td><td>{{ $rx->os_pd }}</td></tr>
        </tbody>
    </table>
    <p style="margin-top:8px">
        @if ($rx->filters) <strong>{{ __('app.fields.filters') }}:</strong> {{ implode(', ', (array) $rx->filters) }} @endif
    </p>
    @if ($rx->notes)<p><strong>{{ __('app.fields.notes') }}:</strong> {{ $rx->notes }}</p>@endif
    <p>
        @if ($rx->prescriber_name) <strong>{{ __('app.fields.prescriber_name') }}:</strong> {{ $rx->prescriber_name }} @endif
        @if ($rx->prescriber_license) · {{ __('app.fields.prescriber_license') }}: {{ $rx->prescriber_license }} @endif
    </p>
    @if ($rx->expires_at)
        <p><strong>{{ __('app.fields.expires_at') }}:</strong> {{ $rx->expires_at->format('d/m/Y') }} @if ($rx->isExpired()) ({{ __('app.documents.expired') }}) @endif</p>
    @endif
    @if (($showAttachmentLink ?? false) && $rx->attachment)
        <p class="no-print"><a href="{{ route('documents.prescription.attachment', $rx) }}" target="_blank" rel="noopener">{{ __('app.documents.view_attachment') }}</a></p>
    @endif
@endsection
