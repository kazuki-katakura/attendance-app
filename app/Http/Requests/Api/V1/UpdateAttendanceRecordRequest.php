<?php

namespace App\Http\Requests\Api\V1;

use App\Http\Requests\Concerns\AttendanceRecordApiMessages;
use App\Models\AttendanceRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAttendanceRecordRequest extends FormRequest
{
    use AttendanceRecordApiMessages;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * 勤怠更新APIのバリデーションルール。送信された項目だけを検証する（部分更新）。
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $attendanceRecord = $this->attendanceRecord();

        return [
            'date' => [
                'sometimes',
                'required',
                'date_format:Y-m-d',
                Rule::unique('attendance_records', 'date')
                    ->ignore($attendanceRecord->id)
                    ->where('user_id', $attendanceRecord->user_id),
            ],
            'clock_in' => ['sometimes', 'required', 'date_format:H:i:s'],
            'clock_out' => ['sometimes', 'nullable', 'date_format:H:i:s'],
            'comment' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }

    /**
     * 退勤時刻が出勤時刻より後か確認する。出勤時刻が送られていなければ登録済みの値と比べる。
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->hasAny(['clock_in', 'clock_out']) || blank($this->input('clock_out'))) {
                return;
            }

            $clockIn = $this->input('clock_in', $this->attendanceRecord()->clock_in);

            if ($this->input('clock_out') <= $clockIn) {
                $validator->errors()->add('clock_out', '退勤時刻は出勤時刻より後の時刻を指定してください。');
            }
        });
    }

    /**
     * ルートモデルバインディングで取得した更新対象の勤怠。
     */
    private function attendanceRecord(): AttendanceRecord
    {
        return $this->route('attendanceRecord');
    }
}
