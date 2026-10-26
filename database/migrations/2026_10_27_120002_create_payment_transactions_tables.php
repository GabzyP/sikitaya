<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();
            $table->string('checkout_token', 64)->nullable()->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider', 20)->default('midtrans');
            $table->string('allocation_method', 20)->nullable();
            $table->string('status', 20)->default('pending')->index();
            $table->char('currency', 3)->default('IDR');
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedBigInteger('platform_commission')->default(0);
            $table->unsignedBigInteger('midtrans_fee')->default(0);
            $table->string('fee_bearer', 20)->default('platform');
            $table->string('midtrans_order_id', 50)->nullable()->unique();
            $table->string('midtrans_transaction_id', 100)->nullable()->unique();
            $table->string('snap_token', 255)->nullable();
            $table->text('snap_redirect_url')->nullable();
            $table->string('payment_type', 30)->nullable();
            $table->string('fraud_status', 20)->nullable();
            $table->string('gateway_status', 30)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('notified_at')->nullable();
            $table->unsignedInteger('notification_count')->default(0);
            $table->text('failure_reason')->nullable();
            $table->json('gateway_payload')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('stock_restored_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('payment_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_transaction_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt_number');
            $table->string('midtrans_order_id', 50)->unique();
            $table->string('status', 20)->default('creating')->index();
            $table->string('snap_token', 255)->nullable();
            $table->text('snap_redirect_url')->nullable();
            $table->string('gateway_status', 30)->nullable();
            $table->json('response')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->unique(['payment_transaction_id', 'attempt_number']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('payment_transaction_id')
                ->nullable()
                ->after('payment_method')
                ->constrained('payment_transactions')
                ->nullOnDelete();

            $table->index(['payment_method', 'status']);
        });

        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            $table->string('method', 20);
            $table->string('status', 20)->default('pending')->index();
            $table->unsignedBigInteger('gross_amount');
            $table->unsignedBigInteger('platform_commission')->default(0);
            $table->unsignedBigInteger('midtrans_fee')->default(0);
            $table->unsignedBigInteger('net_amount');
            $table->string('merchant_id', 100)->nullable();
            $table->string('payout_bank_code', 20)->nullable();
            $table->string('payout_bank_name', 100)->nullable();
            $table->text('payout_account_number')->nullable();
            $table->string('payout_account_name', 100)->nullable();
            $table->string('midtrans_reference', 100)->nullable()->index();
            $table->string('midtrans_status', 30)->nullable();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->unsignedBigInteger('disbursed_amount')->default(0);
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['method', 'status']);
        });

        Schema::create('payment_allocation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_allocation_id')->constrained()->cascadeOnDelete();
            $table->string('action', 50);
            $table->string('status_before', 20)->nullable();
            $table->string('status_after', 20)->nullable();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('midtrans_order_id', 50)->nullable()->index();
            $table->string('midtrans_transaction_id', 100)->nullable();
            $table->string('transaction_status', 30)->nullable();
            $table->string('gross_amount', 30)->nullable();
            $table->string('currency', 3)->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->boolean('processed')->default(false);
            $table->text('message')->nullable();
            $table->json('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_logs');
        Schema::dropIfExists('payment_allocation_events');
        Schema::dropIfExists('payment_allocations');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['payment_method', 'status']);
            $table->dropConstrainedForeignId('payment_transaction_id');
        });

        Schema::dropIfExists('payment_attempts');
        Schema::dropIfExists('payment_transactions');
    }
};
