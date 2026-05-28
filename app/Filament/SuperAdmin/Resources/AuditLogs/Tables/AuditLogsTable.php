<?php

namespace App\Filament\SuperAdmin\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('actor.name')->label('Actor')->default('System')->searchable(),
                TextColumn::make('actor_role')->badge()->sortable(),
                TextColumn::make('action')->sortable()->searchable(),
                TextColumn::make('entity_type')->sortable(),
                TextColumn::make('entity_id')->sortable(),
                TextColumn::make('ip_address'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('action')
                    ->options(fn () => AuditLog::distinct()->pluck('action', 'action')),
                SelectFilter::make('actor_role')
                    ->options(fn () => AuditLog::distinct()->pluck('actor_role', 'actor_role')->filter()->toArray()),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('from')->label('From'),
                        DatePicker::make('until')->label('Until'),
                    ])
                    ->query(function (Builder $query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('created_at', '<=', $data['until']));
                    }),
            ])
            ->recordActions([
                Action::make('view')
                    ->label('View Changes')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalContent(fn (AuditLog $record) => new HtmlString(
                        '<div class="space-y-2">'
                        .'<p class="font-semibold">Old Values</p>'
                        .'<pre class="text-xs bg-gray-50 p-2 rounded">'.e(json_encode($record->old_values, JSON_PRETTY_PRINT)).'</pre>'
                        .'<p class="font-semibold mt-2">New Values</p>'
                        .'<pre class="text-xs bg-gray-50 p-2 rounded">'.e(json_encode($record->new_values, JSON_PRETTY_PRINT)).'</pre>'
                        .'</div>'
                    ))
                    ->modalSubmitAction(false),
            ])
            ->toolbarActions([])
            ->defaultPaginationPageOption(25);
    }
}
