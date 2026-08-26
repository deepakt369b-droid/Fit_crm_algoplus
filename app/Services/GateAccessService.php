<?php

namespace App\Services;

use App\Enums\Status;
use App\Models\Device;
use App\Models\Member;
use App\Models\Subscription;
use App\Support\AppConfig;
use Illuminate\Support\Carbon;

/**
 * Server-side gate enforcement for attendance check-ins.
 *
 * A check-in is allowed only when the device is paired, the member resolves
 * on the device's branch and is active, and the member holds a subscription
 * whose date range covers today (and that has not been cancelled). Every
 * other case yields an explicit deny with a machine-readable reason:
 *
 *   device_revoked | unknown_member | member_inactive |
 *   no_subscription | subscription_expired | subscription_not_started |
 *   subscription_cancelled
 */
class GateAccessService
{
    public function evaluate(Device $device, ?Member $member): GateDecision
    {
        if ($device->status !== 'paired') {
            return GateDecision::deny('device_revoked', 'This device has been revoked.');
        }

        if ($member === null) {
            return GateDecision::deny('unknown_member', 'No member matches this identifier on this branch.');
        }

        if ($member->status === Status::Inactive) {
            return GateDecision::deny('member_inactive', 'Membership is deactivated. Please contact the front desk.');
        }

        return $this->evaluateSubscription($member);
    }

    private function evaluateSubscription(Member $member): GateDecision
    {
        $today = Carbon::today(AppConfig::timezone());

        $subscriptions = $member->subscriptions()
            ->whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->orderByDesc('end_date')
            ->get();

        if ($subscriptions->isEmpty()) {
            return GateDecision::deny('no_subscription', 'No subscription found. Please purchase a plan at the front desk.');
        }

        $covering = $subscriptions->filter(
            fn (Subscription $subscription) => $subscription->start_date->copy()->startOfDay()->lte($today)
                && $subscription->end_date->copy()->endOfDay()->gte($today),
        );

        $usable = $covering->first(
            fn (Subscription $subscription) => ! in_array($subscription->status, [Status::Cancelled, Status::Renewed], true),
        );

        if ($usable !== null) {
            return GateDecision::granted();
        }

        if ($covering->contains(fn (Subscription $subscription) => $subscription->status === Status::Cancelled)) {
            return GateDecision::deny('subscription_cancelled', 'Your subscription was cancelled. Please contact the front desk.');
        }

        $notStarted = $subscriptions->first(fn (Subscription $subscription) => $subscription->start_date->gt($today));

        if ($notStarted !== null) {
            return GateDecision::deny('subscription_not_started', "Your plan starts on {$notStarted->start_date->toDateString()}.");
        }

        $latest = $subscriptions->first();

        return GateDecision::deny('subscription_expired', "Your subscription expired on {$latest?->end_date?->toDateString()}. Please renew.");
    }
}
