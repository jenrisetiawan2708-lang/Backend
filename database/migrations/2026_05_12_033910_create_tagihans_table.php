<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan', function (Blueprint $table) {
            $table->id('id_tagihan');
            $table->unsignedBigInteger('id_sewa');
            $table->date('bulan');
            $table->decimal('jumlah', 12, 2);
            $table->decimal('denda', 12, 2)->default(0);
            $table->enum('status_tagihan', ['Belum Dibayar', 'Lunas']);
            $table->timestamps();
            $table->foreign('id_sewa')->references('id_sewa')->on('sewa_kamar')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan');
    }
};