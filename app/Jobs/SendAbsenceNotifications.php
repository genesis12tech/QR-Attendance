<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAbsenceNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [1, 5, 10];

    /**
     * @param  array<int, int>  $studentIds
     */
    public function __construct(
        public readonly array $studentIds,
        public readonly int $courseId,
    ) {}

    public function handle(): void
    {
        // Phase 6.3 implementation
    }

    public function failed(\Throwable $exception): void
    {
        // Phase 6.3 implementation
    }
}
