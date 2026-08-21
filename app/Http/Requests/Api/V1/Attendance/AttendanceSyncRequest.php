<?php

namespace App\Http\Requests\Api\V1\Attendance;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Batched replay of check-ins buffered by a device while it was offline.
 */
class AttendanceSyncRequest extends FormRequest
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
            'events' => ['required', 'array', 'min:1', 'max:500'],
            'events.*.device_user_id' => ['required_without:events.*.member_number', 'nullable', 'string', 'max:255'],
            'events.*.member_number' => ['required_without:events.*.device_user_id', 'nullable', 'string', 'max:255'],
            'events.*.direction' => ['nullable', 'string', 'in:in,out'],
            'events.*.method' => ['nullable', 'string', 'in:face,fingerprint,manual'],
            'events.*.confidence' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'events.*.recognized_at' => ['required', 'date'],
        ];
    }
}
