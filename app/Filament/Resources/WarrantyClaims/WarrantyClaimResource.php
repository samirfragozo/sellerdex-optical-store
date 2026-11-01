<?php

namespace App\Filament\Resources\WarrantyClaims;

use App\Enums\NavigationGroup;
use App\Filament\Resources\Resource;
use App\Filament\Resources\WarrantyClaims\Pages\CreateWarrantyClaim;
use App\Filament\Resources\WarrantyClaims\Pages\ListWarrantyClaims;
use App\Filament\Resources\WarrantyClaims\Schemas\WarrantyClaimForm;
use App\Filament\Resources\WarrantyClaims\Tables\WarrantyClaimsTable;
use App\Models\WarrantyClaim;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class WarrantyClaimResource extends Resource
{
    protected static ?string $model = WarrantyClaim::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = NavigationGroup::Counter;

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return WarrantyClaimForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WarrantyClaimsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWarrantyClaims::route('/'),
            'create' => CreateWarrantyClaim::route('/create'),
        ];
    }
}
