<?php

namespace App\Filament\SuperAdmin\Resources\AdminRoleAssignments\Tables;

use App\Models\AdminRoleAssignment;
use App\Models\AuditLog;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdminRoleAssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('User')->searchable(),
                TextColumn::make('role')->badge(),
                TextColumn::make('department.name')->label('Department'),
                TextColumn::make('assigned_at')->dateTime()->sortable(),
                TextColumn::make('revoked_at')->dateTime()->sortable()->placeholder('—'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('revoke')
                    ->label('Revoke')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (AdminRoleAssignment $record) => $record->revoked_at === null)
                    ->action(function (AdminRoleAssignment $record) {
                        $record->update(['revoked_at' => now()]);
                        AuditLog::record('role_assignment.revoked', $record, ['revoked_at' => null], ['revoked_at' => now()->toIso8601String()]);
                        Notification::make()->title('Role assignment revoked')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([]),
            ]);
    }
}
