<?php

use App\Models\Device;
use App\Models\Gym;
use App\Models\Member;
use App\Models\MemberDeviceIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('pairs a device with a valid, unexpired code and rejects a wrong one', function (): void {
    $gym = Gym::factory()->create();
    $device = Device::factory()->for($gym)->create(['status' => 'pending', 'paired_at' => null, 'last_seen_at' => null]);
    $code = $device->generatePairingCode();

    $this->postJson('/api/v1/devices/pair', [
        'pairing_code' => '000000' === $code ? '111111' : '000000',
        'serial' => 'SN-WRONG',
    ])->assertUnprocessable();

    $response = $this->postJson('/api/v1/devices/pair', [
        'pairing_code' => $code,
        'serial' => 'SN-12345',
    ])->assertCreated();

    expect($response->json('token'))->toBeString()->not->toBeEmpty();

    $device->refresh();
    expect($device->status)->toBe('paired')
        ->and($device->serial)->toBe('SN-12345')
        ->and($device->pairing_code_hash)->toBeNull();
});

it('rejects an expired pairing code', function (): void {
    $gym = Gym::factory()->create();
    $device = Device::factory()->for($gym)->create(['status' => 'pending', 'paired_at' => null]);
    $code = $device->generatePairingCode();

    $device->forceFill(['pairing_expires_at' => now()->subMinute()])->save();

    $this->postJson('/api/v1/devices/pair', [
        'pairing_code' => $code,
        'serial' => 'SN-12345',
    ])->assertUnprocessable();
});

it('records a check-in and is idempotent for the same event', function (): void {
    $gym = Gym::factory()->create();
    $device = Device::factory()->for($gym)->create();
    $member = Member::factory()->for($gym)->create();

    Sanctum::actingAs($device, ['attendance:write']);

    $payload = [
        'member_number' => $member->code,
        'direction' => 'in',
        'method' => 'face',
        'recognized_at' => '2026-08-21T09:00:00Z',
    ];

    $first = $this->postJson('/api/v1/attendance/check-in', $payload)->assertCreated();
    $second = $this->postJson('/api/v1/attendance/check-in', $payload)->assertCreated();

    expect($first->json('data.id'))->toBe($second->json('data.id'));
    expect(\App\Models\Attendance::query()->count())->toBe(1);
});

it('rejects a device token on a member-management endpoint', function (): void {
    $gym = Gym::factory()->create();
    $device = Device::factory()->for($gym)->create();

    Sanctum::actingAs($device, ['attendance:write']);

    $this->getJson('/api/v1/members')->assertForbidden();
});

it('lets a device enrol a member biometric identifier scoped to its own branch', function (): void {
    $gymA = Gym::factory()->create();
    $gymB = Gym::factory()->create();
    $device = Device::factory()->for($gymA)->create();
    $memberInA = Member::factory()->for($gymA)->create();
    $memberInB = Member::factory()->for($gymB)->create();

    Sanctum::actingAs($device, ['attendance:write']);

    $this->postJson('/api/v1/devices/enrol', [
        'member_id' => $memberInB->id,
        'external_user_id' => 'ext-1',
        'biometric_type' => 'face',
    ])->assertUnprocessable();

    $this->postJson('/api/v1/devices/enrol', [
        'member_id' => $memberInA->id,
        'external_user_id' => 'ext-1',
        'biometric_type' => 'face',
    ])->assertCreated();

    expect(MemberDeviceIdentifier::query()->where('member_id', $memberInA->id)->count())->toBe(1);
});
