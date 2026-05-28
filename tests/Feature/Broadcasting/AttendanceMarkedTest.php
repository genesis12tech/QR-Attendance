<?php

use App\Enums\AttendanceStatus;
use App\Events\AttendanceMarked;
use App\Models\AttendanceSession;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

test('event_broadcasts_on_correct_private_channel', function () {
    $session = AttendanceSession::factory()->create();

    $event = new AttendanceMarked(
        session: $session,
        studentName: 'Alice',
        status: AttendanceStatus::Present->value,
        riskScore: 0,
        markedAt: now()->toIso8601String(),
        sessionStats: ['total_present' => 1, 'total_enrolled' => 15],
    );

    $channel = $event->broadcastOn();

    expect($channel)->toBeInstanceOf(PrivateChannel::class);
    expect($channel->name)->toBe("private-session.{$session->uuid}");
});

test('event_payload_contains_required_fields', function () {
    $session = AttendanceSession::factory()->create();

    $event = new AttendanceMarked(
        session: $session,
        studentName: 'Bob',
        status: AttendanceStatus::Late->value,
        riskScore: 15,
        markedAt: now()->toIso8601String(),
        sessionStats: ['total_present' => 5, 'total_enrolled' => 20],
    );

    $payload = $event->broadcastWith();

    expect($payload)->toHaveKeys(['student_name', 'status', 'risk_score', 'marked_at', 'session_stats']);
    expect($payload['student_name'])->toBe('Bob');
    expect($payload['risk_score'])->toBe(15);
    expect($payload['session_stats']['total_enrolled'])->toBe(20);
});

test('faculty_can_listen_to_own_session_channel', function () {
    $facultyUser = User::factory()->faculty()->create();
    $faculty = Faculty::factory()->create(['user_id' => $facultyUser->id]);
    $session = AttendanceSession::factory()->create(['faculty_id' => $faculty->id]);

    $callback = app(BroadcastManager::class)
        ->driver()
        ->getChannels()
        ->get('session.{sessionUuid}');

    $result = $callback($facultyUser, $session->uuid);

    expect($result)->toBeTrue();
});

test('faculty_cannot_listen_to_another_facultys_session_channel', function () {
    $facultyUser = User::factory()->faculty()->create();
    $otherFaculty = Faculty::factory()->create();
    $session = AttendanceSession::factory()->create(['faculty_id' => $otherFaculty->id]);

    $callback = app(BroadcastManager::class)
        ->driver()
        ->getChannels()
        ->get('session.{sessionUuid}');

    $result = $callback($facultyUser, $session->uuid);

    expect($result)->toBeFalse();
});
