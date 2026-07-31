<?php

namespace App\Filament\Superadmin\Resources;

use App\Filament\Superadmin\Resources\CompanyResource\Pages\CreateCompany;
use App\Filament\Superadmin\Resources\CompanyResource\Pages\EditCompany;
use App\Filament\Superadmin\Resources\CompanyResource\Pages\ListCompanies;
use App\Models\Company;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CompanyResource extends Resource
{
    protected static ?string $model = Company::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $modelLabel = 'Empresa';

    protected static ?string $pluralModelLabel = 'Empresas';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nombre')->required(),
            TextInput::make('tax_id')->label('NIT'),
            TextInput::make('address')->label('Dirección'),
            TextInput::make('phones')->label('Teléfonos'),
            Toggle::make('is_active')->label('Activa')->default(true),
            Select::make('plan')
                ->label('Plan')
                ->options(['free' => 'Free', 'hobby' => 'Hobby', 'pro' => 'Pro'])
                ->default('free'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable(),
                TextColumn::make('plan')->label('Plan'),
                IconColumn::make('is_active')->label('Activa')->boolean(),
                TextColumn::make('users_count')->label('Usuarios')->counts('users'),
                TextColumn::make('created_at')->label('Registro')->date('d/m/Y'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('toggle_active')
                    ->label(fn (Company $record) => $record->is_active ? 'Suspender' : 'Activar')
                    ->icon(fn (Company $record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (Company $record) => $record->is_active ? 'danger' : 'success')
                    ->action(fn (Company $record) => $record->update(['is_active' => ! $record->is_active])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCompanies::route('/'),
            'create' => CreateCompany::route('/create'),
            'edit' => EditCompany::route('/{record}/edit'),
        ];
    }
}
