<?php

namespace App\Models;

use Laravel\Cashier\Subscription as CashierSubscription;

class Subscription extends CashierSubscription
{
    protected $table = 'subscriptions';

    protected $fillable =
    [
        'user_id',
        'type',
        'stripe_id',
        'stripe_status',
        'stripe_price',
        'quantity',
        'trial_ends_at',
        'ends_at',
        'subscription_plan_id',
        'pending_subscription_plan_id',
    ];

    protected $casts = [
    'current_period_start' => 'datetime',
    'current_period_end' => 'datetime',
];


    public function subscriptionPlan()
    {
        return $this->belongsTo(
            SubscriptionPlan::class,
            'subscription_plan_id'
        );
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
    public function modelUsages()
    {
        return $this->hasMany(ModelUsage::class);
    }
    public function pendingSubscriptionPlan()
    {
        return $this->belongsTo( SubscriptionPlan::class, 'pending_subscription_plan_id' );
    }

}
