<?php

use App\Jobs\SendAbsenceNotifications;
use App\Mail\AbsenceNotificationMail;
use App\Models\Course;
use App\Models\Student;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
});

it('sends an email to each student in the list', function () {
    $course = Course::factory()->create();
    $students = Student::factory()->count(3)->create();

    (new SendAbsenceNotifications(
        studentIds: $students->pluck('id')->all(),
        courseId: $course->id,
    ))->handle();

    Mail::assertSentCount(3);
    foreach ($students as $student) {
        Mail::assertSent(AbsenceNotificationMail::class, fn ($mail) => $mail->hasTo($student->user->email));
    }
});

it('includes the course name in the email', function () {
    $course = Course::factory()->create(['name' => 'Advanced Mathematics']);
    $student = Student::factory()->create();

    (new SendAbsenceNotifications(
        studentIds: [$student->id],
        courseId: $course->id,
    ))->handle();

    Mail::assertSent(AbsenceNotificationMail::class, function ($mail) {
        return $mail->course->name === 'Advanced Mathematics';
    });
});

it('skips missing student IDs gracefully without throwing', function () {
    $course = Course::factory()->create();
    $student = Student::factory()->create();

    expect(fn () => (new SendAbsenceNotifications(
        studentIds: [$student->id, 99999],
        courseId: $course->id,
    ))->handle())->not->toThrow(Exception::class);

    Mail::assertSentCount(1);
});
