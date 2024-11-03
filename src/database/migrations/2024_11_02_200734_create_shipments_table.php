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
        Schema::create('shipments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('orderId')->constrained('orders')->onDelete('restrict');
            $table->dateTime('shippingDate');
            $table->enum('status', [
                'PREPARING', // 準備中
                'READY', // 発送準備完了
                'SHIPPED', // 発送済み
                'IN_TRANSIT', // 配送中
                'DELIVERED', // 配達完了
                'FAILED', // 配達失敗
                'RETURNED' // 配送
            ])->default('PREPARING');
            $table->string('trackingNumber')->nullable();
            $table->text('deliveryNote')->nullable(); // 配送メモ
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipments');
    }
};
