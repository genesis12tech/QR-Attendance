<?php

namespace App\Filament\SuperAdmin\Widgets;

use App\Models\AuditLog;
use Filament\Widgets\Widget;

class RecentAuditFeedWidget extends Widget
{
    protected string $view = 'filament.super-admin.widgets.recent-audit-feed-widget';

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '20s';

    public function getViewData(): array
    {
        return [
            'logs' => AuditLog::with('actor')->latest()->limit(10)->get(),
        ];
    }
}
