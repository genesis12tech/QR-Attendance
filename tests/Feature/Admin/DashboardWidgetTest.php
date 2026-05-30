<?php

use App\Filament\Admin\Widgets\ActiveSessionsTableWidget;
use App\Filament\Admin\Widgets\AdminStatsOverviewWidget;
use App\Filament\Admin\Widgets\ProxyFlagAlertWidget;
use App\Models\AdminRoleAssignment;
use App\Models\Department;
use App\Models\ProxyFlag;
use App\Models\Student;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

function adminWithDepartment(): array
{
    $dept = Department::factory()->create();
    $admin = User::factory()->admin()->create();
    AdminRoleAssignment::factory()->create([
        'user_id' => $admin->id,
        'department_id' => $dept->id,
        'revoked_at' => null,
    ]);

    return [$admin, $dept];
}

test('stats_overview_widget_renders_for_admin', function () {
    [$admin] = adminWithDepartment();
    $this->actingAs($admin);

    livewire(AdminStatsOverviewWidget::class)
        ->assertSuccessful();
});

test('stats_are_scoped_to_admin_department', function () {
    [$admin, $dept] = adminWithDepartment();
    $otherDept = Department::factory()->create();

    Student::factory()->count(3)->create(['department_id' => $dept->id]);
    Student::factory()->count(2)->create(['department_id' => $otherDept->id]);

    $this->actingAs($admin);

    livewire(AdminStatsOverviewWidget::class)
        ->assertSeeHtml('3');
});

test('active_sessions_widget_renders', function () {
    [$admin] = adminWithDepartment();
    $this->actingAs($admin);

    livewire(ActiveSessionsTableWidget::class)
        ->assertSuccessful();
});

test('proxy_flag_alert_widget_renders', function () {
    [$admin] = adminWithDepartment();
    ProxyFlag::factory()->pending()->count(3)->create();
    $this->actingAs($admin);

    livewire(ProxyFlagAlertWidget::class)
        ->assertSuccessful();
});
