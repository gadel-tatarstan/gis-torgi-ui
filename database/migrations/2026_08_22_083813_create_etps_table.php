<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('etps', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('published')->default(true);
            $table->string('site')->nullable();
            $table->string('short_name')->nullable();
            $table->string('icon_url')->nullable();
            $table->string('icon_file_name')->nullable();
            $table->string('key_etp')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etps');
    }
};
