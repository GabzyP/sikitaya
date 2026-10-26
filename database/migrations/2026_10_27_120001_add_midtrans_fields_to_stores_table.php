<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->string('midtrans_merchant_id', 100)->nullable()->after('is_active');
            $table->string('midtrans_merchant_status', 20)->default('unregistered')->after('midtrans_merchant_id');
            $table->string('payout_bank_code', 20)->nullable()->after('midtrans_merchant_status');
            $table->string('payout_bank_name', 100)->nullable()->after('payout_bank_code');
            $table->text('payout_account_number')->nullable()->after('payout_bank_name');
            $table->string('payout_account_name', 100)->nullable()->after('payout_account_number');
            $table->timestamp('payout_account_verified_at')->nullable()->after('payout_account_name');
            $table->unsignedBigInteger('payout_account_verified_by')->nullable()->after('payout_account_verified_at');

            $table->unique('midtrans_merchant_id');
        });
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            $table->dropUnique(['midtrans_merchant_id']);
            $table->dropColumn([
                'midtrans_merchant_id',
                'midtrans_merchant_status',
                'payout_bank_code',
                'payout_bank_name',
                'payout_account_number',
                'payout_account_name',
                'payout_account_verified_at',
                'payout_account_verified_by',
            ]);
        });
    }
};
