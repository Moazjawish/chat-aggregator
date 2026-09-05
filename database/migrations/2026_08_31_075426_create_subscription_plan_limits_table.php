<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plan_limits', function (Blueprint $table) {
            $table->id();

            $table->foreignId('subscription_plan_id')
                ->constrained('subscription_plans')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('input_token_limit')
                ->default(0);
            $table->unsignedBigInteger('output_token_limit')
                ->default(0);
            $table->timestamps();
            $table->unique('subscription_plan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plan_limits');
    }
};
