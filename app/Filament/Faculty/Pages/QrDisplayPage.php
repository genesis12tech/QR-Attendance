<?php

namespace App\Filament\Faculty\Pages;

use App\Enums\SessionStatus;
use App\Filament\Faculty\Resources\AttendanceSessions\Pages\ListAttendanceSessions;
use App\Jobs\FinalizeAttendanceSession;
use App\Jobs\GenerateAttendanceReport;
use App\Models\AttendanceSession;
use App\Models\SecurityPolicy;
use App\Services\QRChallengeService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class QrDisplayPage extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|\UnitEnum|null $navigationGroup = 'My Sessions';

    protected static ?string $navigationLabel = 'Live QR';

    protected static ?string $slug = 'qr-display';

    protected string $view = 'filament.faculty.pages.qr-display-page';

    public ?int $sessionId = null;

    public string $qrString = '';

    public int $expiresIn = 0;

    /** @var array<string, int> */
    public array $sessionStats = [];

    public bool $isActive = false;

    public function mount(): void
    {
        $facultyId = auth()->user()?->faculty?->id;

        $session = AttendanceSession::where('faculty_id', $facultyId)
            ->where('status', SessionStatus::Active)
            ->latest()
            ->first();

        if (! $session) {
            $this->redirect(ListAttendanceSessions::getUrl());

            return;
        }

        $this->sessionId = $session->id;
        $this->isActive = true;
        $this->refreshQr();
        $this->refreshStats();
    }

    public function refreshQr(): void
    {
        $session = AttendanceSession::find($this->sessionId);

        if (! $session) {
            return;
        }

        $policy = SecurityPolicy::getActive();
        $this->expiresIn = $policy?->qr_expiry_seconds ?? 30;
        $this->qrString = app(QRChallengeService::class)->generateForSession($session);
    }

    public function refreshStats(): void
    {
        $session = AttendanceSession::find($this->sessionId);

        if (! $session) {
            return;
        }

        $session->refresh();
        $this->sessionStats = [
            'total_enrolled' => $session->total_enrolled ?? 0,
            'total_present' => $session->total_present ?? 0,
            'total_late' => $session->total_late ?? 0,
            'total_absent' => $session->total_absent ?? 0,
        ];
    }

    /** @return array<string, string> */
    public function getListeners(): array
    {
        $session = AttendanceSession::find($this->sessionId);

        if (! $session) {
            return [];
        }

        return [
            "echo-private:session.{$session->uuid},AttendanceMarked" => 'refreshStats',
        ];
    }

    /** @return array<int, Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('close_session')
                ->label('Close Session')
                ->icon(Heroicon::OutlinedStopCircle)
                ->color('danger')
                ->requiresConfirmation()
                ->action(function (): void {
                    $affected = AttendanceSession::where('id', $this->sessionId)
                        ->where('status', SessionStatus::Active)
                        ->update(['status' => SessionStatus::Closed, 'closed_at' => now()]);

                    if (! $affected) {
                        $this->redirect(ListAttendanceSessions::getUrl());

                        return;
                    }

                    $session = AttendanceSession::find($this->sessionId);
                    FinalizeAttendanceSession::dispatch($session);

                    Notification::make()->title('Session closed')->success()->send();

                    $this->redirect(ListAttendanceSessions::getUrl());
                }),

            Action::make('pause_session')
                ->label('Pause Session')
                ->icon(Heroicon::OutlinedPause)
                ->color('warning')
                ->requiresConfirmation()
                ->action(function (): void {
                    $session = AttendanceSession::find($this->sessionId);

                    if (! $session) {
                        return;
                    }

                    $session->update(['status' => SessionStatus::Paused]);
                    $this->isActive = false;

                    Notification::make()->title('Session paused')->warning()->send();
                }),

            Action::make('force_refresh_qr')
                ->label('Force Refresh QR')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('gray')
                ->action(function (): void {
                    $this->refreshQr();

                    Notification::make()->title('QR refreshed')->success()->send();
                }),

            Action::make('export_summary')
                ->label('Export Summary')
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->schema([
                    Select::make('format')
                        ->options(['pdf' => 'PDF', 'csv' => 'CSV', 'xlsx' => 'XLSX'])
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $session = AttendanceSession::find($this->sessionId);

                    if (! $session) {
                        return;
                    }

                    GenerateAttendanceReport::dispatch(
                        type: 'course',
                        departmentId: $session->course?->department_id,
                        courseId: $session->course_id,
                        from: $session->started_at?->toDateString(),
                        to: now()->toDateString(),
                        format: $data['format'],
                        requestedBy: auth()->id(),
                    );

                    Notification::make()->title('Export queued')->success()->send();
                }),
        ];
    }
}
