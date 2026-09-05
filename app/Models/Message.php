<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'model_id',
        'role',
        'content',
        'input_tokens',
        'output_tokens',
        'provider_cost',
        'user_cost',
    ];

    protected $casts = [
        'input_tokens' => 'integer',
        'output_tokens' => 'integer',
        'provider_cost' => 'decimal:15',
        'user_cost' => 'decimal:15',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(
            Conversation::class,
            'conversation_id'
        );
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(
            AIModel::class,
            'model_id'
        );
    }

    public function files(): BelongsToMany
    {
        return $this->belongsToMany(
            File::class,
            'message_file',
            'message_id',
            'file_id'
        )
        ->withTimestamps();
    }

    }
