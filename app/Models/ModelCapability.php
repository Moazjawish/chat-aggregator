<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModelCapability extends Model
{
    use HasFactory;

    protected $fillable = [
        'model_id',
        'key',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function model(): BelongsTo
    {
        return $this->belongsTo(
            AIModel::class,
            'model_id'
        );
    }
}
