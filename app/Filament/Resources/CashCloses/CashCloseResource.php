<?php

namespace App\Filament\Resources\CashCloses;

use App\Enums\NavigationGroup;
use App\Filament\Resources\CashCloses\Pages\CreateCashClose;
use App\Filament\Resources\CashCloses\Pages\EditCashClose;
use App\Filament\Resources\CashCloses\Pages\ListCashCloses;
use App\Filament\Resources\CashCloses\Schemas\CashCloseForm;
use App\Filament\Resources\CashCloses\Tables\CashClosesTable;
use App\Filament\Resources\Resource;
use App\Models\CashClose;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CashCloseResource extends Resource
{
    protected static ?string $model = CashClose::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Finance;

    protected static ?int $navigationSort = 2;

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
