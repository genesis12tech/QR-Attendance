<?php

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AdminRoleAssignment;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\AuditLog;
use App\Models\ClassGroup;
use App\Models\Course;
use App\Models\Department;
use App\Models\Enrollment;
use App\Models\Faculty;
use App\Models\ProxyFlag;
use App\Models\SecurityPolicy;
use App\Models\Student;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\HasMedia;
use Tests\TestCase;

uses(TestCase::class, LazilyRefreshDatabase::class);

// ── User ──────────────────────────────────────────────────────────────────────

test('user role cast returns user role enum', function () {
    $user = User::factory()->create(['role' => 'admin']);
    expect($user->role)->toBeInstanceOf(UserRole::class)
        ->and($user->role)->toBe(UserRole::Admin);
});

test('user status cast returns user status enum', function () {
    $user = User::factory()->create(['status' => 'suspended']);
    expect($user->status)->toBeInstanceOf(UserStatus::class)
        ->and($user->status)->toBe(UserStatus::Suspended);
});

test('user has one student', function () {
    expect((new User)->student())->toBeInstanceOf(HasOne::class);
});

test('user has one faculty', function () {
    expect((new User)->faculty())->toBeInstanceOf(HasOne::class);
});

// ── Department ────────────────────────────────────────────────────────────────

test('department has many students', function () {
    expect((new Department)->students())->toBeInstanceOf(HasMany::class);
});

test('department has many faculty', function () {
    expect((new Department)->faculty())->toBeInstanceOf(HasMany::class);
});

test('department belongs to head faculty', function () {
    expect((new Department)->headFaculty())->toBeInstanceOf(BelongsTo::class);
});

// ── Student ───────────────────────────────────────────────────────────────────

test('student belongs to user and department', function () {
    expect((new Student)->user())->toBeInstanceOf(BelongsTo::class)
        ->and((new Student)->department())->toBeInstanceOf(BelongsTo::class);
});

test('student soft deletes', function () {
    $user = User::factory()->create();
    $dept = Department::create(['name' => 'CS', 'code' => 'CS', 'is_active' => true]);
    $student = Student::create([
        'user_id' => $user->id,
        'department_id' => $dept->id,
        'roll_no' => '2024001',
        'batch_year' => '2024',
        'status' => 'active',
    ]);

    $student->delete();

    expect(Student::withTrashed()->find($student->id))->not->toBeNull()
        ->and(Student::find($student->id))->toBeNull();
});

// ── Faculty ───────────────────────────────────────────────────────────────────

test('faculty belongs to user and department', function () {
    expect((new Faculty)->user())->toBeInstanceOf(BelongsTo::class)
        ->and((new Faculty)->department())->toBeInstanceOf(BelongsTo::class);
});

// ── Course ────────────────────────────────────────────────────────────────────

test('course soft deletes', function () {
    $dept = Department::create(['name' => 'CS', 'code' => 'CS', 'is_active' => true]);
    $course = Course::create([
        'department_id' => $dept->id,
        'code' => 'CS101',
        'name' => 'Intro to CS',
        'semester' => '1',
        'credits' => 3,
        'min_attendance_pct' => 75,
    ]);

    $course->delete();

    expect(Course::withTrashed()->find($course->id))->not->toBeNull()
        ->and(Course::find($course->id))->toBeNull();
});

// ── Enrollment ────────────────────────────────────────────────────────────────

test('enrollment unique student course constraint', function () {
    $dept = Department::create(['name' => 'CS', 'code' => 'CS', 'is_active' => true]);
    $user = User::factory()->create();
    $student = Student::create(['user_id' => $user->id, 'department_id' => $dept->id, 'roll_no' => '2024001', 'batch_year' => '2024', 'status' => 'active']);
    $course = Course::create(['department_id' => $dept->id, 'code' => 'CS101', 'name' => 'Intro', 'semester' => '1', 'credits' => 3, 'min_attendance_pct' => 75]);
    $group = ClassGroup::create(['course_id' => $course->id, 'name' => 'Group A']);

    Enrollment::create(['student_id' => $student->id, 'course_id' => $course->id, 'class_group_id' => $group->id, 'status' => 'active', 'enrolled_at' => now()]);

    expect(fn () => Enrollment::create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'class_group_id' => $group->id,
        'status' => 'active',
        'enrolled_at' => now(),
    ]))->toThrow(QueryException::class);
});

// ── AttendanceSession ─────────────────────────────────────────────────────────

test('attendance session uuid is auto generated on create', function () {
    $dept = Department::create(['name' => 'CS', 'code' => 'CS', 'is_active' => true]);
    $fUser = User::factory()->create();
    $faculty = Faculty::create(['user_id' => $fUser->id, 'department_id' => $dept->id, 'employee_code' => 'EMP001', 'status' => 'active']);
    $course = Course::create(['department_id' => $dept->id, 'code' => 'CS101', 'name' => 'Intro', 'semester' => '1', 'credits' => 3, 'min_attendance_pct' => 75]);
    $group = ClassGroup::create(['course_id' => $course->id, 'name' => 'Group A']);

    $session = AttendanceSession::create([
        'faculty_id' => $faculty->id,
        'course_id' => $course->id,
        'class_group_id' => $group->id,
        'status' => 'pending',
    ]);

    expect($session->uuid)->toBeString()->not->toBeEmpty();
});

test('attendance session belongs to faculty course and class group', function () {
    expect((new AttendanceSession)->faculty())->toBeInstanceOf(BelongsTo::class)
        ->and((new AttendanceSession)->course())->toBeInstanceOf(BelongsTo::class)
        ->and((new AttendanceSession)->classGroup())->toBeInstanceOf(BelongsTo::class);
});

// ── AttendanceRecord ──────────────────────────────────────────────────────────

test('attendance record belongs to session and student', function () {
    expect((new AttendanceRecord)->session())->toBeInstanceOf(BelongsTo::class)
        ->and((new AttendanceRecord)->student())->toBeInstanceOf(BelongsTo::class);
});

test('attendance record implements has media', function () {
    expect(new AttendanceRecord)->toBeInstanceOf(HasMedia::class);
});

// ── ProxyFlag ─────────────────────────────────────────────────────────────────

test('proxy flag implements has media', function () {
    expect(new ProxyFlag)->toBeInstanceOf(HasMedia::class);
});

test('proxy flag pending scope returns only pending rows', function () {
    $dept = Department::create(['name' => 'CS', 'code' => 'CS', 'is_active' => true]);
    $fUser = User::factory()->create();
    $faculty = Faculty::create(['user_id' => $fUser->id, 'department_id' => $dept->id, 'employee_code' => 'EMP001', 'status' => 'active']);
    $course = Course::create(['department_id' => $dept->id, 'code' => 'CS101', 'name' => 'Intro', 'semester' => '1', 'credits' => 3, 'min_attendance_pct' => 75]);
    $group = ClassGroup::create(['course_id' => $course->id, 'name' => 'Group A']);
    $session = AttendanceSession::create(['faculty_id' => $faculty->id, 'course_id' => $course->id, 'class_group_id' => $group->id, 'status' => 'pending']);
    $sUser = User::factory()->create();
    $student = Student::create(['user_id' => $sUser->id, 'department_id' => $dept->id, 'roll_no' => '2024001', 'batch_year' => '2024', 'status' => 'active']);
    $record = AttendanceRecord::create(['attendance_session_id' => $session->id, 'student_id' => $student->id, 'status' => 'pending_review', 'risk_score' => 0]);

    ProxyFlag::insert([
        ['attendance_record_id' => $record->id, 'severity' => 'medium', 'reason_code' => 'gps', 'risk_score' => 60, 'review_status' => 'pending',  'created_at' => now(), 'updated_at' => now()],
        ['attendance_record_id' => $record->id, 'severity' => 'low',    'reason_code' => 'gps', 'risk_score' => 20, 'review_status' => 'approved', 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(ProxyFlag::pending()->count())->toBe(1);
});

// ── SecurityPolicy ────────────────────────────────────────────────────────────

test('security policy active scope returns only active rows', function () {
    DB::table('security_policies')->insert([
        ['policy_name' => 'active_pol',   'qr_expiry_seconds' => 30, 'risk_auto_reject' => 80, 'risk_pending_review' => 50, 'late_threshold_mins' => 10, 'geofence_radius_m' => 50, 'device_binding_required' => 1, 'clock_skew_seconds' => 5, 'is_active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['policy_name' => 'inactive_pol', 'qr_expiry_seconds' => 30, 'risk_auto_reject' => 80, 'risk_pending_review' => 50, 'late_threshold_mins' => 10, 'geofence_radius_m' => 50, 'device_binding_required' => 1, 'clock_skew_seconds' => 5, 'is_active' => 0, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $active = SecurityPolicy::active()->get();
    expect($active)->toHaveCount(1)
        ->and($active->first()->policy_name)->toBe('active_pol');
});

// ── AdminRoleAssignment ───────────────────────────────────────────────────────

test('admin role assignment active scope excludes revoked', function () {
    $admin = User::factory()->create();
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    AdminRoleAssignment::insert([
        ['user_id' => $user1->id, 'assigned_by' => $admin->id, 'role' => 'admin', 'department_id' => null, 'assigned_at' => now(), 'revoked_at' => null,  'created_at' => now(), 'updated_at' => now()],
        ['user_id' => $user2->id, 'assigned_by' => $admin->id, 'role' => 'admin', 'department_id' => null, 'assigned_at' => now(), 'revoked_at' => now(), 'created_at' => now(), 'updated_at' => now()],
    ]);

    expect(AdminRoleAssignment::active()->count())->toBe(1);
});

// ── AuditLog ──────────────────────────────────────────────────────────────────

test('audit log record creates row with correct fields', function () {
    $user = User::factory()->create();
    $policy = SecurityPolicy::create([
        'policy_name' => 'test', 'qr_expiry_seconds' => 30, 'risk_auto_reject' => 80,
        'risk_pending_review' => 50, 'late_threshold_mins' => 10, 'geofence_radius_m' => 50,
        'device_binding_required' => true, 'clock_skew_seconds' => 5, 'is_active' => true,
    ]);

    $log = AuditLog::record('policy.updated', $policy, ['is_active' => false], ['is_active' => true], $user);

    expect($log->actor_id)->toBe($user->id)
        ->and($log->action)->toBe('policy.updated')
        ->and($log->entity_type)->toBe($policy->getMorphClass())
        ->and($log->entity_id)->toBe($policy->id)
        ->and($log->old_values)->toBe(['is_active' => false])
        ->and($log->new_values)->toBe(['is_active' => true]);
});

// ── SystemSetting ─────────────────────────────────────────────────────────────

test('system setting get returns value by key', function () {
    DB::table('system_settings')->insert(['key' => 'app_mode', 'value' => 'demo', 'created_at' => now(), 'updated_at' => now()]);
    expect(SystemSetting::get('app_mode'))->toBe('demo');
});

test('system setting get returns default when key missing', function () {
    expect(SystemSetting::get('nonexistent', 'fallback'))->toBe('fallback');
});

test('system setting set creates row when key does not exist', function () {
    SystemSetting::set('new_key', 'new_value');
    expect(SystemSetting::get('new_key'))->toBe('new_value');
});

test('system setting set updates row when key exists', function () {
    DB::table('system_settings')->insert(['key' => 'existing_key', 'value' => 'old', 'created_at' => now(), 'updated_at' => now()]);
    SystemSetting::set('existing_key', 'updated');
    expect(SystemSetting::get('existing_key'))->toBe('updated');
});
