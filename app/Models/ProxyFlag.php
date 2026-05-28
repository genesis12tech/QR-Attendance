<?php

namespace App\Models;

use App\Enums\ProxySeverity;
use App\Enums\ReviewStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ProxyFlag extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    protected $fillable = [
        'attendance_record_id',
        'severity',
        'reason_code',
        'risk_score',
        'evidence_json',
        'review_status',
        'reviewer_id',
        'reviewer_notes',
        'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'severity' => ProxySeverity::class,
            'review_status' => ReviewStatus::class,
            'evidence_json' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('evidence_files');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('review_status', ReviewStatus::Pending);
    }

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
