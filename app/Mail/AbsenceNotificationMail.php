<?php

namespace App\Mail;

use App\Models\Course;
use App\Models\Student;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AbsenceNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Student $student,
        public readonly Course $course,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Attendance Warning: {$this->course->name}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.absence-notification',
        );
    }
}
