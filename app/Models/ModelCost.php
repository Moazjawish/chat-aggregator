<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModelCost extends Model
{
    protected $casts = [
        'input_cost' => 'decimal:8',
        'output_cost' => 'decimal:8',
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
    ];
    public function models()
    {
        return $this->belongsTo(AIModel::class);
    }
}
