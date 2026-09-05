<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('model_capabilities', function (Blueprint $table) {
            $table->id();

            $table->foreignId('model_id')
                ->constrained('models')
                ->cascadeOnDelete();

            /*
             * Examples:
             *
             * document_input
             * image_input
             * web_search
             */
            $table->string('key');

            /*
             * Allows temporarily disabling
             * a capability without deleting it.
             */
            $table->boolean('status')
                ->default(true);

            $table->timestamps();

            /*
             * A model should not have
             * the same capability twice.
             */
            $table->unique([
                'model_id',
                'key',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('model_capabilities');
    }
};
