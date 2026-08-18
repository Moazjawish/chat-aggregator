<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Subscription extends Model
{
    protected $table = "subscriptions";
    protected $fillable = ['model_id','name'];
    public function model()
    {
        return $this->belongsTo(AIModel::class,'model_id');
    }
}
