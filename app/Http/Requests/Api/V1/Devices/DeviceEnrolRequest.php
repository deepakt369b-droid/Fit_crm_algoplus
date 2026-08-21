<?php

namespace App\Http\Requests\Api\V1\Devices;

use Illuminate\Foundation\Http\FormRequest;

class DeviceEnrolRequest extends FormRequest
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
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'external_user_id' => ['required', 'string', 'max:255'],
            'biometric_type' => ['required', 'string', 'in:face,fingerprint'],
            'finger_position' => ['nullable', 'string', 'max:50', 'required_if:biometric_type,fingerprint'],
            // Biometric enrolment is regulated personal data in most jurisdictions
            // (GDPR/DPDP special-category data): the member (or their guardian)
            // must have consented before the device is allowed to enrol them.
            'consent' => ['required', 'accepted'],
        ];
    }
}
