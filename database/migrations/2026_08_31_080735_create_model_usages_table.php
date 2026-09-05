<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_usages', function (Blueprint $table) {

            $table->id();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('subscription_id')
                ->constrained('subscriptions')
                ->cascadeOnDelete();
            $table->foreignId('model_id')
                ->constrained('models')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('input_tokens')->default(0);
            $table->unsignedBigInteger('output_tokens')->default(0);
            $table->decimal('total_provider_cost', 20, 15)->default(0);
            $table->decimal('total_user_cost', 20, 15)->default(0);
            $table->timestamps();
            $table->index([
                'user_id',
                'subscription_id',
            ]);
            $table->index([
                'model_id',
                'created_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_usages');
    }
};
