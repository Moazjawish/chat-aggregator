<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $guarded = [];
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function models()
    {
        return $this->belongsToMany(AIModel::class,'subscription_plan_model','subscription_plan_id',
        'model_id' )->withPivot([
    'input_price',
    'output_price',
    'status',
])->withTimestamps();
    }

    public function features()
    {
        return $this->belongsToMany(Feature::class,'subscription_plan_feature')->withPivot('status')->withTimestamps();
    }
}
