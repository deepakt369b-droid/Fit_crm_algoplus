<?php

use App\Models\Gym;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('rejects creating a member without a photo', function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('Create:Member', 'web');

    $gym = Gym::factory()->create();
    $user = User::factory()->create(['gym_id' => $gym->id]);
    $user->givePermissionTo('Create:Member');

    Sanctum::actingAs($user);

    $this->postJson('/api/v1/members', [
        'name' => 'No Photo',
        'email' => 'no-photo@example.com',
        'contact' => '+1 5550000000',
        'gender' => 'male',
        'status' => 'active',
    ])->assertUnprocessable()->assertJsonValidationErrors('photo');
});

it('accepts creating a member with a photo', function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    Permission::findOrCreate('Create:Member', 'web');

    $gym = Gym::factory()->create();
    $user = User::factory()->create(['gym_id' => $gym->id]);
    $user->givePermissionTo('Create:Member');

    Sanctum::actingAs($user);

    $this->post('/api/v1/members', [
        'name' => 'Has Photo',
        'email' => 'has-photo@example.com',
        'contact' => '+1 5550000001',
        'gender' => 'male',
        'status' => 'active',
        'photo' => UploadedFile::fake()->image('face.jpg', 480, 480),
    ], ['Accept' => 'application/json'])->assertSuccessful();
});
