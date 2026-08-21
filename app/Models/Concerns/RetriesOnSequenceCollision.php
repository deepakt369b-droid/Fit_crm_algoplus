<?php

namespace App\Models\Concerns;

use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Retries a save when two concurrent requests generate the same sequence
 * value (e.g. an invoice number or member code) and race to insert it.
 *
 * The sequence generator (see JsonSequenceRepository) computes the next
 * number by reading the current max — without a database lock, two
 * concurrent requests can compute the same "next" number. Rather than add
 * a lock (which would need every writer to go through one, including
 * ad-hoc scripts and future code), this catches the resulting unique
 * constraint violation on insert, clears the generated value so it is
 * recomputed, and retries a bounded number of times. A genuine duplicate
 * on a different column (email, etc.) is not this class's sequence
 * attribute, so it is rethrown immediately rather than retried forever.
 */
trait RetriesOnSequenceCollision
{
    /**
     * The attribute holding the generated sequence value, e.g. 'number' or 'code'.
     */
    abstract protected function sequenceAttribute(): string;

    /**
     * @param  array<string, mixed>  $options
     */
    public function save(array $options = []): bool
    {
        $attempts = 0;

        while (true) {
            try {
                return parent::save($options);
            } catch (UniqueConstraintViolationException $e) {
                $attempts++;

                if ($this->exists || $attempts >= 5 || ! $this->isSequenceCollision($e)) {
                    throw $e;
                }

                $this->setAttribute($this->sequenceAttribute(), null);
            }
        }
    }

    private function isSequenceCollision(UniqueConstraintViolationException $e): bool
    {
        return str_contains($e->getMessage(), $this->sequenceAttribute());
    }
}
