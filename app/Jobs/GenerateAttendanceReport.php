<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateAttendanceReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [1, 5, 10];

    public function __construct(
        public readonly string $type,
        public readonly ?int $departmentId,
        public readonly ?int $courseId,
        public readonly ?string $from,
        public readonly ?string $to,
        public readonly string $format,
        public readonly int $requestedBy,
    ) {}

    public function handle(): void
    {
        // Phase 6.1 implementation
    }

    public function failed(\Throwable $exception): void
    {
        // Phase 6.1 implementation
    }
}
