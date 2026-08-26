<?php

use App\Models\Attendance;
use App\Models\Device;
use App\Models\Gym;
use App\Models\Member;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-08-26 10:00:00'));

    $this->gym = Gym::factory()->create();
    $this->device = Device::factory()->for($this->gym)->create();
    $this->member = Member::factory()
        ->for($this->gym)
        ->create(['status' => 'active']);

    Sanctum::actingAs($this->device, ['attendance:write']);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function gateCheckIn(Member $member): mixed
{
    return test()->postJson('/api/v1/attendance/check-in', [
        'member_number' => $member->code,
        'direction' => 'in',
        'method' => 'face',
    ]);
}

it('allows a check-in for an active member whose subscription covers today', function (): void {
    Subscription::factory()->for($this->member)->create([
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-31',
        'status' => 'ongoing',
    ]);

    gateCheckIn($this->member)
        ->assertCreated()
        ->assertJsonPath('gate.allowed', true)
        ->assertJsonPath('gate.reason', 'granted')
        ->assertJsonPath('member.id', $this->member->id);

    expect(Attendance::query()->count())->toBe(1);
});

it('allows a check-in through an expired original plus an active renewal', function (): void {
    $original = Subscription::factory()->for($this->member)->create([
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'status' => 'renewed',
    ]);

    Subscription::factory()->for($this->member)->create([
        'renewed_from_subscription_id' => $original->getKey(),
        'start_date' => '2026-08-01',
        'end_date' => '2026-09-30',
        'status' => 'ongoing',
    ]);

    gateCheckIn($this->member)
        ->assertCreated()
        ->assertJsonPath('gate.allowed', true)
        ->assertJsonPath('gate.reason', 'granted');
});

it('denies an unknown member without recording attendance', function (): void {
    $this->postJson('/api/v1/attendance/check-in', [
        'member_number' => 'NOPE-404',
        'direction' => 'in',
    ])
        ->assertOk()
        ->assertJsonPath('gate.allowed', false)
        ->assertJsonPath('gate.reason', 'unknown_member')
        ->assertJsonPath('member', null);

    expect(Attendance::query()->count())->toBe(0);
});

it('denies a member from another branch as unknown_member', function (): void {
    $otherBranchMember = Member::factory()
        ->for(Gym::factory()->create())
        ->create(['status' => 'active']);

    gateCheckIn($otherBranchMember)
        ->assertOk()
        ->assertJsonPath('gate.allowed', false)
        ->assertJsonPath('gate.reason', 'unknown_member');

    expect(Attendance::query()->count())->toBe(0);
});

it('denies an inactive member', function (): void {
    $this->member->update(['status' => 'inactive']);

    gateCheckIn($this->member)
        ->assertOk()
        ->assertJsonPath('gate.allowed', false)
        ->assertJsonPath('gate.reason', 'member_inactive');

    expect(Attendance::query()->count())->toBe(0);
});

it('denies a member without any subscription', function (): void {
    gateCheckIn($this->member)
        ->assertOk()
        ->assertJsonPath('gate.allowed', false)
        ->assertJsonPath('gate.reason', 'no_subscription');

    expect(Attendance::query()->count())->toBe(0);
});

it('denies a member whose latest subscription expired', function (): void {
    Subscription::factory()->for($this->member)->create([
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-31',
        'status' => 'expired',
    ]);

    gateCheckIn($this->member)
        ->assertOk()
        ->assertJsonPath('gate.allowed', false)
        ->assertJsonPath('gate.reason', 'subscription_expired');

    expect(Attendance::query()->count())->toBe(0);
});

it('denies a member whose plan has not started yet', function (): void {
    Subscription::factory()->for($this->member)->create([
        'start_date' => '2026-09-01',
        'end_date' => '2026-09-30',
        'status' => 'upcoming',
    ]);

    gateCheckIn($this->member)
        ->assertOk()
        ->assertJsonPath('gate.allowed', false)
        ->assertJsonPath('gate.reason', 'subscription_not_started');

    expect(Attendance::query()->count())->toBe(0);
});

it('denies a cancelled subscription that covers today', function (): void {
    Subscription::factory()->for($this->member)->create([
        'start_date' => '2026-08-01',
        'end_date' => '2026-08-31',
        'status' => 'cancelled',
    ]);

    gateCheckIn($this->member)
        ->assertOk()
        ->assertJsonPath('gate.allowed', false)
        ->assertJsonPath('gate.reason', 'subscription_cancelled');

    expect(Attendance::query()->count())->toBe(0);
});

it('rejects every device endpoint for a revoked device even with a valid ability token', function (): void {
    $revoked = Device::factory()->for($this->gym)->create(['status' => 'revoked']);

    Sanctum::actingAs($revoked, ['attendance:write']);

    $this->postJson('/api/v1/attendance/check-in', [
        'member_number' => $this->member->code,
    ])->assertForbidden();

    $this->postJson('/api/v1/devices/heartbeat')->assertForbidden();
});

it('invalidates the previous token when a device pairs again', function (): void {
    $pending = Device::factory()
        ->for($this->gym)
        ->create(['status' => 'pending', 'paired_at' => null, 'last_seen_at' => null]);

    $firstCode = $pending->generatePairingCode();
    $firstToken = $this->postJson('/api/v1/devices/pair', [
        'pairing_code' => $firstCode,
        'serial' => 'SN-FIRST',
    ])->assertCreated()->json('token');

    $secondCode = $pending->generatePairingCode();
    $secondToken = $this->postJson('/api/v1/devices/pair', [
        'pairing_code' => $secondCode,
        'serial' => 'SN-SECOND',
    ])->assertCreated()->json('token');

    $this->postJson('/api/v1/devices/heartbeat', [], [
        'Authorization' => "Bearer {$firstToken}",
    ])->assertUnauthorized();

    $this->postJson('/api/v1/devices/heartbeat', [], [
        'Authorization' => "Bearer {$secondToken}",
    ])->assertOk();
});
