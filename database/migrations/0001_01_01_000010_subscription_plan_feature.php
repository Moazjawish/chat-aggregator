<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plan_feature', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_plan_id')
                ->constrained('subscription_plans')
                ->cascadeOnDelete();

            $table->foreignId('feature_id')
                ->constrained('features')
                ->cascadeOnDelete();

            $table->boolean('status')->default(true);

            $table->timestamps();

            $table->unique([
                'subscription_plan_id',
                'feature_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_feature');
    }
};
