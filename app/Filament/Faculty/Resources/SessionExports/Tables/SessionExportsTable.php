<?php

namespace App\Filament\Faculty\Resources\SessionExports\Tables;

use App\Enums\ExportStatus;
use App\Models\SessionExport;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\URL;
use Livewire\Component;

class SessionExportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('session.course.code')
                    ->label('Course')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('format')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Requested')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Download')
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->action(function (SessionExport $record, Component $livewire): void {
                        if ($record->status !== ExportStatus::Ready) {
                            Notification::make()
                                ->title('Processing…')
                                ->body('Your export is still being generated. Please check back shortly.')
                                ->warning()
                                ->send();

                            return;
                        }

                        $livewire->redirect(
                            URL::temporarySignedRoute(
                                'session-exports.download',
                                now()->addMinutes(30),
                                ['export' => $record->id]
                            )
                        );
                    }),

                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No exports found')
            ->emptyStateDescription('Exports are created when you export a session report.');
    }
}
