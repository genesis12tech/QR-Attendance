Dear {{ $student->user->name }},

This is a reminder that your attendance in {{ $course->name }} has fallen below the required minimum of {{ $course->min_attendance_pct }}%.

Please ensure you attend upcoming classes to avoid academic penalties.

Regards,
{{ config('app.name') }}
