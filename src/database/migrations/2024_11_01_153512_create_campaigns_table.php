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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 100);                   // キャンペーン名
            $table->date('startDate');                     // 開始日
            $table->date('endDate');                       // 終了日
            $table->unsignedInteger('discountRate');       // 割引率（%）小数点切り捨て
            $table->string('discountCode', 50)->unique();  // 割引コード
            $table->text('description')->nullable();        // キャンペーン説明
            $table->boolean('is_active')->default(true);   // キャンペーンの有効/無効状態
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
