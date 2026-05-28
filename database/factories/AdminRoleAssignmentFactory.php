<?php

namespace Database\Factories;

use App\Enums\AdminAssignmentRole;
use App\Models\AdminRoleAssignment;
use App\Models\Department;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminRoleAssignment>
 */
class AdminRoleAssignmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'assigned_by' => User::factory(),
            'role' => AdminAssignmentRole::Admin,
            'department_id' => Department::factory(),
            'assigned_at' => now(),
            'revoked_at' => null,
        ];
    }
}
