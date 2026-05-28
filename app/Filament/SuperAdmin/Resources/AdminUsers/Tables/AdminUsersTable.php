<?php

namespace App\Filament\SuperAdmin\Resources\AdminUsers\Tables;

use App\Enums\UserStatus;
use App\Models\AdminRoleAssignment;
use App\Models\AuditLog;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class AdminUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->sortable()->searchable(),
                TextColumn::make('email')->sortable()->searchable(),
                TextColumn::make('role')->badge()->sortable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('last_login_at')->dateTime()->sortable(),
                TextColumn::make('activeAdminAssignment.department.name')
                    ->label('Department')
                    ->default('—'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('suspend')
                    ->label('Suspend')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (User $record) => $record->status !== UserStatus::Suspended)
                    ->action(function (User $record) {
                        $old = ['status' => $record->status->value];
                        $record->update(['status' => UserStatus::Suspended]);
                        AuditLog::record('user.suspended', $record, $old, ['status' => UserStatus::Suspended->value]);
                        Notification::make()->title('User suspended')->success()->send();
                    }),
                Action::make('revokeRole')
                    ->label('Revoke Role')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->action(function (User $record) {
                        AdminRoleAssignment::where('user_id', $record->id)
                            ->active()
                            ->update(['revoked_at' => now()]);
                        AuditLog::record('user.role_revoked', $record, ['role' => $record->role->value], []);
                        Notification::make()->title('Role revoked')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('suspend')
                        ->label('Suspend selected')
                        ->requiresConfirmation()
                        ->action(function (Collection $records) {
                            $records->each(function (User $user) {
                                $old = ['status' => $user->status->value];
                                $user->update(['status' => UserStatus::Suspended]);
                                AuditLog::record('user.suspended', $user, $old, ['status' => UserStatus::Suspended->value]);
                            });
                            Notification::make()->title('Users suspended')->success()->send();
                        }),
                    BulkAction::make('export_csv')
                        ->label('Export CSV')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->action(function (Collection $records) {
                            $csv = "Name,Email,Role,Status\n";
                            foreach ($records as $record) {
                                $csv .= sprintf(
                                    "%s,%s,%s,%s\n",
                                    $record->name,
                                    $record->email,
                                    $record->role->value,
                                    $record->status->value,
                                );
                            }

                            return response()->streamDownload(fn () => print ($csv), 'admin-users.csv');
                        }),
                ]),
            ]);
    }
}
