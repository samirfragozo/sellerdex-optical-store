<?php

namespace App\Filament\Resources\Taxes;

use App\Filament\Resources\Resource;
use App\Filament\Resources\Taxes\Pages\CreateTax;
use App\Filament\Resources\Taxes\Pages\EditTax;
use App\Filament\Resources\Taxes\Pages\ListTaxes;
use App\Filament\Resources\Taxes\Schemas\TaxForm;
use App\Filament\Resources\Taxes\Tables\TaxesTable;
use App\Models\Tax;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TaxResource extends Resource
{
    protected static ?string $model = Tax::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    public static function canAccess(): bool
    {
        return auth()->user()?->isAdmin() === true;
    }

    public static function form(Schema $schema): Schema
    {
        return TaxForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TaxesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTaxes::route('/'),
            'create' => CreateTax::route('/create'),
            'edit' => EditTax::route('/{record}/edit'),
        ];
    }
}
