<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlanLimit extends Model
{
    protected $fillable = [ 'subscription_plan_id', 'input_token_limit', 'output_token_limit', ];
    public function subscriptionPlan()
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }
}
