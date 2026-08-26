<?php

namespace App\Services;

/**
 * The outcome of a gate check-in evaluation: an explicit allow/deny with a
 * stable machine-readable reason code plus a human-facing message that
 * kiosk hardware can display directly at the gate.
 */
final class GateDecision
{
    private function __construct(
        private readonly bool $allowed,
        private readonly string $reason,
        private readonly string $message,
    ) {}

    public static function granted(): self
    {
        return new self(true, 'granted', 'Welcome in.');
    }

    public static function deny(string $reason, string $message): self
    {
        return new self(false, $reason, $message);
    }

    public function allowed(): bool
    {
        return $this->allowed;
    }

    public function reason(): string
    {
        return $this->reason;
    }

    /**
     * @return array{allowed: bool, reason: string, message: string}
     */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'reason' => $this->reason,
            'message' => $this->message,
        ];
    }
}
