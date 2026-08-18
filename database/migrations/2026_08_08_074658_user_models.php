<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
            Schema::create('user_models',function(Blueprint $table){

            $table->foreignId('user_id')->constrained();
            $table->foreignId('model_id')->constrained();
            $table->foreignId('subscription_id')->constrained();
            $table->timestamp('started_at');
            $table->timestamp('expires_at')->nullable();
            $table->enum('status', [
                'active',
                'expired',
                'cancelled',
            ])->default('active');
            $table->timestamps();
            $table->unique(['user_id', 'model_id']);


        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};


