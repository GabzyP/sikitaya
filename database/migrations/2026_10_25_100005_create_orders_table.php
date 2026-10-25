<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();

            $table->string('recipient_name');
            $table->string('phone', 20);
            $table->text('address');
            $table->unsignedInteger('city_id');
            $table->string('city_name');
            $table->string('province_name');
            $table->string('postal_code', 10)->nullable();

            $table->string('courier', 10);
            $table->string('shipping_service', 30);
            $table->string('shipping_etd', 30)->nullable();
            $table->unsignedBigInteger('shipping_cost')->default(0);
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('total');

            $table->string('payment_method', 20);
            $table->string('status', 20)->default('menunggu')->index();
            $table->string('payment_proof')->nullable();
            $table->timestamp('payment_proof_uploaded_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('tracking_number')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['store_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
