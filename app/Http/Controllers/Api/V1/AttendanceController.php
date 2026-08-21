<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\Attendance\AttendanceCheckInRequest;
use App\Http\Requests\Api\V1\Attendance\AttendanceSyncRequest;
use App\Http\Resources\V1\AttendanceResource;
use App\Models\Attendance;
use App\Models\Device;
use App\Models\Member;
use App\Models\MemberDeviceIdentifier;
use App\Services\Api\QueryFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Attendance (gate check-in/check-out) endpoints, authenticated as a
 * paired Device with the 'attendance:write' Sanctum ability.
 */
class AttendanceController extends ApiController
{
    private const RESOURCE_KEY = 'attendance';

    /**
     * List attendance records for the current branch (staff-facing).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->requirePermission($request, 'ViewAny:Member');

        $query = Attendance::query();

        QueryFilters::applyIndexFilters($query, $request, self::RESOURCE_KEY);

        $perPage = QueryFilters::perPage($request->query('per_page'));

        return AttendanceResource::collection($query->paginate($perPage));
    }

    /**
     * A single real-time check-in/check-out event from a device.
     */
    public function checkIn(AttendanceCheckInRequest $request): JsonResponse
    {
        $device = $this->currentDevice($request);
        $data = $request->validated();

        $member = $this->resolveMember($device, $data['device_user_id'] ?? null, $data['member_number'] ?? null);

        if ($member === null) {
            throw ValidationException::withMessages([
                'device_user_id' => ['No member matches this identifier on this branch.'],
            ]);
        }

        $attendance = $this->recordEvent($device, $member, $data);

        return response()->json([
            'data' => (new AttendanceResource($attendance))->toArray($request),
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'status' => $member->status?->value,
            ],
        ], 201);
    }

    /**
     * Batched replay of events a device buffered while offline.
     */
    public function sync(AttendanceSyncRequest $request): JsonResponse
    {
        $device = $this->currentDevice($request);
        $events = $request->validated('events');

        $results = [];

        foreach ($events as $event) {
            $member = $this->resolveMember($device, $event['device_user_id'] ?? null, $event['member_number'] ?? null);

            if ($member === null) {
                $results[] = ['status' => 'skipped', 'reason' => 'member_not_found'];

                continue;
            }

            $attendance = $this->recordEvent($device, $member, $event);
            $results[] = ['status' => 'ok', 'id' => $attendance->id];
        }

        return response()->json(['results' => $results], 201);
    }

    private function resolveMember(Device $device, ?string $deviceUserId, ?string $memberNumber): ?Member
    {
        if (filled($deviceUserId)) {
            $identifier = MemberDeviceIdentifier::query()
                ->where('device_id', $device->id)
                ->where('external_user_id', $deviceUserId)
                ->first();

            if ($identifier !== null) {
                return Member::query()->where('gym_id', $device->gym_id)->find($identifier->member_id);
            }
        }

        if (filled($memberNumber)) {
            return Member::query()
                ->where('gym_id', $device->gym_id)
                ->where('code', $memberNumber)
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function recordEvent(Device $device, Member $member, array $data): Attendance
    {
        $recognizedAt = filled($data['recognized_at'] ?? null)
            ? (string) $data['recognized_at']
            : now()->toIso8601String();

        $direction = $data['direction'] ?? 'in';

        $dedupeHash = Attendance::makeDedupeHash($member->id, $device->id, $direction, $recognizedAt);

        return Attendance::query()->firstOrCreate(
            ['dedupe_hash' => $dedupeHash],
            [
                'gym_id' => $device->gym_id,
                'member_id' => $member->id,
                'device_id' => $device->id,
                'direction' => $direction,
                'method' => $data['method'] ?? $this->defaultMethodFor($device),
                'confidence' => $data['confidence'] ?? null,
                'recognized_at' => $recognizedAt,
                'source' => 'device',
            ],
        );
    }

    /**
     * A hybrid device's own `type` isn't a valid attendance `method` value,
     * so fall back to 'face' for it rather than writing an invalid enum
     * value when the request didn't say which modality was actually used.
     */
    private function defaultMethodFor(Device $device): string
    {
        return in_array($device->type, ['face', 'fingerprint'], true) ? $device->type : 'face';
    }

    private function currentDevice(Request $request): Device
    {
        $device = $request->user();

        abort_unless($device instanceof Device, 401);
        abort_unless($device->tokenCan('attendance:write'), 403);

        return $device;
    }
}
