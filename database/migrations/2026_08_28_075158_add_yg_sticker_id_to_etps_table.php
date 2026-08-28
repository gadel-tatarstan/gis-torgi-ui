<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etps', function (Blueprint $table) {
            $table->string('yg_sticker_id')->nullable()->after('key_etp');
        });
    }

    public function down(): void
    {
        Schema::table('etps', function (Blueprint $table) {
            $table->dropColumn('yg_sticker_id');
        });
    }
};
