<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feature extends Model
{

    public function subscriptions_plans()
    {
        return $this->belongsToMany(SubscriptionPlan::class,'subscription_plan_feature')->withPivot('status')->withTimestamps();
    }


}
