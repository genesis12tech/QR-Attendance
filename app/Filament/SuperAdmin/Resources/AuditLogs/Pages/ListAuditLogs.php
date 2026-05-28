<?php

namespace App\Filament\SuperAdmin\Resources\AuditLogs\Pages;

use App\Filament\SuperAdmin\Resources\AuditLogs\AuditLogResource;
use App\Models\AuditLog;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_csv')
                ->label('Export CSV')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->action(function () {
                    $logs = AuditLog::with('actor')->get();
                    $handle = fopen('php://temp', 'r+');
                    fputcsv($handle, ['Actor', 'Role', 'Action', 'Entity Type', 'Entity ID', 'IP', 'Created At']);
                    foreach ($logs as $log) {
                        fputcsv($handle, [
                            $log->actor?->name ?? 'System',
                            $log->actor_role ?? '',
                            $log->action,
                            $log->entity_type,
                            $log->entity_id,
                            $log->ip_address ?? '',
                            $log->created_at,
                        ]);
                    }
                    rewind($handle);
                    $csv = stream_get_contents($handle);
                    fclose($handle);

                    return response()->streamDownload(fn () => print ($csv), 'audit-logs.csv');
                }),
        ];
    }
}
