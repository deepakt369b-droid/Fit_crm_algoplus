<?php

namespace App\Filament\Forms\Components;

use Filament\Forms\Components\Field;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A photo field that captures directly from the device camera (getUserMedia
 * + canvas), with a native file-picker fallback when no camera is available
 * or permission is denied. Both paths converge on the same base64 data URI
 * bound to this field's Livewire state; only a genuinely new capture (a
 * fresh data: URI) is decoded and written to storage on save — editing a
 * record without recapturing leaves its existing stored path untouched.
 *
 * The stored value is a path on the given disk/directory, identical in
 * shape to what Filament's own FileUpload stores, so existing code reading
 * `photo` (avatar URLs, infolists, the API) needs no changes.
 */
class CameraCapture extends Field
{
    protected string $view = 'filament.forms.components.camera-capture';

    protected string $disk = 'public';

    protected string $directory = 'images';

    /**
     * Minimum accepted capture width/height in pixels — enough for face
     * matching without rejecting a modest webcam.
     */
    protected int $minDimension = 200;

    public function disk(string $disk): static
    {
        $this->disk = $disk;

        return $this;
    }

    public function getDisk(): string
    {
        return $this->disk;
    }

    public function directory(string $directory): static
    {
        $this->directory = $directory;

        return $this;
    }

    public function getDirectory(): string
    {
        return $this->directory;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->dehydrateStateUsing(function (mixed $state): mixed {
            if (! is_string($state) || ! str_starts_with($state, 'data:image')) {
                return $state;
            }

            return $this->storeBase64Image($state);
        });
    }

    /**
     * Decode, validate, and store a captured data: URI. Returns null (and
     * lets the field's own `required()` validation catch it) if the
     * payload isn't a decodable image or is too small to be useful.
     */
    private function storeBase64Image(string $dataUri): ?string
    {
        if (! preg_match('/^data:image\/(png|jpe?g|webp);base64,(.+)$/i', $dataUri, $matches)) {
            return null;
        }

        $extension = strtolower($matches[1]) === 'jpg' ? 'jpeg' : strtolower($matches[1]);
        $binary = base64_decode($matches[2], true);

        if ($binary === false || $binary === '') {
            return null;
        }

        $info = @getimagesizefromstring($binary);

        if ($info === false || $info[0] < $this->minDimension || $info[1] < $this->minDimension) {
            return null;
        }

        $path = trim($this->getDirectory(), '/').'/'.Str::uuid()->toString().'.'.$extension;

        Storage::disk($this->getDisk())->put($path, $binary);

        return $path;
    }
}
