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
        return $this->belongsToMany(
            AIModel::class,
            'subscription_plan_model',
            'subscription_plan_id',
            'model_id'
        )
        ->withPivot([
            'input_price',
            'output_price',
            'status',
            'input_token_limit',
            'output_token_limit',
        ])
        ->withTimestamps();
    }

    public function activeModels()
    {
        return $this->models()
            ->where('models.status', true)
            ->wherePivot('status', true);
    }

    public function features()
    {
    return $this->belongsToMany( Feature::class, 'subscription_plan_feature', 'subscription_plan_id', 'feature_id' )
    ->withPivot('status') ->withTimestamps();
    }
    public function activeFeatures()
    {
        return $this->features()->where('features.status', true) ->wherePivot('status', true);
    }
    public function hasFeature(string $key): bool
    {
        return $this->activeFeatures() ->where('features.key', $key) ->exists();
    }

    public function limits()
    {
        return $this->hasOne(
            SubscriptionPlanLimit::class
        );
    }
}
