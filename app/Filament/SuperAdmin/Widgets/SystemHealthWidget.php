<?php

namespace App\Filament\SuperAdmin\Widgets;

use App\Models\DataRetentionPolicy;
use App\Models\SecurityPolicy;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

class SystemHealthWidget extends Widget
{
    protected string $view = 'filament.super-admin.widgets.system-health-widget';

    protected int|string|array $columnSpan = 'full';

    public function getViewData(): array
    {
        $policy = SecurityPolicy::getActive();
        $lastRetentionRun = DataRetentionPolicy::whereNotNull('last_run_at')->max('last_run_at');
        $redisConnected = $this->checkRedisConnection();

        return [
            'policy' => $policy,
            'lastRetentionRun' => $lastRetentionRun,
            'redisConnected' => $redisConnected,
            'queueWorkerRunning' => true,
        ];
    }

    private function checkRedisConnection(): bool
    {
        try {
            Cache::store('redis')->put('_health_ping', 1, 1);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
