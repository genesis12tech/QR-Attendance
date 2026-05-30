<?php

namespace App\Events;

use App\Models\AttendanceSession;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AttendanceMarked implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public bool $afterCommit = true;

    public function __construct(
        public readonly AttendanceSession $session,
        public readonly string $studentName,
        public readonly string $status,
        public readonly int $riskScore,
        public readonly string $markedAt,
        public readonly array $sessionStats,
    ) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("session.{$this->session->uuid}");
    }

    public function broadcastAs(): string
    {
        return 'AttendanceMarked';
    }

    public function broadcastWith(): array
    {
        return [
            'student_name' => $this->studentName,
            'status' => $this->status,
            'risk_score' => $this->riskScore,
            'marked_at' => $this->markedAt,
            'session_stats' => $this->sessionStats,
        ];
    }
}
