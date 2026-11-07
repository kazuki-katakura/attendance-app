<?php

namespace App\Http\Requests\Concerns;

use App\Models\AttendanceRecord;
use Illuminate\Validation\Validator;

/**
 * 勤怠詳細画面（一般ユーザーの修正申請・管理者の直接修正）で共通のバリデーション。
 */
trait AttendanceCorrectionRules
{
    private string $timePattern = '/^([01]?\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/';

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'new_clock_in' => ['required', 'regex:'.$this->timePattern],
            'new_clock_out' => ['required', 'regex:'.$this->timePattern],
            'new_break_in' => ['nullable', 'array'],
            'new_break_in.*' => ['nullable', 'regex:'.$this->timePattern],
            'new_break_out' => ['nullable', 'array'],
            'new_break_out.*' => ['nullable', 'regex:'.$this->timePattern],
            'comment' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'new_clock_in.required' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_in.regex' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_out.required' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_clock_out.regex' => '出勤時間もしくは退勤時間が不適切な値です',
            'new_break_in.*.regex' => '休憩時間が不適切な値です',
            'new_break_out.*.regex' => '休憩時間もしくは退勤時間が不適切な値です',
            'comment.required' => '備考を記入してください',
            'comment.max' => '備考は255文字以内で入力してください',
        ];
    }

    /**
     * 出勤・退勤・休憩の前後関係をチェックする。
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $errors = $validator->errors();
            if ($errors->hasAny(['new_clock_in', 'new_clock_out'])) {
                return;
            }

            $clockIn = $this->toMinutes((string) $this->input('new_clock_in'));
            $clockOut = $this->toMinutes((string) $this->input('new_clock_out'));

            if ($clockIn >= $clockOut) {
                $errors->add('new_clock_in', '出勤時間もしくは退勤時間が不適切な値です');

                return;
            }

            $breakIns = (array) $this->input('new_break_in', []);
            $breakOuts = (array) $this->input('new_break_out', []);

            collect(array_keys($breakIns + $breakOuts))
                ->reject(fn (int|string $index): bool => $errors->hasAny(["new_break_in.{$index}", "new_break_out.{$index}"]))
                ->each(function (int|string $index) use ($breakIns, $breakOuts, $clockIn, $clockOut, $errors): void {
                    $breakIn = $breakIns[$index] ?? null;
                    $breakOut = $breakOuts[$index] ?? null;

                    if (blank($breakIn) && blank($breakOut)) {
                        return;
                    }

                    if (blank($breakIn) || blank($breakOut)) {
                        $errors->add("new_break_in.{$index}", '休憩時間が不適切な値です');

                        return;
                    }

                    $start = $this->toMinutes($breakIn);
                    $end = $this->toMinutes($breakOut);

                    if ($start < $clockIn || $start > $clockOut || $start >= $end) {
                        $errors->add("new_break_in.{$index}", '休憩時間が不適切な値です');
                    }

                    if ($end > $clockOut) {
                        $errors->add("new_break_out.{$index}", '休憩時間もしくは退勤時間が不適切な値です');
                    }
                });
        });
    }

    /**
     * 入力欄に入っている休憩のうち、開始・終了の両方があるものを返す。
     *
     * @return array<int, array{break_in: string, break_out: string}>
     */
    public function breaks(): array
    {
        $breakIns = (array) $this->input('new_break_in', []);
        $breakOuts = (array) $this->input('new_break_out', []);

        return collect($breakIns)
            ->map(fn (?string $breakIn, int|string $index): array => [
                'break_in' => $breakIn,
                'break_out' => $breakOuts[$index] ?? null,
            ])
            ->filter(fn (array $break): bool => filled($break['break_in']) && filled($break['break_out']))
            ->map(fn (array $break): array => [
                'break_in' => $this->normalizeTime($break['break_in']),
                'break_out' => $this->normalizeTime($break['break_out']),
            ])
            ->values()
            ->all();
    }

    /**
     * 出勤・退勤・備考を、保存用の形に整えて返す。
     *
     * @return array{clock_in: string, clock_out: string, comment: string}
     */
    public function attendance(): array
    {
        return [
            'clock_in' => $this->normalizeTime((string) $this->input('new_clock_in')),
            'clock_out' => $this->normalizeTime((string) $this->input('new_clock_out')),
            'comment' => (string) $this->input('comment'),
        ];
    }

    private function toMinutes(string $time): int
    {
        [$hour, $minute] = array_map('intval', explode(':', $time));

        return $hour * 60 + $minute;
    }

    private function normalizeTime(string $time): string
    {
        return AttendanceRecord::minutesToTime($this->toMinutes($time));
    }
}
