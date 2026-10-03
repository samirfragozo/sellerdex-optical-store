<?php

namespace App\Filament\Resources\CashRegisterSessions\Pages;

use App\Filament\Resources\CashRegisterSessions\CashRegisterSessionResource;
use App\Filament\Resources\CashRegisterSessions\Tables\CashRegisterSessionsTable;
use Filament\Resources\Pages\ViewRecord;

class ViewCashRegisterSession extends ViewRecord
{
    protected static string $resource = CashRegisterSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CashRegisterSessionsTable::adminCloseAction(),
            CashRegisterSessionsTable::reviewAction(),
            CashRegisterSessionsTable::reportAction(),
        ];
    }
}
