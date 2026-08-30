<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AIModel extends Model
{
    protected $table = 'models';
    protected $fillable = [
        'name',
        'provider',
        'model_key',    'status',
    ];

    public function subscriptionsPlan()
    {
        return $this->belongsToMany(SubscriptionPlan::class,'subscription_plan_model', 'model_id',
        'subscription_plan_id')->withPivot('status')->withTimestamps();
    }

    public function costs()
    {
        return $this->hasMany(ModelCost::class);
    }

}

