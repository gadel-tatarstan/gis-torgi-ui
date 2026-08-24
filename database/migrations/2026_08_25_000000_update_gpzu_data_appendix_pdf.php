<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gpzu_data', function (Blueprint $table) {
            $table->dropColumn('utility_tables');
            $table->text('appendix_pdf')->nullable()->after('permitted_uses');
        });
    }

    public function down(): void
    {
        Schema::table('gpzu_data', function (Blueprint $table) {
            $table->dropColumn('appendix_pdf');
            $table->json('utility_tables')->nullable()->after('permitted_uses');
        });
    }
};
