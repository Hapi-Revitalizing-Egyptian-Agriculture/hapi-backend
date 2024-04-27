<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('lands', function (Blueprint $table) {
            $table->dropforeign('lands_crop_id_foreign');
            $table->dropUnique('lands_crop_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('lands', function (Blueprint $table) {
            $table->foreign('crop_id')->references('id')->on('crops')->onDelete('set null');
            $table->unique('crop_id');
        });
    }
};
