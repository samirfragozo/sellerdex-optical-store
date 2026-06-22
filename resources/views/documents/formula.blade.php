@extends('documents.layout')
@section('title', 'Fórmula')
@section('content')
    <h3 style="text-align:center;margin:4px 0">PRESCRIPCIÓN DE LENTES OFTÁLMICOS</h3>
    <table>
        <tr><td><strong>Paciente:</strong> {{ $rx->customer->full_name }}</td><td class="right">{{ $rx->exam_date?->format('d/m/Y') }}</td></tr>
        <tr>
            <td>
                @if ($rx->customer->id_number) {{ $rx->customer->document_type->label() }}: {{ $rx->customer->id_number }} @endif
                @if (! is_null($rx->customer->age)) · Edad: {{ $rx->customer->age }} @endif
            </td>
            <td class="right">@if ($rx->customer->phone) Tel: {{ $rx->customer->phone }} @endif</td>
        </tr>
        @if ($rx->customer->address)<tr><td colspan="2">Dirección: {{ $rx->customer->address }}</td></tr>@endif
    </table>
    @if ($rx->diagnosis)<p><strong>R:</strong> {{ $rx->diagnosis }}</p>@endif
    <table class="items" style="margin-top:6px">
        <thead>
            <tr><th>Ojo</th><th>Esfera</th><th>Cilindro</th><th>Eje</th><th>ADD</th><th>AV</th><th>DP</th></tr>
        </thead>
        <tbody>
            <tr><td>OD</td><td>{{ $rx->od_sphere }}</td><td>{{ $rx->od_cylinder }}</td><td>{{ $rx->od_axis }}</td><td>{{ $rx->od_add }}</td><td>{{ $rx->od_va }}</td><td>{{ $rx->od_pd }}</td></tr>
            <tr><td>OS</td><td>{{ $rx->os_sphere }}</td><td>{{ $rx->os_cylinder }}</td><td>{{ $rx->os_axis }}</td><td>{{ $rx->os_add }}</td><td>{{ $rx->os_va }}</td><td>{{ $rx->os_pd }}</td></tr>
        </tbody>
    </table>
    <p style="margin-top:8px">
        @if ($rx->lens_type) <strong>Tipo de lente:</strong> {{ $rx->lens_type->label() }}<br> @endif
        @if ($rx->filters) <strong>Filtros:</strong> {{ implode(', ', (array) $rx->filters) }}<br> @endif
        @if ($rx->usage) <strong>Uso:</strong> {{ $rx->usage }}<br> @endif
        @if ($rx->control_period) <strong>Control:</strong> {{ $rx->control_period }}<br> @endif
        @if ($rx->drops) <strong>Gotas:</strong> {{ $rx->drops }}<br> @endif
        @if ($rx->lensometry) <strong>Lensometría:</strong> {{ $rx->lensometry }} @endif
    </p>
@endsection
