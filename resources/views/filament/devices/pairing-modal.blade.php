@php
    /** @var \App\Models\Device $device */
    /** @var string|null $code */
@endphp

<div class="space-y-4 text-center">
    @if ($device->status === 'paired')
        <div class="rounded-lg bg-success-50 dark:bg-success-950 p-4">
            <p class="text-lg font-semibold text-success-700 dark:text-success-300">
                {{ __('app.devices.paired_heading') }}
            </p>
            <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                {{ __('app.devices.paired_at', ['datetime' => optional($device->paired_at)->format('d M Y, H:i')]) }}
            </p>
        </div>
    @elseif ($code)
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ __('app.devices.pairing_instructions') }}
        </p>

        <div class="flex justify-center">
            <img
                src="{{ (new chillerlan\QRCode\QRCode())->render(json_encode([
                    'api_base_url' => config('app.url'),
                    'pairing_code' => $code,
                ])) }}"
                alt="{{ __('app.devices.pairing_qr_alt') }}"
                class="h-48 w-48"
            />
        </div>

        <p class="text-3xl font-mono font-bold tracking-widest">{{ $code }}</p>

        <p class="text-xs text-gray-500 dark:text-gray-400">
            {{ __('app.devices.pairing_expires', ['minutes' => 15]) }}
        </p>

        <p class="text-sm text-warning-600 dark:text-warning-400">
            {{ __('app.devices.pairing_waiting') }}
        </p>
    @else
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('app.devices.pairing_code_hidden') }}
        </p>
    @endif
</div>
