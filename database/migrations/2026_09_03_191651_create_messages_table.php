<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('conversation_id')
                ->constrained('conversations')
                ->cascadeOnDelete();

            $table->foreignId('model_id')
                ->nullable()
                ->constrained('models')
                ->nullOnDelete();

            $table->enum('role', [
                'user',
                'assistant',
            ]);

            $table->longText('content');

            $table->unsignedBigInteger('input_tokens')
                ->nullable();

            $table->unsignedBigInteger('output_tokens')
                ->nullable();

            $table->decimal(
                'provider_cost',
                20,
                15
            )->nullable();

            $table->decimal(
                'user_cost',
                20,
                15
            )->nullable();

            $table->timestamps();

            $table->index([
                'conversation_id',
                'created_at',
            ]);

            $table->index('model_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
