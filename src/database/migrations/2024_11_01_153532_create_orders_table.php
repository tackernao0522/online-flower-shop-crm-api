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
        Schema::create('orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('orderNumber')->unique();  // 注文番号
            $table->dateTime('orderDate');           // 注文日時
            $table->unsignedInteger('totalAmount');  // 合計金額（税込）
            $table->enum('status', [
                'PENDING',      // 注文受付
                'PROCESSING',   // 処理中
                'CONFIRMED',    // 確認済み
                'SHIPPED',      // 発送済み
                'DELIVERED',    // 配達完了
                'CANCELLED'     // キャンセル
            ])->default('PENDING');
            $table->unsignedInteger('discountApplied')->default(0);  // 適用された割引額
            $table->foreignUuid('customerId')->constrained('customers')->onDelete('restrict');
            $table->foreignUuid('userId')->constrained('users')->onDelete('restrict');
            $table->foreignUuid('campaignId')->nullable()->constrained('campaigns')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
