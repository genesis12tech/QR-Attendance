<?php

namespace App\Models;

use App\Enums\ExportFormat;
use App\Enums\ExportStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionExport extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'requested_by',
        'format',
        'status',
        'file_path',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'format' => ExportFormat::class,
            'status' => ExportStatus::class,
            'expires_at' => 'datetime',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'session_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
