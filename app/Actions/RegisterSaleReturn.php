<?php

namespace App\Actions;

use App\Enums\LensOrderStatus;
use App\Enums\MoneyDestination;
use App\Enums\SaleReturnType;
use App\Enums\SaleStatus;
use App\Enums\StockMovementType;
use App\Models\CashRegisterSession;
use App\Models\PaymentMethod;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\User;
use App\Support\StockLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RegisterSaleReturn
{
    /**
     * Give value back on a sale: return lines, lower its value, or void it (a layaway void is a plan separe
     * cancellation). The money goes out as a refund, store credit, or both; whatever the customer hadn't paid
     * simply stops being owed. `$approver` (an admin; defaults to the actor) signs it off when a seller acts.
     *
     * @param  array{reason: string, items?: list<array{sale_item_id: int, quantity: int, restock?: bool}>, amount?: int, refund_amount?: int, refund_payment_method_id?: int|null, store_credit_amount?: int}  $data
     */
    public function handle(Sale $sale, SaleReturnType $type, array $data, User $actor, ?User $approver = null): SaleReturn
    {
        $approver ??= $actor;
        // Defense in depth: the Filament form already asked for the PIN.
        if (! $approver->isAdmin() || $approver->company_id !== $sale->company_id) {
            throw ValidationException::withMessages(['approval_pin' => __('app.approval.required')]);
        }

        $this->validate($sale, $type, $data);

        return DB::transaction(function () use ($sale, $type, $data, $actor, $approver): SaleReturn {
            $sale = Sale::withoutGlobalScopes()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();
            $refund = (int) ($data['refund_amount'] ?? 0);
            $credit = (int) ($data['store_credit_amount'] ?? 0);
            $this->guard($sale, $type, $credit);

            $return = new SaleReturn([
                'company_id' => $sale->company_id,
                'sale_id' => $sale->id,
                'type' => $type,
                'reason' => $data['reason'],
                'money_destination' => MoneyDestination::for($refund, $credit),
                'refund_amount' => $refund,
                'refund_payment_method_id' => $refund > 0 ? (int) $data['refund_payment_method_id'] : null,
                'store_credit_amount' => $credit,
                'user_id' => $actor->id,
                'approved_by' => $approver->id,
            ]);

            match ($type) {
                SaleReturnType::Return => $this->returnLines($sale, $return, $data['items']),
                SaleReturnType::ValueAdjustment => $this->adjustValue($sale, $return, (int) $data['amount']),
                SaleReturnType::Void => $this->voidSale($sale, $return),
            };

            $this->moveMoney($sale, $return, $actor);

            if ($type === SaleReturnType::Void) {
                // The Sale::updated hook gives the still-sold units back to stock.
                $sale->refresh()->update(['status' => SaleStatus::Voided]);
            }
            $sale->refresh()->recalculateStatus();

            return $return;
        });
    }

    /** Checks that need the locked sale: nothing but a void on a voided sale, and store credit needs somewhere to go. */
    private function guard(Sale $sale, SaleReturnType $type, int $credit): void
    {
        if ($type !== SaleReturnType::Void && $sale->status === SaleStatus::Voided) {
            throw ValidationException::withMessages(['reason' => __('app.sale_return.sale_voided')]);
        }

        if ($credit > 0 && $sale->customer_id === null) {
            throw ValidationException::withMessages(['store_credit_amount' => __('app.store_credit.needs_customer')]);
        }

        if ($credit > 0 && PaymentMethod::storeCreditFor($sale->company_id) === null) {
            throw ValidationException::withMessages(['store_credit_amount' => __('app.sale_return.no_store_credit_method')]);
        }
    }

    /** @param  array<string, mixed>  $data */
    private function validate(Sale $sale, SaleReturnType $type, array $data): void
    {
        Validator::make($data, [
            'reason' => ['required', 'string', 'max:255'],
            'items' => [Rule::requiredIf($type === SaleReturnType::Return), 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer', 'distinct', Rule::exists('sale_items', 'id')->where('sale_id', $sale->id)],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.restock' => ['boolean'],
            'amount' => [Rule::requiredIf($type === SaleReturnType::ValueAdjustment), 'integer', 'min:1'],
            'refund_amount' => ['integer', 'min:0'],
            'refund_payment_method_id' => [
                Rule::requiredIf((int) ($data['refund_amount'] ?? 0) > 0),
                'nullable',
                Rule::exists('payment_methods', 'id')->where(fn ($query) => $query->where('company_id', $sale->company_id)->where('is_store_credit', false)->where('is_active', true)),
            ],
            'store_credit_amount' => ['integer', 'min:0'],
        ])->validate();
    }

    /**
     * Each line is worth its share of the line total after the sale discount (the payment surcharge stays),
     * taken cumulatively so unit-by-unit returns never round past the line. The return as a whole never
     * exceeds what is left of the sale's value (e.g. goods coming back after a full value adjustment).
     *
     * @param  list<array{sale_item_id: int, quantity: int, restock?: bool}>  $lines
     */
    private function returnLines(Sale $sale, SaleReturn $return, array $lines): void
    {
        $items = $sale->items()->with(['product', 'lensConfig', 'lensOrder'])->get()->keyBy('id');
        $factor = $sale->chargedFactor();
        $valueOf = fn (SaleItem $item, int $units): int => (int) round($item->line_total * $units / $item->quantity * $factor);
        $rows = [];

        foreach ($lines as $index => $line) {
            $item = $items->get($line['sale_item_id']);
            $quantity = (int) $line['quantity'];
            if ($quantity > $item->returnableQuantity()) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => __('app.sale_return.too_many_units', ['count' => $item->returnableQuantity()]),
                ]);
            }

            $rows[] = [
                $item,
                $quantity,
                $valueOf($item, $item->returnedQuantity() + $quantity) - $valueOf($item, $item->returnedQuantity()),
                // Made-to-order lenses never go back to stock.
                ($line['restock'] ?? false) && ! $item->isLens() && $item->movesStock(),
            ];
        }

        // Trim the excess off the last lines first.
        $excess = array_sum(array_column($rows, 2)) - max(0, $sale->netTotal());
        for ($i = count($rows) - 1; $i >= 0 && $excess > 0; $i--) {
            $cut = min($excess, $rows[$i][2]);
            $rows[$i][2] -= $cut;
            $excess -= $cut;
        }

        $return->total = array_sum(array_column($rows, 2));
        $return->loss_amount = $this->settleLenses($rows);
        $return->save();

        foreach ($rows as [$item, $quantity, $amount, $restock]) {
            $return->items()->create(['sale_item_id' => $item->id, 'quantity' => $quantity, 'amount' => $amount, 'restock' => $restock]);
            if ($restock) {
                StockLedger::record($item->product, StockMovementType::SaleReturn, $quantity, $return);
            }
        }
    }

    private function adjustValue(Sale $sale, SaleReturn $return, int $amount): void
    {
        if ($amount > $sale->netTotal()) {
            throw ValidationException::withMessages(['amount' => __('app.sale_return.amount_exceeds')]);
        }

        $return->total = $amount;
        $return->save();
    }

    /** Cancel the whole sale; a layaway keeps the company's cancellation fee out of what was paid. */
    private function voidSale(Sale $sale, SaleReturn $return): void
    {
        if ($sale->is_delivered || $sale->status === SaleStatus::Voided) {
            throw ValidationException::withMessages(['reason' => __('app.sale_return.cannot_void')]);
        }

        $lines = $sale->items()->with(['lensConfig', 'lensOrder'])->get()
            ->map(fn (SaleItem $item): array => [$item, $item->returnableQuantity()])
            ->filter(fn (array $line): bool => $line[1] > 0)
            ->all();

        $return->total = $sale->netTotal();
        $return->retained_amount = $sale->cancellationFee();
        $return->loss_amount = $this->settleLenses($lines);
        $return->save();
    }

    /**
     * Lens orders not yet sent are cancelled; a lens already at the lab is lost at its cost.
     *
     * @param  array<int, array{0: SaleItem, 1: int}>  $lines
     */
    private function settleLenses(array $lines): int
    {
        $loss = 0;

        foreach ($lines as [$item, $quantity]) {
            $order = $item->isLens() ? $item->lensOrder : null;
            if ($order?->lab_status === LensOrderStatus::PendingAssignment) {
                $order->update(['lab_status' => LensOrderStatus::Cancelled]);
            } elseif ($order !== null && $order->lab_status !== LensOrderStatus::Cancelled) {
                $loss += $item->unit_cost * $quantity;
            }
        }

        return $loss;
    }

    /** Runs after the return row is saved, so netTotal() is already the new value; a net below zero (items edited after a return) never widens the cap. */
    private function moveMoney(Sale $sale, SaleReturn $return, User $actor): void
    {
        $out = $return->refund_amount + $return->store_credit_amount;
        $paid = $sale->totalPaid();

        if ($return->type === SaleReturnType::Void) {
            $due = max(0, $paid - $return->retained_amount);
            if ($out !== $due) {
                throw ValidationException::withMessages(['store_credit_amount' => __('app.sale_return.void_money_mismatch', ['amount' => $this->money($due)])]);
            }
        } elseif ($out > ($max = max(0, $paid - max(0, $sale->netTotal())))) {
            throw ValidationException::withMessages(['store_credit_amount' => __('app.sale_return.money_exceeds', ['max' => $this->money($max)])]);
        }

        if ($return->refund_amount > 0) {
            $cashMethodId = PaymentMethod::withoutGlobalScopes()->where('company_id', $sale->company_id)->where('is_default', true)->value('id');
            if ((int) $return->refund_payment_method_id === (int) $cashMethodId && CashRegisterSession::openFor($actor) === null) {
                throw ValidationException::withMessages(['refund_payment_method_id' => __('app.sale_return.cash_needs_session')]);
            }
            $this->payBack($sale, $return->refund_payment_method_id, $return->refund_amount, $return->reason, $actor);
        }

        if ($return->store_credit_amount > 0) {
            $this->payBack($sale, PaymentMethod::storeCreditFor($sale->company_id)->id, $return->store_credit_amount, $return->reason, $actor);
        }
    }

    private function payBack(Sale $sale, int $methodId, int $amount, string $reason, User $actor): void
    {
        $sale->payments()->create([
            'company_id' => $sale->company_id,
            'payment_method_id' => $methodId,
            'amount' => -$amount,
            'paid_at' => now()->toDateString(),
            'received_by' => $actor->id,
            'notes' => $reason,
        ]);
    }

    private function money(int $amount): string
    {
        return number_format($amount, 0, ',', '.');
    }
}
