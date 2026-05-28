<?php

namespace Database\Factories;

use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use App\Models\AttendanceSession;
use App\Models\SessionExport;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SessionExport>
 */
class SessionExportFactory extends Factory
{
    public function definition(): array
    {
        return [
            'session_id' => AttendanceSession::factory(),
            'requested_by' => User::factory(),
            'format' => ExportFormat::Pdf,
            'status' => ExportStatus::Pending,
            'file_path' => null,
            'expires_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExportStatus::Pending,
        ]);
    }

    public function ready(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExportStatus::Ready,
            'file_path' => 'reports/export_'.fake()->uuid().'.pdf',
            'expires_at' => now()->addDay(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ExportStatus::Failed,
        ]);
    }
}
