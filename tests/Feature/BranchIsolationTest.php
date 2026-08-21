<?php

use App\Models\Gym;
use App\Models\Member;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

function actingAsBranchUser(Gym $gym, array $permissions): User
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $user = User::factory()->create(['gym_id' => $gym->id]);
    $user->givePermissionTo($permissions);

    Sanctum::actingAs($user);

    return $user;
}

it('does not let a branch admin read another branch\'s member by id', function (): void {
    $gymA = Gym::factory()->create();
    $gymB = Gym::factory()->create();

    $memberInB = Member::factory()->create(['gym_id' => $gymB->id]);

    actingAsBranchUser($gymA, ['ViewAny:Member', 'View:Member']);

    $this->getJson("/api/v1/members/{$memberInB->id}")
        ->assertNotFound();
});

it('scopes the members index to the acting user\'s own branch', function (): void {
    $gymA = Gym::factory()->create();
    $gymB = Gym::factory()->create();

    Member::factory()->count(2)->create(['gym_id' => $gymA->id]);
    Member::factory()->count(3)->create(['gym_id' => $gymB->id]);

    actingAsBranchUser($gymA, ['ViewAny:Member']);

    $response = $this->getJson('/api/v1/members')->assertSuccessful();

    expect($response->json('data'))->toHaveCount(2);
});

it('auto-fills gym_id from the acting user\'s branch when creating a member', function (): void {
    $gym = Gym::factory()->create();

    actingAsBranchUser($gym, ['Create:Member', 'ViewAny:Member']);

    $response = $this->postJson('/api/v1/members', [
        'name' => 'Alex',
        'email' => 'alex.branch-isolation@example.com',
        'contact' => '+91 9000000000',
        'gender' => 'male',
        'status' => 'active',
        'photo' => 'images/placeholder.png',
    ])->assertSuccessful();

    $member = Member::query()->withoutGlobalScopes()->findOrFail($response->json('data.id'));

    expect($member->gym_id)->toBe($gym->id);
});

it('lets two branches use the same member email independently', function (): void {
    $gymA = Gym::factory()->create();
    $gymB = Gym::factory()->create();

    Member::factory()->create(['gym_id' => $gymA->id, 'email' => 'shared@example.com']);

    // Creating a second member with the same email in a different branch
    // must not violate the (gym_id, email) composite unique constraint.
    Member::factory()->create(['gym_id' => $gymB->id, 'email' => 'shared@example.com']);

    expect(Member::query()->withoutGlobalScopes()->where('email', 'shared@example.com')->count())->toBe(2);
});
