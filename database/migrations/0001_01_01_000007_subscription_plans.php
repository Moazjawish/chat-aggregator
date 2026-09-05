<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_plans', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->text('description')->nullable();

            // Subscription price paid by the user
            $table->decimal('price', 20, 15)->default(0);

            $table->string('currency', 3)->default('usd');

            // month, year, etc.
            $table->string('billing_interval')->nullable();

            // Stripe Product / Price
            $table->string('stripe_product_id')->nullable()->unique();
            $table->string('stripe_price_id')->nullable()->unique();

            $table->unsignedInteger('duration_days')->nullable();

            $table->boolean('status')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_plans');
    }
};
