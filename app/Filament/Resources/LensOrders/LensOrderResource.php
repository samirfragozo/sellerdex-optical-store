<?php

namespace App\Filament\Resources\LensOrders;

use App\Filament\Resources\LensOrders\Pages\CreateLensOrder;
use App\Filament\Resources\LensOrders\Pages\EditLensOrder;
use App\Filament\Resources\LensOrders\Pages\ListLensOrders;
use App\Filament\Resources\LensOrders\Schemas\LensOrderForm;
use App\Filament\Resources\LensOrders\Tables\LensOrdersTable;
use App\Models\LensOrder;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LensOrderResource extends Resource
{
    protected static ?string $model = LensOrder::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBeaker;

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('app.resources.lens_order.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.resources.lens_order.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('app.resources.lens_order.nav');
    }

    public static function form(Schema $schema): Schema
    {
        return LensOrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LensOrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLensOrders::route('/'),
            'create' => CreateLensOrder::route('/create'),
            'edit' => EditLensOrder::route('/{record}/edit'),
        ];
    }
}
