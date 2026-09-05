<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AIModel extends Model
{
    protected $table = 'models';
    protected $fillable = [
        'name',
        'provider',
        'model_key',    'status',
    ];

    public function subscriptionPlans()
    {
        return $this->belongsToMany(
            SubscriptionPlan::class,
            'subscription_plan_model',
            'model_id',
            'subscription_plan_id'
        )
        ->withPivot([
            'input_price',
            'output_price',
            'input_token_limit',
            'output_token_limit',
            'status',
        ])
        ->withTimestamps();
    }
    public function costs()
    {
        return $this->hasMany(ModelCost::class);
    }

    public function modelUsages()
    {
        return $this->hasMany(ModelUsage::class);
    }
    public function messages()
    {
        return $this->hasMany(
            Message::class,
            'model_id'
        );
    }

    public function capabilities(): HasMany
    {
        return $this->hasMany(
            ModelCapability::class,
            'model_id'
        );
    }

    public function activeCapabilities(): HasMany
    {
        return $this->capabilities()
            ->where('status', true);
    }

    public function hasCapability(string $key): bool
    {
        return $this->activeCapabilities()
            ->where('key', $key)
            ->exists();
    }


}

