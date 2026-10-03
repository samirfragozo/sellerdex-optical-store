<?php

namespace App\Filament\Resources\CashRegisterSessions;

use App\Filament\Resources\CashRegisterSessions\Pages\ListCashRegisterSessions;
use App\Filament\Resources\CashRegisterSessions\Pages\ViewCashRegisterSession;
use App\Filament\Resources\CashRegisterSessions\Schemas\CashRegisterSessionInfolist;
use App\Filament\Resources\CashRegisterSessions\Tables\CashRegisterSessionsTable;
use App\Filament\Resources\Resource;
use App\Models\CashRegisterSession;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CashRegisterSessionResource extends Resource
{
    protected static ?string $model = CashRegisterSession::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return CashRegisterSessionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CashRegisterSessionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCashRegisterSessions::route('/'),
            'view' => ViewCashRegisterSession::route('/{record}'),
        ];
    }
}
