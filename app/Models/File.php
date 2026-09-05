<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;


class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'conversation_id',
        'original_name',
        'path',
        'disk',
        'mime_type',
        'extension',
        'size',
        'status',
        'extracted_text',
        'processing_error',
    ];

    protected $casts = [
        'size' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'user_id'
        );
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(
            Conversation::class,
            'conversation_id'
        );
    }

    public function messages(): BelongsToMany
    {
        return $this->belongsToMany(
            Message::class,
            'message_file',
            'file_id',
            'message_id'
        )
        ->withTimestamps();
    }

}
