<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\AttendanceCorrectionRules;
use Illuminate\Foundation\Http\FormRequest;

class AdminAttendanceUpdateRequest extends FormRequest
{
    use AttendanceCorrectionRules;

    public function authorize(): bool
    {
        return true;
    }
}
