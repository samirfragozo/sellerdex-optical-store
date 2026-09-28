<?php

namespace App\Filament\Resources\Products\Pages;

use App\Actions\GenerateProductVariants;
use App\Filament\Concerns\RedirectsToResourceIndex;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    use RedirectsToResourceIndex;

    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generateVariants')
                ->label(__('app.product_actions.generate_variants'))
                ->icon('heroicon-o-squares-plus')
                ->visible(fn (Product $record): bool => $record->optionGroups()
                    ->where('option_groups.is_required', true)
                    ->exists())
                ->requiresConfirmation()
                ->action(function (Product $record): void {
                    $result = app(GenerateProductVariants::class)->handle($record);

                    Notification::make()
                        ->success()
                        ->title(__('app.product_actions.variants_generated', [
                            'created' => $result['created'],
                            'skipped' => $result['skipped'],
                        ]))
                        ->send();
                }),
            // A product a combo uses is kept by the model guard; don't offer to delete it (the built-in trashed rules still apply).
            DeleteAction::make()->visible(fn (Product $record): bool => $record->isDeletable()),
            ForceDeleteAction::make()->hidden(fn (Product $record): bool => ! $record->isDeletable()),
            RestoreAction::make(),
        ];
    }
}
