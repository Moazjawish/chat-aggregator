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
        Schema::table('subscription_plan_model', function (Blueprint $table) {
        $table->unsignedBigInteger('input_token_limit')->nullable();
        $table->unsignedBigInteger('output_token_limit')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_plan_model', function (Blueprint $table) {
            //
        });
    }
};
