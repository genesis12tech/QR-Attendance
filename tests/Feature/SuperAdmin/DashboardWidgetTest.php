<?php

use App\Filament\SuperAdmin\Widgets\RecentAuditFeedWidget;
use App\Filament\SuperAdmin\Widgets\SuperAdminStatsOverviewWidget;
use App\Filament\SuperAdmin\Widgets\SystemHealthWidget;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\ProxyFlag;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

use function Pest\Livewire\livewire;

uses(TestCase::class, LazilyRefreshDatabase::class);

beforeEach(function () {
    Cache::forget('stat.total_users');
    Cache::forget('stat.active_sessions');
    Cache::forget('stat.open_proxy_flags');
    Cache::forget('stat.departments');
});

test('stats_overview_widget_renders_for_super_admin', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin);

    livewire(SuperAdminStatsOverviewWidget::class)
        ->assertSuccessful();
});

test('stats_overview_shows_correct_user_count', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    User::factory()->admin()->count(2)->create();

    $this->actingAs($superAdmin);

    livewire(SuperAdminStatsOverviewWidget::class)
        ->assertSeeHtml((string) User::count());
});

test('stats_overview_shows_correct_active_session_count', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    AttendanceSession::factory()->active()->count(2)->create();

    $this->actingAs($superAdmin);

    livewire(SuperAdminStatsOverviewWidget::class)
        ->assertSeeHtml('2');
});

test('stats_overview_shows_correct_pending_proxy_flag_count', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    ProxyFlag::factory()->pending()->count(3)->create();

    $this->actingAs($superAdmin);

    livewire(SuperAdminStatsOverviewWidget::class)
        ->assertSeeHtml('3');
});

test('system_health_widget_renders', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin);

    livewire(SystemHealthWidget::class)
        ->assertSuccessful();
});

test('recent_audit_feed_widget_renders', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    AuditLog::factory()->count(5)->create();

    $this->actingAs($superAdmin);

    livewire(RecentAuditFeedWidget::class)
        ->assertSuccessful();
});
