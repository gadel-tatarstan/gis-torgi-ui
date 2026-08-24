<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gpzu_data', function (Blueprint $table) {
            $table->id();
            $table->string('lot_id')->unique();
            $table->string('file_id');
            $table->string('file_name');
            $table->json('permitted_uses')->nullable();
            $table->json('utility_tables')->nullable();
            $table->integer('gas_page')->nullable();
            $table->integer('drawing_page')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gpzu_data');
    }
};
