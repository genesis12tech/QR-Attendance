<?php

namespace App\Concerns;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

trait LogsToAudit
{
    public function logAudit(string $action, Model $entity, array $old = [], array $new = []): void
    {
        AuditLog::record($action, $entity, $old, $new);
    }
}
