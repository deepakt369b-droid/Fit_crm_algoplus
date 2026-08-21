<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Devices\DeviceEnrolRequest;
use App\Http\Requests\Api\V1\Devices\DeviceHeartbeatRequest;
use App\Http\Requests\Api\V1\Devices\DevicePairRequest;
use App\Models\Device;
use App\Models\MemberDeviceIdentifier;
use App\Models\Member;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Device pairing and hardware-facing device endpoints.
 *
 * `pair` is unauthenticated — the pairing code itself is the one-time
 * credential. Every other action here authenticates as the Device itself
 * (a Sanctum tokenable, not a User), scoped to the 'attendance:write'
 * ability only, so a stolen device token cannot reach member or billing
 * endpoints (Device holds no Spatie permissions).
 */
class DevicesController extends ApiController
{
    /**
     * Exchange a pairing code for a long-lived, ability-scoped device token.
     *
     * @unauthenticated
     */
    public function pair(DevicePairRequest $request): JsonResponse
    {
        $data = $request->validated();

        /** @var Device|null $device */
        $device = Device::query()
            ->where('status', 'pending')
            ->where('pairing_code_hash', hash('sha256', (string) $data['pairing_code']))
            ->where('pairing_expires_at', '>', now())
            ->first();

        if ($device === null) {
            throw ValidationException::withMessages([
                'pairing_code' => ['This pairing code is invalid or has expired.'],
            ]);
        }

        $device->markPaired((string) $data['serial']);

        if (filled($data['firmware_version'] ?? null)) {
            $device->forceFill(['firmware_version' => $data['firmware_version']])->save();
        }

        $token = $device->createToken('device:'.$device->id, ['attendance:write'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'device' => [
                'id' => $device->id,
                'name' => $device->name,
                'type' => $device->type,
            ],
        ], 201);
    }

    /**
     * Device liveness ping — drives the online/offline indicator in the panel.
     */
    public function heartbeat(DeviceHeartbeatRequest $request): JsonResponse
    {
        $device = $this->currentDevice($request);

        $device->forceFill([
            'last_seen_at' => now(),
            'firmware_version' => $request->validated('firmware_version') ?? $device->firmware_version,
        ])->save();

        return response()->json(['status' => 'ok']);
    }

    /**
     * Record a member's biometric enrolment on this device.
     *
     * The biometric template itself stays on the device; only the mapping
     * (device's own identifier for that member) is stored here.
     */
    public function enrol(DeviceEnrolRequest $request): JsonResponse
    {
        $device = $this->currentDevice($request);
        $data = $request->validated();

        $member = Member::query()
            ->where('gym_id', $device->gym_id)
            ->find($data['member_id']);

        if ($member === null) {
            throw ValidationException::withMessages([
                'member_id' => ['This member does not belong to this device\'s branch.'],
            ]);
        }

        $identifier = MemberDeviceIdentifier::query()->updateOrCreate(
            [
                'device_id' => $device->id,
                'member_id' => $member->id,
                'biometric_type' => $data['biometric_type'],
                'finger_position' => $data['finger_position'] ?? null,
            ],
            [
                'gym_id' => $device->gym_id,
                'external_user_id' => $data['external_user_id'],
                'enrolled_at' => now(),
            ],
        );

        return response()->json(['data' => [
            'id' => $identifier->id,
            'member_id' => $identifier->member_id,
            'biometric_type' => $identifier->biometric_type,
            'enrolled_at' => $identifier->enrolled_at?->toISOString(),
        ]], 201);
    }

    /**
     * Resolve the authenticated Device for this request, or abort 401/403.
     */
    private function currentDevice(Request $request): Device
    {
        $device = $request->user();

        abort_unless($device instanceof Device, 401);
        abort_unless($device->tokenCan('attendance:write'), 403);

        return $device;
    }
}
