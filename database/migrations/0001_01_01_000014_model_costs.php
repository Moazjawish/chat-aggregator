<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_costs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('model_id')
                ->constrained('models')
                ->cascadeOnDelete();

            // Cost paid by your platform to the AI provider
            $table->decimal('input_cost', 12, 8);
            $table->decimal('output_cost', 12, 8);

            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();

            $table->timestamps();

            $table->index([
                'model_id',
                'effective_from',
                'effective_to',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_costs');
    }
};
