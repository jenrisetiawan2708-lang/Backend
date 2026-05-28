<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kamar_fasilitas', function (Blueprint $table) {
            $table->unsignedBigInteger('id_kamar');
            $table->unsignedBigInteger('id_fasilitas');
            $table->primary(['id_kamar', 'id_fasilitas']);
            $table->foreign('id_kamar')->references('id_kamar')->on('kamar')->onDelete('cascade');
            $table->foreign('id_fasilitas')->references('id_fasilitas')->on('fasilitas')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kamar_fasilitas');
    }
};