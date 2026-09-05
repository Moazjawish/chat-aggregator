<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModelUsage extends Model
{
    use HasFactory;

    protected $table = 'model_usages';

    /*
    |--------------------------------------------------------------------------
    | Mass Assignment
    |--------------------------------------------------------------------------
    */

    protected $fillable = [
    'user_id',
    'subscription_id',
    'model_id',
    'input_tokens',
    'output_tokens',
    'total_provider_cost',
    'total_user_cost',
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */

    protected $casts = [
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',

        'total_provider_cost' => 'decimal:15',
        'total_user_cost' => 'decimal:15',
    ];

    /*
    |--------------------------------------------------------------------------
    | User
    |--------------------------------------------------------------------------
    |
    | Each usage record belongs to the user who made the request.
    |
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Subscription
    |--------------------------------------------------------------------------
    |
    | Each usage belongs to the subscription under which
    | the AI request was made.
    |
    */

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(
            Subscription::class,
            'subscription_id'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | AI Model
    |--------------------------------------------------------------------------
    |
    | The AI model used for this request.
    |
    */

    public function model(): BelongsTo
    {
        return $this->belongsTo(
            AIModel::class,
            'model_id'
        );
    }
}
