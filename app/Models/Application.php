<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Application extends Model
{
    use HasFactory;

    public const STATUS_PENDING = '承認待ち';

    public const STATUS_APPROVED = '承認済み';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'attendance_record_id',
        'approval_status',
        'application_date',
        'new_date',
        'new_clock_in',
        'new_clock_out',
        'comment',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'application_date' => 'date:Y-m-d',
        'new_date' => 'date:Y-m-d',
    ];

    /**
     * 申請したユーザー。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 修正対象の勤怠記録。
     */
    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    /**
     * 申請された休憩（開始時刻順）。
     */
    public function proposalBreaks(): HasMany
    {
        return $this->hasMany(ProposalBreak::class)->orderBy('break_in');
    }

    /**
     * 承認待ちの申請に絞り込む。
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('approval_status', self::STATUS_PENDING);
    }

    /**
     * 承認済みかどうか。
     */
    public function isApproved(): bool
    {
        return $this->approval_status === self::STATUS_APPROVED;
    }
}
