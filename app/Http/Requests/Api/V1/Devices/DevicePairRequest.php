<?php

namespace App\Http\Requests\Api\V1\Devices;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Device pairing request — unauthenticated; the pairing code itself is
 * the credential (single-use, hashed at rest, 15-minute TTL).
 */
class DevicePairRequest extends FormRequest
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
            'pairing_code' => ['required', 'string', 'size:6'],
            'serial' => ['required', 'string', 'max:255'],
            'firmware_version' => ['nullable', 'string', 'max:100'],
        ];
    }
}
