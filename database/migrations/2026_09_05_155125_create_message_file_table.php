<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_file', function (Blueprint $table) {
            $table->id();

            $table->foreignId('message_id')
                ->constrained('messages')
                ->cascadeOnDelete();

            $table->foreignId('file_id')
                ->constrained('files')
                ->cascadeOnDelete();

            $table->timestamps();

            $table->unique([
                'message_id',
                'file_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_file');
    }
};
