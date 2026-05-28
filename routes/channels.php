<?php

use App\Enums\UserRole;
use App\Models\AttendanceSession;
use App\Models\Faculty;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('session.{sessionUuid}', function ($user, string $sessionUuid) {
    $session = AttendanceSession::where('uuid', $sessionUuid)->first();

    if (! $session) {
        return false;
    }

    if ($user->role === UserRole::SuperAdmin) {
        return true;
    }

    if ($user->role === UserRole::Faculty) {
        $faculty = Faculty::where('user_id', $user->id)->first();

        return $faculty && $session->faculty_id === $faculty->id;
    }

    if ($user->role === UserRole::Admin) {
        return $user->activeAdminAssignment?->department_id === $session->course->department_id;
    }

    return false;
});
