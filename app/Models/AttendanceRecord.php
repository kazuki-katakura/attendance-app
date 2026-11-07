<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class AttendanceRecord extends Model
{
    use HasFactory;

    public const STANDARD_WORK_MINUTES = 480;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'date',
        'clock_in',
        'clock_out',
        'total_time',
        'total_break_time',
        'comment',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'user_id' => 'integer',
        'date' => 'date:Y-m-d',
    ];

    /**
     * 勤怠記録の持ち主。
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 勤怠記録の休憩（打刻順）。
     */
    public function breaks(): HasMany
    {
        return $this->hasMany(AttendanceBreak::class)->orderBy('break_in');
    }

    /**
     * 勤怠記録に対する修正申請。
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * 指定した年月（Y-m）の勤怠記録に絞り込む。
     */
    public function scopeInMonth(Builder $query, Carbon $month): Builder
    {
        return $query->whereBetween('date', [
            $month->copy()->startOfMonth()->toDateString(),
            $month->copy()->endOfMonth()->toDateString(),
        ]);
    }

    /**
     * 終了していない（休憩戻を押していない）休憩を返す。
     */
    public function openBreak(): ?AttendanceBreak
    {
        return $this->breaks->first(fn (AttendanceBreak $break): bool => $break->break_out === null);
    }

    /**
     * 承認待ちの修正申請を返す。なければ null。
     */
    public function pendingApplication(): ?Application
    {
        return $this->applications
            ->first(fn (Application $application): bool => $application->approval_status === Application::STATUS_PENDING);
    }

    /**
     * 休憩時間の合計（分）。終了していない休憩は含めない。
     */
    public function breakMinutes(): int
    {
        return $this->breaks->sum(fn (AttendanceBreak $break): int => $break->minutes());
    }

    /**
     * 労働時間（分）。退勤前は 0 を返す。
     */
    public function workMinutes(): int
    {
        if ($this->clock_out === null) {
            return 0;
        }

        return max(0, self::minutesBetween($this->clock_in, $this->clock_out) - $this->breakMinutes());
    }

    /**
     * 残業時間（分）。1日8時間を超えた分を残業とする。
     */
    public function overtimeMinutes(): int
    {
        return max(0, $this->workMinutes() - self::STANDARD_WORK_MINUTES);
    }

    /**
     * 休憩合計と労働時間合計を計算し直して保存する。
     */
    public function recalculateTotals(): self
    {
        $this->load('breaks');

        $this->total_break_time = $this->breaks->isEmpty() ? null : self::minutesToTime($this->breakMinutes());
        $this->total_time = $this->clock_out === null ? null : self::minutesToTime($this->workMinutes());
        $this->save();

        return $this;
    }

    /**
     * 2つの時刻（H:i または H:i:s）の差を分で返す。
     */
    public static function minutesBetween(string $from, string $to): int
    {
        return (int) Carbon::parse($from)->diffInMinutes(Carbon::parse($to), false);
    }

    /**
     * 分を H:i:s 形式の時刻文字列に変換する。
     */
    public static function minutesToTime(int $minutes): string
    {
        return sprintf('%02d:%02d:00', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * 時刻文字列を H:i 形式に整える。null や空文字は空文字にする。
     */
    public static function formatTime(?string $time): string
    {
        return $time ? Carbon::parse($time)->format('H:i') : '';
    }
}
