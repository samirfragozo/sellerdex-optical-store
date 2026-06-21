<?php

namespace App\Filament\Resources\CashCloses;

use App\Filament\Resources\CashCloses\Pages\CreateCashClose;
use App\Filament\Resources\CashCloses\Pages\EditCashClose;
use App\Filament\Resources\CashCloses\Pages\ListCashCloses;
use App\Filament\Resources\CashCloses\Schemas\CashCloseForm;
use App\Filament\Resources\CashCloses\Tables\CashClosesTable;
use App\Models\CashClose;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CashCloseResource extends Resource
{
    protected static ?string $model = CashClose::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    public static function getModelLabel(): string
    {
        return __('app.resources.cash_close.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('app.resources.cash_close.plural');
    }

    public static function getNavigationLabel(): string
    {
        return __('app.resources.cash_close.nav');
    }

    public static function form(Schema $schema): Schema
    {
        return CashCloseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CashClosesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCashCloses::route('/'),
            'create' => CreateCashClose::route('/create'),
            'edit' => EditCashClose::route('/{record}/edit'),
        ];
    }
}
