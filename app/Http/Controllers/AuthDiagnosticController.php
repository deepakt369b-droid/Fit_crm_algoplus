<?php

namespace App\Http\Controllers;

use App\Filament\Resources\WhatsappBroadcasts\WhatsappBroadcastResource;
use App\Models\User;
use App\Models\WhatsappBroadcast;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

/**
 * TEMPORARY diagnostic for the whatsapp Forbidden investigation.
 * Local-only deployment; removed immediately after the probe is read.
 */
class AuthDiagnosticController extends Controller
{
    public function __invoke(): JsonResponse
    {
        Auth::login(User::query()->where('email', 'test@example.com')->firstOrFail());
        $user = auth()->user();

        $probe = Gate::forUser($user)->inspect('wbc-debug-probe');
        $policy = Gate::getPolicyFor(WhatsappBroadcast::class);

        return response()->json([
            'user' => $user?->email,
            'roles' => $user?->getRoleNames()?->toArray(),
            'resource_canAccess' => WhatsappBroadcastResource::canAccess(),
            'canViewAny' => WhatsappBroadcastResource::canViewAny(),
            'policy_class' => $policy ? $policy::class : 'none',
            'gate_before_probe_allowed' => $probe->allowed(),
            'gate_before_probe_message' => $probe->message(),
            'direct_ability' => $user?->can('ViewAny:WhatsappBroadcast'),
            'member_ability' => $user?->can('ViewAny:Member'),
        ]);
    }
}
