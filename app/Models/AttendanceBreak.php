<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceBreak extends Model
{
    use HasFactory;

    /**
     * PHP の予約語 break を避けてモデル名を AttendanceBreak にし、テーブル名は breaks を使う。
     *
     * @var string
     */
    protected $table = 'breaks';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'attendance_record_id',
        'break_in',
        'break_out',
    ];

    /**
     * 休憩が属する勤怠記録。
     */
    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    /**
     * 休憩時間（分）。休憩戻の前は 0 を返す。
     */
    public function minutes(): int
    {
        if ($this->break_out === null) {
            return 0;
        }

        return max(0, AttendanceRecord::minutesBetween($this->break_in, $this->break_out));
    }
}
