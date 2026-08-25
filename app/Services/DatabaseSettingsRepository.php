<?php

namespace App\Services;

use App\Contracts\SettingsRepository;
use App\Contracts\TenantContext;
use App\Services\Concerns\NormalizesSettings;
use Illuminate\Support\Facades\DB;

/**
 * Database-backed settings repository — one row per branch (Gym).
 *
 * Used by multi-branch installs so each branch has its own gym_name, logo,
 * invoice prefix, tax rate, etc., and settings survive a Coolify redeploy
 * without depending on a mounted volume the way the JSON file does.
 */
class DatabaseSettingsRepository implements SettingsRepository
{
    use NormalizesSettings;

    private const EXAMPLE_SETTINGS_PATH = 'data/settingsData.json.example';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $cachedSettings = null;

    /**
     * @var array<string, mixed>|null
     */
    protected static ?array $testOverride = null;

    public function __construct(protected TenantContext $tenantContext) {}

    /**
     * @param  array<string, mixed>|null  $override
     */
    public function setTestOverride(?array $override): void
    {
        static::$testOverride = $override;
        $this->cachedSettings = null;
    }

    public function get(): array
    {
        if ($this->cachedSettings !== null) {
            return $this->cachedSettings;
        }

        if (static::$testOverride !== null) {
            return $this->cachedSettings = $this->normalize(static::$testOverride);
        }

        if (app()->runningUnitTests()) {
            // Unit tests get the bundled example settings UNLESS the branch
            // already has a persisted row (a seeded testing deployment does —
            // APP_ENV=testing there must NOT freeze every branch on the
            // example blob, or Settings saves silently no-op and per-branch
            // feature flags become impossible to turn on).
            $row = $this->row();

            if ($row !== null) {
                $settings = json_decode((string) $row->data, true);
                $settings = is_array($settings) ? $settings : [];

                return $this->cachedSettings = $this->normalize($settings);
            }

            return $this->cachedSettings = $this->normalize($this->exampleSettings());
        }

        $data = $this->row()?->data;
        $settings = $data ? json_decode((string) $data, true) : [];
        $settings = is_array($settings) ? $settings : [];

        return $this->cachedSettings = $this->normalize($settings);
    }

    public function put(array $settings): void
    {
        $normalized = $this->normalize($settings);

        // Mirror get(): persist when the request has a branch context (a
        // seeded testing deployment does); fall back to the in-memory
        // override only for true unit tests with no branch behind them.
        if (app()->runningUnitTests() && $this->tenantContext->gymId() === null) {
            static::$testOverride = $normalized;
            $this->cachedSettings = $normalized;

            return;
        }

        DB::table('gym_settings')->updateOrInsert(
            ['gym_id' => $this->tenantContext->gymId()],
            ['data' => json_encode($normalized), 'updated_at' => now(), 'created_at' => now()],
        );

        $this->cachedSettings = $normalized;
    }

    private function row(): ?object
    {
        return DB::table('gym_settings')
            ->where('gym_id', $this->tenantContext->gymId())
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function exampleSettings(): array
    {
        $path = storage_path(self::EXAMPLE_SETTINGS_PATH);

        if (! file_exists($path)) {
            return [];
        }

        $settings = json_decode((string) file_get_contents($path), true);

        return is_array($settings) ? $settings : [];
    }
}
