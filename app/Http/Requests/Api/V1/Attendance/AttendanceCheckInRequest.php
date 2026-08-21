<?php

namespace App\Http\Requests\Api\V1\Attendance;

use Illuminate\Foundation\Http\FormRequest;

class AttendanceCheckInRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_user_id' => ['required_without:member_number', 'nullable', 'string', 'max:255'],
            'member_number' => ['required_without:device_user_id', 'nullable', 'string', 'max:255'],
            'direction' => ['nullable', 'string', 'in:in,out'],
            'method' => ['nullable', 'string', 'in:face,fingerprint,manual'],
            'confidence' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'recognized_at' => ['nullable', 'date'],
        ];
    }
}
