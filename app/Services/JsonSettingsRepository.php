<?php

namespace App\Services;

use App\Contracts\SettingsRepository;
use App\Services\Concerns\NormalizesSettings;

/**
 * JSON-backed settings repository.
 *
 * Settings are stored under `storage/data/settingsData.json` — a single,
 * install-wide file. Kept for single-tenant/local use; multi-branch installs
 * use {@see DatabaseSettingsRepository} instead, since a shared file cannot
 * hold different settings per branch and does not survive a Coolify
 * redeploy without a mounted volume.
 */
class JsonSettingsRepository implements SettingsRepository
{
    use NormalizesSettings;

    private const SETTINGS_PATH = 'data/settingsData.json';

    private const EXAMPLE_SETTINGS_PATH = 'data/settingsData.json.example';

    /**
     * @var array<string, mixed>|null
     */
    private ?array $cachedSettings = null;

    /**
     * @var array<string, mixed>|null
     */
    protected static ?array $testOverride = null;

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
            $exampleFilePath = storage_path(self::EXAMPLE_SETTINGS_PATH);

            if (file_exists($exampleFilePath)) {
                $settings = json_decode((string) file_get_contents($exampleFilePath), true) ?? [];
                $settings = is_array($settings) ? $settings : [];

                return $this->cachedSettings = $this->normalize($settings);
            }

            return $this->cachedSettings = $this->normalize([]);
        }

        $filePath = storage_path(self::SETTINGS_PATH);

        if (! file_exists($filePath)) {
            $this->initializeFile($filePath);
        }

        $settings = json_decode((string) file_get_contents($filePath), true) ?? [];
        $settings = is_array($settings) ? $settings : [];

        return $this->cachedSettings = $this->normalize($settings);
    }

    public function put(array $settings): void
    {
        $normalized = $this->normalize($settings);

        if (app()->runningUnitTests()) {
            static::$testOverride = $normalized;
            $this->cachedSettings = $normalized;

            return;
        }

        $filePath = storage_path(self::SETTINGS_PATH);

        if (! file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        file_put_contents(
            $filePath,
            json_encode($normalized, JSON_PRETTY_PRINT),
        );

        $this->cachedSettings = $normalized;
    }

    private function initializeFile(string $filePath): void
    {
        $exampleFilePath = storage_path(self::EXAMPLE_SETTINGS_PATH);

        if (file_exists($exampleFilePath)) {
            copy($exampleFilePath, $filePath);

            return;
        }

        if (! file_exists(dirname($filePath))) {
            mkdir(dirname($filePath), 0755, true);
        }

        file_put_contents($filePath, json_encode([
            'general' => [],
            'invoice' => [],
            'member' => [],
            'charges' => [],
            'expenses' => [],
            'subscriptions' => [],
        ], JSON_PRETTY_PRINT));
    }
}
