<?php

use App\Enums\ExportFormat;
use App\Filament\Admin\Pages\ReportPage;
use App\Jobs\GenerateAttendanceReport;
use App\Models\AdminRoleAssignment;
use App\Models\Department;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function adminForReport(Department $department): User
{
    $admin = User::factory()->admin()->create();
    AdminRoleAssignment::factory()->create([
        'user_id' => $admin->id,
        'department_id' => $department->id,
        'revoked_at' => null,
    ]);

    return $admin;
}

test('admin_can_view_report_page', function () {
    $dept = Department::factory()->create();
    $admin = adminForReport($dept);

    $this->actingAs($admin)
        ->get('/admin/reports')
        ->assertSuccessful();
});

test('report_form_dispatches_generate_report_job', function () {
    Queue::fake();

    $dept = Department::factory()->create();
    $admin = adminForReport($dept);

    $this->actingAs($admin);

    livewire(ReportPage::class)
        ->fillForm([
            'type' => 'department',
            'department_id' => $dept->id,
            'from' => '2026-01-01',
            'to' => '2026-05-01',
            'format' => ExportFormat::Xlsx->value,
        ])
        ->call('generateReport')
        ->assertNotified();

    Queue::assertPushed(GenerateAttendanceReport::class, function ($job) use ($dept) {
        return $job->type === 'department'
            && $job->departmentId === $dept->id
            && $job->format === ExportFormat::Xlsx->value;
    });
});

test('report_form_requires_date_range', function () {
    $dept = Department::factory()->create();
    $admin = adminForReport($dept);

    $this->actingAs($admin);

    livewire(ReportPage::class)
        ->fillForm([
            'type' => 'department',
            'from' => null,
            'to' => null,
            'format' => ExportFormat::Pdf->value,
        ])
        ->call('generateReport')
        ->assertHasFormErrors(['from' => 'required', 'to' => 'required']);
});
