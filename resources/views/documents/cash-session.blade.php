@extends('documents.layout')
@section('title', __('app.cash_report.title'))
@section('content')
    @php($fmt = fn ($v) => '$'.number_format((int) $v, 0, ',', '.'))
    <h3 style="text-align:center;margin:4px 0">{{ __('app.cash_report.title') }}</h3>
    <table>
        <tr>
            <td><strong>{{ __('app.cash_session_admin.cashier') }}:</strong> {{ $session->user?->name }}</td>
            <td class="right">
                @if ($session->closed_by_admin) {{ __('app.cash_session_admin.closed_by_admin') }}: {{ $session->closedBy?->name }} @endif
                @if ($session->reviewed_at) · {{ __('app.cash_session_admin.reviewed') }} @endif
            </td>
        </tr>
        <tr>
            <td><strong>{{ __('app.cash_session_admin.opened_at') }}:</strong> {{ $session->opened_at->format('d/m/Y H:i') }}</td>
            <td class="right"><strong>{{ __('app.cash_session_admin.closed_at') }}:</strong> {{ $session->closed_at?->format('d/m/Y H:i') ?? __('app.cash_session_admin.open') }}</td>
        </tr>
        <tr><td colspan="2"><strong>{{ __('app.pos.cash_session.opening_cash') }}:</strong> {{ $fmt($session->opening_cash) }}</td></tr>
    </table>

    <h4 style="margin:8px 0 2px">{{ __('app.cash_session_admin.payments_count') }}</h4>
    <table>
        <tr>
            <td>{{ __('app.cash_report.sales_count', ['count' => $salesCount]) }}</td>
            <td class="right">{{ $fmt($salesTotal) }}</td>
        </tr>
        <tr><td colspan="2" class="muted">{{ __('app.cash_report.returns_none') }}</td></tr>
    </table>

    <h4 style="margin:8px 0 2px">{{ __('app.cash_report.methods') }}</h4>
    <table class="items">
        <thead>
            <tr>
                <th>{{ __('app.cash_session_admin.method') }}</th>
                <th class="right">{{ __('app.pos.cash_session.expected') }}</th>
                <th class="right">{{ __('app.pos.cash_session.counted') }}</th>
                <th class="right">{{ __('app.pos.cash_session.difference') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($session->counts->sortBy('payment_method_id') as $count)
                <tr>
                    <td>{{ $count->paymentMethod?->name }}</td>
                    <td class="right">{{ $fmt($count->expected) }}</td>
                    <td class="right">{{ $fmt($count->counted) }}</td>
                    <td class="right">{{ $fmt($count->difference) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($session->movements->isNotEmpty())
        <h4 style="margin:8px 0 2px">{{ __('app.cash_report.movements') }}</h4>
        <table class="items">
            <tbody>
                @foreach ($session->movements as $movement)
                    <tr>
                        <td>{{ $movement->type->label() }}</td>
                        <td>{{ $movement->reason }}</td>
                        <td class="right">{{ $fmt($movement->amount) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($session->closed_at)
        <p><strong>{{ __('app.pos.cash_session.cash_left') }}:</strong> {{ $fmt($session->cash_left) }}</p>
    @endif
    @if (filled($session->notes))
        <p><strong>{{ __('app.fields.notes') }}:</strong> {{ $session->notes }}</p>
    @endif
@endsection
