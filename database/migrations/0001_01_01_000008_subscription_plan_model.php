<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plan_model', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_plan_id')
                ->constrained('subscription_plans')
                ->cascadeOnDelete();

            $table->foreignId('model_id')
                ->constrained('models')
                ->cascadeOnDelete();

            // Price charged to the user per token
            $table->decimal('input_price', 12, 8)->default(0);
            $table->decimal('output_price', 12, 8)->default(0);

            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->unique([
                'subscription_plan_id',
                'model_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_model');
    }
};
