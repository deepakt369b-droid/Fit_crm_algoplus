<?php

namespace App\Filament\Resources\WhatsappBroadcasts\Pages;

use App\Filament\Resources\WhatsappBroadcasts\WhatsappBroadcastResource;
use App\Models\WhatsappBroadcast;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class ListWhatsappBroadcasts extends ListRecords
{
    protected static string $resource = WhatsappBroadcastResource::class;

    public static function canAccess(array $parameters = []): bool
    {
        // TEMPORARY diagnostic — remove after the Forbidden investigation.
        $user = auth()->user();
        error_log('WBC-debug ' . json_encode([
            'user' => $user?->email,
            'roles' => $user?->getRoleNames()?->toArray(),
            'resource_canAccess' => WhatsappBroadcastResource::canAccess(),
            'canViewAny' => WhatsappBroadcastResource::canViewAny(),
            'policy_class' => Gate::getPolicyFor(WhatsappBroadcast::class)::class ?? 'none',
            'gate_before_probe' => Gate::forUser($user)->check('wbc-debug-probe'),
            'direct_ability' => $user?->can('ViewAny:WhatsappBroadcast'),
        ]));

        return parent::canAccess($parameters);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('app.actions.new', ['resource' => WhatsappBroadcastResource::getModelLabel()]))
                ->modalWidth('lg')
                ->createAnother(false),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            __('app.navigation.groups.marketing'),
            WhatsappBroadcastResource::getNavigationLabel(),
        ];
    }
}
