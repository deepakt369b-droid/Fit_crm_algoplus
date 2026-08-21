<?php

namespace App\Services\Concerns;

/**
 * Fills in missing settings sections/keys with defaults.
 *
 * Shared by every SettingsRepository implementation so the shape returned
 * by get() is identical regardless of where settings are actually stored.
 */
trait NormalizesSettings
{
    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function normalize(array $settings): array
    {
        foreach ([
            'general',
            'invoice',
            'member',
            'charges',
            'expenses',
            'subscriptions',
            'payments',
            'notifications',
        ] as $key) {
            if (! array_key_exists($key, $settings) || ! is_array($settings[$key])) {
                $settings[$key] = [];
            }
        }

        /** @var array<string, mixed> $general */
        $general = $settings['general'];
        if (
            ! array_key_exists('locale', $general) ||
            (! is_string($general['locale']) && $general['locale'] !== null)
        ) {
            $general['locale'] = null;
        }
        $settings['general'] = $general;

        /** @var array<string, mixed> $notifications */
        $notifications = $settings['notifications'];
        if (
            ! array_key_exists('email', $notifications) ||
            ! is_array($notifications['email'])
        ) {
            $notifications['email'] = [];
        }
        $settings['notifications'] = $notifications;

        /** @var array<string, mixed> $emailSettings */
        $emailSettings = $settings['notifications']['email'];

        foreach ([
            'enabled' => false,
            'auto_send_invoice_issued' => false,
            'auto_send_payment_receipt' => false,
            'invoice_subject_template' => 'Invoice {invoice_number} - {status}',
            'receipt_subject_template' => 'Payment received - {invoice_number}',
        ] as $key => $default) {
            if (! array_key_exists($key, $emailSettings)) {
                $emailSettings[$key] = $default;
            }
        }
        $settings['notifications']['email'] = $emailSettings;

        /** @var array<string, mixed> $payments */
        $payments = $settings['payments'];
        if (
            ! array_key_exists('provider', $payments) ||
            ! is_string($payments['provider']) ||
            trim($payments['provider']) === ''
        ) {
            $payments['provider'] = 'stripe';
        }
        $settings['payments'] = $payments;

        return $settings;
    }
}
