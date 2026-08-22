<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Spatie\Permission\Models\Role;

/**
 * Creates (or promotes an existing) user to the super_admin role — the
 * one role with unrestricted access to every branch and the superadmin
 * panel (see User::canAccessPanel()/getTenants()/canAccessTenant() and
 * config/filament-shield.php's super_admin gate-before bypass).
 *
 * Exists because neither scripts/coolify-deploy.sh nor a fresh
 * production database ever runs ShieldSeeder/UserSeeder — those are
 * dev-only (composer setup-demo), and UserSeeder's own account
 * (test@example.com / "test") must never exist in production. This is
 * the safe, repeatable, non-interactive-friendly replacement: it never
 * needs a plaintext password to pass through a one-shot command runner
 * (e.g. Coolify's "Execute Command" panel, which has no TTY) unless
 * one is explicitly opted into via --password.
 */
class MakeSuperAdmin extends Command
{
    /**
     * @var string
     */
    protected $signature = 'fitcrm:make-super-admin
        {email : Email address of the user to create or promote}
        {--name= : Required when creating a new user}
        {--password= : Set this password directly instead of a random one + "forgot password" flow}';

    /**
     * @var string
     */
    protected $description = 'Create or promote a user to super_admin — the role with full cross-branch access';

    public function handle(): int
    {
        $email = (string) $this->argument('email');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("'{$email}' is not a valid email address.");

            return self::FAILURE;
        }

        $password = $this->option('password');

        if ($password !== null) {
            $errors = validator(['password' => $password], ['password' => [PasswordRule::min(8)]])->errors();

            if ($errors->isNotEmpty()) {
                $this->error($errors->first('password'));

                return self::FAILURE;
            }
        }

        Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            $name = $this->option('name');

            if (blank($name)) {
                if (! $this->input->isInteractive()) {
                    $this->error('No user exists with that email yet — pass --name= to create one (this command has no TTY to prompt with here).');

                    return self::FAILURE;
                }

                $name = $this->ask('Name for the new user');
            }

            if (blank($name)) {
                $this->error('A name is required to create a new user.');

                return self::FAILURE;
            }

            $generatedPassword = null;

            if ($password === null) {
                // Random and never logged/displayed - the user completes
                // setup via the panel's "Forgot password?" flow using
                // this email. Requires a real mail driver configured
                // (MAIL_MAILER=log by default just writes the reset
                // email to the log instead of delivering it - check
                // that first, or re-run with --password= instead).
                $generatedPassword = Str::password(32);
            }

            $user = User::query()->create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password ?? $generatedPassword),
                'status' => 'active',
            ]);

            $this->info("Created user '{$name}' <{$email}>.");
        } elseif ($password !== null) {
            $user->forceFill(['password' => Hash::make($password)])->save();
            $this->info("Updated password for existing user <{$email}>.");
        }

        if ($user->hasRole('super_admin')) {
            $this->info("<{$email}> already has the super_admin role — nothing to change there.");
        } else {
            $user->assignRole('super_admin');
            $this->info("Granted super_admin to <{$email}>.");
        }

        if ($password === null && $user->wasRecentlyCreated) {
            $this->newLine();
            $this->warn('No password was set — this account has a random one nobody knows.');
            $this->line("Use the panel's \"Forgot password?\" link with {$email} to set one, or re-run this command with --password= to set it directly.");
            $this->line('If MAIL_MAILER is still "log" (the .env.example default), the reset email will only be written to storage/logs, not actually delivered — configure real SMTP first, or use --password= instead.');
        } elseif ($password !== null) {
            $this->newLine();
            $this->warn('A password was set directly via --password= — it may be visible in shell history and any command-runner logs (e.g. Coolify\'s Execute Command panel). Consider changing it after first login.');
        }

        return self::SUCCESS;
    }
}
