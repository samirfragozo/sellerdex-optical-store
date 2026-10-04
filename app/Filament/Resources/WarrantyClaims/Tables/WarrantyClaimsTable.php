<?php

namespace App\Filament\Resources\WarrantyClaims\Tables;

use App\Actions\ResolveWarrantyClaim;
use App\Enums\WarrantyClaimStatus;
use App\Enums\WarrantyResolution;
use App\Enums\WarrantyResponsible;
use App\Filament\Forms\ApprovalPinInput;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\WarrantyClaim;
use App\Support\AdminApproval;
use DomainException;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class WarrantyClaimsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('saleItem.sale.number')->label(__('app.fields.number')),
                TextColumn::make('saleItem.description')->label(__('app.fields.item'))->searchable(),
                TextColumn::make('saleItem.sale.customer.full_name')->label(__('app.fields.customer')),
                TextColumn::make('type')->label(__('app.fields.type'))->badge(),
                TextColumn::make('status')->label(__('app.fields.status'))->badge(),
                TextColumn::make('received_at')->label(__('app.fields.received_at'))->date('d/m/Y')->sortable(),
                TextColumn::make('resolution')->label(__('app.warranty.fields.resolution'))->badge()->placeholder('—'),
            ])
            ->defaultSort('received_at', 'desc')
            ->filters([
                SelectFilter::make('status')->label(__('app.fields.status'))->options(WarrantyClaimStatus::options()),
            ])
            ->recordActions([
                Action::make('startReview')
                    ->label(__('app.warranty.actions.start_review'))
                    ->visible(fn (WarrantyClaim $record): bool => $record->status === WarrantyClaimStatus::Received)
                    ->requiresConfirmation()
                    ->action(fn (WarrantyClaim $record) => $record->advance(WarrantyClaimStatus::InReview)),
                Action::make('sendToSupplier')
                    ->label(__('app.warranty.actions.send_to_supplier'))
                    ->visible(fn (WarrantyClaim $record): bool => $record->status === WarrantyClaimStatus::InReview)
                    ->requiresConfirmation()
                    ->action(fn (WarrantyClaim $record) => $record->advance(WarrantyClaimStatus::AtSupplier)),
                Action::make('resolve')
                    ->label(__('app.warranty.actions.resolve'))
                    ->visible(fn (WarrantyClaim $record): bool => in_array($record->status, [WarrantyClaimStatus::InReview, WarrantyClaimStatus::AtSupplier], true))
                    ->schema(fn (WarrantyClaim $record): array => self::resolveFields($record))
                    ->action(function (WarrantyClaim $record, array $data): void {
                        try {
                            $approver = AdminApproval::ensure(AdminApproval::approver(auth()->user(), $data['approval_pin'] ?? null));
                            app(ResolveWarrantyClaim::class)->handle($record, WarrantyResolution::from($data['resolution']), $data, auth()->user(), $approver);
                        } catch (ValidationException $exception) {
                            throw ValidationException::withMessages(collect($exception->errors())
                                ->mapWithKeys(fn (array $messages, string $key): array => ['mountedActions.0.data.'.self::formKey($key, $record) => $messages])
                                ->all());
                        } catch (DomainException $exception) {
                            Notification::make()->danger()->title($exception->getMessage())->send();

                            return;
                        }

                        Notification::make()->success()->title(__('app.warranty.resolved'))->send();
                    }),
                Action::make('deliver')
                    ->label(__('app.warranty.actions.deliver'))
                    ->visible(fn (WarrantyClaim $record): bool => $record->status === WarrantyClaimStatus::Resolved)
                    ->requiresConfirmation()
                    ->action(fn (WarrantyClaim $record) => $record->advance(WarrantyClaimStatus::Delivered)),
                Action::make('printReceipt')
                    ->label(__('app.warranty.actions.print'))
                    ->icon('heroicon-o-printer')
                    ->url(fn (WarrantyClaim $record): string => route('documents.warranty-claim', $record))
                    ->openUrlInNewTab(),
            ]);
    }

    /** Puts an error under a field the modal shows; anything without one (the return's `items`, `reason`, a hidden field) goes under the resolution. */
    private static function formKey(string $key, WarrantyClaim $claim): string
    {
        // Walk-in sales have no store-credit field, so the money-limit errors go under the refund.
        if ($key === 'store_credit_amount' && $claim->saleItem->sale->customer_id === null) {
            return 'refund_amount';
        }

        $shown = ['resolution', 'responsible', 'store_cost', 'rejection_reason', 'refund_amount', 'refund_payment_method_id', 'store_credit_amount', 'approval_pin'];
        if (! $claim->saleItem->isLens()) {
            $shown[] = 'replacement_product_id';
        }

        return in_array($key, $shown, true) ? $key : 'resolution';
    }

    /** @return list<mixed> */
    private static function resolveFields(WarrantyClaim $claim): array
    {
        $isLens = $claim->saleItem->isLens();
        $hasCustomer = $claim->saleItem->sale->customer_id !== null;
        $refundDue = ResolveWarrantyClaim::refundDue($claim->saleItem);
        $isRefund = fn (Get $get): bool => $get('resolution') === WarrantyResolution::Refund->value;
        $isRejected = fn (Get $get): bool => $get('resolution') === WarrantyResolution::Rejected->value;

        return [
            Select::make('resolution')
                ->label(__('app.warranty.fields.resolution'))
                ->options(WarrantyResolution::options())
                ->required()
                ->live(),
            Select::make('responsible')
                ->label(__('app.warranty.fields.responsible'))
                ->options(WarrantyResponsible::options())
                ->hidden($isRejected)
                ->live(),
            TextInput::make('store_cost')
                ->label(__('app.warranty.fields.store_cost'))
                ->integer()
                ->minValue(0)
                ->default(0)
                ->prefix('$')
                ->visible(fn (Get $get): bool => $get('responsible') === WarrantyResponsible::Store->value && ! $isRejected($get)),
            Textarea::make('rejection_reason')
                ->label(__('app.warranty.fields.rejection_reason'))
                ->visible($isRejected)
                ->required($isRejected),
            Select::make('replacement_product_id')
                ->label(__('app.warranty.fields.replacement_product'))
                ->options(fn (): array => Product::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                ->searchable()
                ->visible(fn (Get $get): bool => ! $isLens && $get('resolution') === WarrantyResolution::OtherReplacement->value)
                ->required(fn (Get $get): bool => ! $isLens && $get('resolution') === WarrantyResolution::OtherReplacement->value),
            TextInput::make('refund_amount')
                ->label(__('app.sale_return.fields.refund_amount'))
                ->integer()
                ->minValue(0)
                ->default($hasCustomer ? 0 : $refundDue)
                ->prefix('$')
                ->visible($isRefund),
            Select::make('refund_payment_method_id')
                ->label(__('app.sale_return.fields.refund_method'))
                ->options(fn (): array => PaymentMethod::withoutGlobalScopes()
                    ->where('company_id', $claim->company_id)
                    ->where('is_store_credit', false)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->pluck('name', 'id')
                    ->all())
                ->visible($isRefund)
                ->required(fn (Get $get): bool => $isRefund($get) && (int) $get('refund_amount') > 0),
            TextInput::make('store_credit_amount')
                ->label(__('app.sale_return.fields.store_credit_amount'))
                ->integer()
                ->minValue(0)
                ->default($hasCustomer ? $refundDue : 0)
                ->prefix('$')
                ->visible(fn (Get $get): bool => $isRefund($get) && $hasCustomer),
            ApprovalPinInput::make(),
        ];
    }
}
