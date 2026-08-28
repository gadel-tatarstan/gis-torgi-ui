<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->decimal('market_price', 15, 2)->nullable()->after('price_step');
            $table->string('yg_task_id')->nullable()->after('on_board');
        });
    }

    public function down(): void
    {
        Schema::table('lots', function (Blueprint $table) {
            $table->dropColumn(['market_price', 'yg_task_id']);
        });
    }
};
