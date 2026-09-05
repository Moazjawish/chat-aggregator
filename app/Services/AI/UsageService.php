<?php
namespace App\Services\AI;

use App\Models\AIModel;
use App\Models\ModelUsage;
use App\Models\Subscription;
use RuntimeException;

class UsageService
{
    /**
     * Check whether the model can still be used
     * during the current subscription billing period.
     */
    public function canUse(
        Subscription $subscription,
        AIModel $model
    ): bool {
        $plan = $subscription->subscriptionPlan;

        if (! $plan) {
            return false;
        }

        /*
        |--------------------------------------------------------------------------
        | Get model configuration for current plan
        |--------------------------------------------------------------------------
        */

        $planModel = $plan
            ->activeModels()
            ->where('models.id', $model->id)
            ->first();

        if (! $planModel) {
            return false;
        }

        $inputLimit = (int) (
            $planModel->pivot->input_token_limit
            ?? 0
        );

        $outputLimit = (int) (
            $planModel->pivot->output_token_limit
            ?? 0
        );

        /*
        |--------------------------------------------------------------------------
        | Current billing period usage
        |--------------------------------------------------------------------------
        */

        $usage = $this->getUsage(
            subscription: $subscription,
            model: $model
        );

        /*
        |--------------------------------------------------------------------------
        | Limit interpretation
        |--------------------------------------------------------------------------
        |
        | 0 means unlimited.
        |
        */

        if (
            $inputLimit > 0 &&
            $usage['input']['used'] >= $inputLimit
        ) {
            return false;
        }

        if (
            $outputLimit > 0 &&
            $usage['output']['used'] >= $outputLimit
        ) {
            return false;
        }

        return true;
    }

    /**
     * Get usage for current billing period only.
     */
    public function getUsage(
        Subscription $subscription,
        AIModel $model
    ): array {
        $plan = $subscription->subscriptionPlan;

        if (! $plan) {
            throw new RuntimeException(
                'Subscription plan not found.'
            );
        }

        $planModel = $plan
            ->activeModels()
            ->where('models.id', $model->id)
            ->first();

        if (! $planModel) {
            throw new RuntimeException(
                'Model is not available in the current subscription plan.'
            );
        }

        $inputLimit = (int) (
            $planModel->pivot->input_token_limit
            ?? 0
        );

        $outputLimit = (int) (
            $planModel->pivot->output_token_limit
            ?? 0
        );

        /*
        |--------------------------------------------------------------------------
        | Base usage query
        |--------------------------------------------------------------------------
        */

        $query = ModelUsage::query()
            ->where(
                'subscription_id',
                $subscription->id
            )
            ->where(
                'model_id',
                $model->id
            );

        /*
        |--------------------------------------------------------------------------
        | Restrict to current billing period
        |--------------------------------------------------------------------------
        */

        if ($subscription->current_period_start) {
            $query->where(
                'created_at',
                '>=',
                $subscription->current_period_start
            );
        }

        if ($subscription->current_period_end) {
            $query->where(
                'created_at',
                '<',
                $subscription->current_period_end
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Aggregate usage
        |--------------------------------------------------------------------------
        */

        $totals = $query
            ->selectRaw(
                '
                COALESCE(SUM(input_tokens), 0) AS input_tokens,
                COALESCE(SUM(output_tokens), 0) AS output_tokens
                '
            )
            ->first();

        $inputUsed =
            (int) ($totals->input_tokens ?? 0);

        $outputUsed =
            (int) ($totals->output_tokens ?? 0);

        return [
            'period' => [
                'start' =>
                    $subscription->current_period_start,

                'end' =>
                    $subscription->current_period_end,
            ],

            'input' => [
                'used' =>
                    $inputUsed,

                'limit' =>
                    $inputLimit,

                'remaining' =>
                    $inputLimit > 0
                        ? max(
                            0,
                            $inputLimit - $inputUsed
                        )
                        : null,
            ],

            'output' => [
                'used' =>
                    $outputUsed,

                'limit' =>
                    $outputLimit,

                'remaining' =>
                    $outputLimit > 0
                        ? max(
                            0,
                            $outputLimit - $outputUsed
                        )
                        : null,
            ],
        ];
    }
}
