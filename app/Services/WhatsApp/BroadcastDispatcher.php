<?php

namespace App\Services\WhatsApp;

use App\Jobs\SendWhatsappBroadcastBatch;
use App\Models\WhatsappBroadcast;
use App\Models\WhatsappContact;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Builds a broadcast's recipient list and starts sending.
 */
class BroadcastDispatcher
{
    /**
     * @param  Collection<int, WhatsappContact>  $contacts
     * @param  array<int, list<string>>  $variablesByContactId  Per-recipient
     *         template parameter values, keyed by contact id. Contacts
     *         without an entry get no parameters.
     */
    public function dispatch(WhatsappBroadcast $broadcast, Collection $contacts, array $variablesByContactId = []): void
    {
        $eligible = $contacts->filter(fn (WhatsappContact $contact): bool => $contact->opt_in_status !== 'opted_out');

        foreach ($eligible as $contact) {
            $broadcast->recipients()->firstOrCreate(
                ['wa_contact_id' => $contact->id],
                [
                    'status' => 'pending',
                    'variables' => $variablesByContactId[$contact->id] ?? null,
                ],
            );
        }

        $broadcast->forceFill([
            'total_recipients' => $broadcast->recipients()->count(),
            'status' => 'scheduled',
        ])->save();

        SendWhatsappBroadcastBatch::dispatch($broadcast->id);
    }

    /**
     * Default audience: every opted-in contact for the broadcast's branch
     * (or every branch, for a shared phone number).
     *
     * @return Builder<WhatsappContact>
     */
    public function optedInContactsQuery(WhatsappBroadcast $broadcast): Builder
    {
        return WhatsappContact::query()
            ->where('opt_in_status', 'opted_in')
            ->when(
                $broadcast->gym_id !== null,
                fn (Builder $query): Builder => $query->where('gym_id', $broadcast->gym_id),
            );
    }
}
