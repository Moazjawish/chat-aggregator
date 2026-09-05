<?php
namespace App\Services;

use App\Models\User;

class FeatureService
{
    /**
     * Determine whether the user currently
     * has access to a feature.
     */
    public function has(User $user, string $featureKey): bool
    {
        $subscription = $user->subscription('default');
        if (! $subscription) {
            return false;
        }

        if (! $subscription->valid()) {
            return false;
        }

        /*
         * Important:
         *
         * This uses subscription_plan_id,
         * not pending_subscription_plan_id.
         *
         * So pending plans do not grant features
         * before payment succeeds.
         */
        $plan = $subscription->subscriptionPlan;

        if (! $plan) {
            return false;
        }

        return $plan->hasFeature(
            $featureKey
        );
    }
}
