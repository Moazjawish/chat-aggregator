<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('subscription_id')
                ->nullable()
                ->constrained('subscriptions')
                ->nullOnDelete();

            // Stripe identifiers
            $table->string('stripe_payment_intent_id')
                ->nullable()
                ->unique();

            $table->string('stripe_checkout_session_id')
                ->nullable()
                ->unique();

            $table->string('stripe_invoice_id')
                ->nullable()
                ->unique();

            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('usd');

            $table->string('status')->default('pending');

            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('subscription_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
