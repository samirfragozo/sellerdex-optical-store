<?php

namespace App\Filament\Resources\LensOrders\Pages;

use App\Filament\Resources\LensOrders\LensOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListLensOrders extends ListRecords
{
    protected static string $resource = LensOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'pendientes' => Tab::make(__('app.tabs.pending'))
                ->modifyQueryUsing(fn (Builder $query) => $query->pending()),
            'todas' => Tab::make(__('app.tabs.all')),
        ];
    }
}
