<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AIModel extends Model
{
    protected $table = 'models';
    protected $fillable = [
        'name',
        'provider',
        'model_key',

    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_models','model_id', 'user_id')
        ->withPivot(['subscription_id',
                    'started_at',
                    'expires_at',
                    'status']);
    }

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'model_id');
    }
}
