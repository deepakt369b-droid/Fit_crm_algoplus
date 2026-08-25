@php
    $statePath = $getStatePath();
    $state = $getState();
    $isExistingPath = filled($state) && ! str_starts_with((string) $state, 'data:image');
    $existingUrl = $isExistingPath ? \Illuminate\Support\Facades\Storage::disk($getDisk())->url((string) $state) : null;
    $fieldId = $getId();
@endphp

<div
    x-data="{
        statePath: @js($statePath),
        preview: @js($existingUrl),
        hasCapture: @js($isExistingPath),
        stream: null,
        cameraOn: false,
        cameraError: false,
        cameraInsecure: false,

        async startCamera() {
            this.cameraError = false;
            this.cameraInsecure = false;
            // getUserMedia only exists in secure contexts (HTTPS, or
            // localhost). On plain-HTTP origins navigator.mediaDevices is
            // undefined and the call would throw a generic error — surface
            // the actionable HTTPS message instead.
            if (! window.isSecureContext || ! navigator.mediaDevices || ! navigator.mediaDevices.getUserMedia) {
                this.cameraInsecure = true;
                this.cameraError = true;
                return;
            }
            try {
                this.stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user' } });
                this.$refs.video.srcObject = this.stream;
                this.cameraOn = true;
            } catch (e) {
                this.cameraError = true;
                this.cameraOn = false;
            }
        },

        stopCamera() {
            if (this.stream) {
                this.stream.getTracks().forEach(track => track.stop());
                this.stream = null;
            }
            this.cameraOn = false;
        },

        capture() {
            const video = this.$refs.video;
            const canvas = this.$refs.canvas;
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            canvas.getContext('2d').drawImage(video, 0, 0);
            const dataUrl = canvas.toDataURL('image/jpeg', 0.9);
            this.preview = dataUrl;
            this.hasCapture = true;
            this.stopCamera();
            this.$wire.set(this.statePath, dataUrl);
        },

        retake() {
            this.preview = null;
            this.hasCapture = false;
            this.$wire.set(this.statePath, null);
            this.startCamera();
        },

        onFileSelected(event) {
            const file = event.target.files[0];
            if (! file) return;
            const reader = new FileReader();
            reader.onload = () => {
                this.preview = reader.result;
                this.hasCapture = true;
                this.$wire.set(this.statePath, reader.result);
            };
            reader.readAsDataURL(file);
        },
    }"
    x-init="if (! preview) startCamera()"
    x-on:beforeunload.window="stopCamera()"
    wire:key="{{ $fieldId }}-camera-capture"
    class="space-y-3"
>
    <div class="relative overflow-hidden rounded-lg border border-gray-300 dark:border-gray-600 bg-gray-100 dark:bg-gray-800" style="aspect-ratio: 4/3; max-width: 320px;">
        <video x-ref="video" x-show="cameraOn && !hasCapture" autoplay playsinline muted class="h-full w-full object-cover"></video>
        <canvas x-ref="canvas" class="hidden"></canvas>

        <img x-show="preview" x-bind:src="preview" class="h-full w-full object-cover" alt="{{ __('app.fields.photo') }}" />

        <div x-show="!cameraOn && !preview" class="absolute inset-0 flex items-center justify-center p-4 text-center text-sm text-gray-500 dark:text-gray-400">
            <span x-show="cameraInsecure">{{ __('app.devices.camera_requires_https') }}</span>
            <span x-show="cameraError && !cameraInsecure">{{ __('app.devices.camera_unavailable') }}</span>
            <span x-show="!cameraError">{{ __('app.devices.camera_starting') }}</span>
        </div>
    </div>

    <div class="flex flex-wrap gap-2">
        <x-filament::button type="button" size="sm" color="gray" x-show="cameraOn && !hasCapture" x-on:click="capture()">
            {{ __('app.devices.capture_photo') }}
        </x-filament::button>

        <x-filament::button type="button" size="sm" color="gray" x-show="hasCapture" x-on:click="retake()">
            {{ __('app.devices.retake_photo') }}
        </x-filament::button>

        <label class="fi-btn fi-btn-size-sm fi-color-gray fi-btn-outlined cursor-pointer inline-flex items-center gap-1 rounded-lg px-3 py-1.5 text-sm font-medium border border-gray-300 dark:border-gray-600">
            {{ __('app.devices.upload_instead') }}
            <input type="file" accept="image/*" capture="user" class="hidden" x-on:change="onFileSelected($event)" />
        </label>
    </div>
</div>
