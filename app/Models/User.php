<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    public const STATUS_OFF_DUTY = '勤務外';

    public const STATUS_WORKING = '出勤中';

    public const STATUS_ON_BREAK = '休憩中';

    public const STATUS_FINISHED = '退勤済';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'admin_status' => 'boolean',
    ];

    /**
     * ユーザーの勤怠記録。
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * ユーザーが行った修正申請。
     */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * 管理者かどうかを判定する。
     */
    public function isAdmin(): bool
    {
        return (bool) $this->admin_status;
    }

    /**
     * 今日の勤怠記録を取得する（休憩も読み込む）。
     */
    public function todayAttendanceRecord(): ?AttendanceRecord
    {
        return $this->attendanceRecords()
            ->with('breaks')
            ->whereDate('date', Carbon::today())
            ->first();
    }

    /**
     * 今日の勤怠状況から「勤務外 / 出勤中 / 休憩中 / 退勤済」を返す。
     */
    public function getAttendanceStatusAttribute(): string
    {
        $record = $this->todayAttendanceRecord();

        if ($record === null) {
            return self::STATUS_OFF_DUTY;
        }

        if ($record->clock_out !== null) {
            return self::STATUS_FINISHED;
        }

        return $record->openBreak() !== null ? self::STATUS_ON_BREAK : self::STATUS_WORKING;
    }
}
