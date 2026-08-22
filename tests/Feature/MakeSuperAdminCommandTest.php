<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MakeSuperAdminCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_an_invalid_email(): void
    {
        $this->artisan('fitcrm:make-super-admin', ['email' => 'not-an-email'])
            ->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_creates_the_super_admin_role_if_missing(): void
    {
        $this->assertDatabaseMissing('roles', ['name' => 'super_admin']);

        $this->artisan('fitcrm:make-super-admin', [
            'email' => 'owner@example.com',
            '--name' => 'Owner',
            '--password' => 'a-strong-password',
        ])->assertExitCode(0);

        $this->assertDatabaseHas('roles', ['name' => 'super_admin', 'guard_name' => 'web']);
    }

    public function test_it_creates_a_new_user_with_the_given_password_and_grants_super_admin(): void
    {
        $this->artisan('fitcrm:make-super-admin', [
            'email' => 'owner@example.com',
            '--name' => 'Owner',
            '--password' => 'a-strong-password',
        ])->assertExitCode(0);

        $user = User::query()->where('email', 'owner@example.com')->sole();

        $this->assertTrue($user->hasRole('super_admin'));
        $this->assertTrue(Hash::check('a-strong-password', $user->password));
        $this->assertSame('active', $user->status->value);
    }

    public function test_it_fails_to_create_a_new_user_without_a_name_when_not_interactive(): void
    {
        $this->artisan('fitcrm:make-super-admin', [
            'email' => 'owner@example.com',
            '--password' => 'a-strong-password',
        ])->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_rejects_a_password_shorter_than_the_minimum(): void
    {
        $this->artisan('fitcrm:make-super-admin', [
            'email' => 'owner@example.com',
            '--name' => 'Owner',
            '--password' => 'short',
        ])->assertExitCode(1);

        $this->assertDatabaseCount('users', 0);
    }

    public function test_it_promotes_an_existing_user_without_touching_their_password(): void
    {
        $user = User::factory()->create([
            'email' => 'existing@example.com',
            'password' => Hash::make('their-original-password'),
        ]);

        $this->artisan('fitcrm:make-super-admin', ['email' => 'existing@example.com'])
            ->assertExitCode(0);

        $user->refresh();

        $this->assertTrue($user->hasRole('super_admin'));
        $this->assertTrue(Hash::check('their-original-password', $user->password));
    }

    public function test_it_is_idempotent_for_a_user_who_is_already_super_admin(): void
    {
        Role::query()->create(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create(['email' => 'existing@example.com']);
        $user->assignRole('super_admin');

        $this->artisan('fitcrm:make-super-admin', ['email' => 'existing@example.com'])
            ->assertExitCode(0);

        $user->refresh();
        $this->assertCount(1, $user->roles);
    }

    public function test_it_updates_an_existing_users_password_when_one_is_given(): void
    {
        $user = User::factory()->create([
            'email' => 'existing@example.com',
            'password' => Hash::make('old-password'),
        ]);

        $this->artisan('fitcrm:make-super-admin', [
            'email' => 'existing@example.com',
            '--password' => 'brand-new-password',
        ])->assertExitCode(0);

        $user->refresh();
        $this->assertTrue(Hash::check('brand-new-password', $user->password));
    }
}
