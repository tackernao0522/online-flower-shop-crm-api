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
        Schema::create('stats_logs', function (Blueprint $table) {
            $table->id();
            $table->string('metric_type', 50)->index();
            $table->integer('current_value');
            $table->integer('previous_value');
            $table->decimal('change_rate', 8, 2);
            $table->timestamp('recorded_at')->index();
            $table->timestamps();

            // 複合インデックス
            $table->index(['metric_type', 'recorded_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stats_logs');
    }
};
