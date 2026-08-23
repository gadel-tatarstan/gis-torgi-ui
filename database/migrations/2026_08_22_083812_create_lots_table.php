<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lots', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('notice_number');
            $table->integer('lot_number');
            $table->string('bidd_form_code')->nullable();
            $table->string('bidd_form_name')->nullable();
            $table->text('lot_name');
            $table->text('lot_description')->nullable();
            $table->decimal('price_min', 15, 2);
            $table->string('price_min_exact')->nullable();
            $table->decimal('price_step', 15, 2)->nullable();
            $table->decimal('deposit', 15, 2)->nullable();
            $table->datetime('bidd_end_time')->nullable();
            $table->datetime('auction_start_date')->nullable();
            $table->datetime('bidd_start_time')->nullable();
            $table->json('lot_images')->nullable();
            $table->string('permitted_use')->nullable();
            $table->string('cadastral_number')->nullable();
            $table->decimal('area', 15, 2)->nullable();
            $table->string('area_unit')->nullable();
            $table->string('etp_code')->nullable();
            $table->string('etp_url')->nullable();
            $table->string('estate_address')->nullable();
            $table->datetime('create_date')->nullable();
            $table->datetime('notice_first_version_publication_date')->nullable();
            $table->string('lot_vat_name')->nullable();
            $table->string('lot_status')->nullable();
            $table->string('version_id')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lon', 10, 7)->nullable();
            $table->boolean('is_viewed')->default(false);
            $table->boolean('is_not_interested')->default(false);
            $table->boolean('on_board')->default(false);
            $table->json('lot_attachments')->nullable();
            $table->json('notice_attachments')->nullable();
            $table->json('characteristics_raw')->nullable();
            $table->json('attributes_raw')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lots');
    }
};
