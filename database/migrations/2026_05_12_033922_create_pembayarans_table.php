<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pembayaran', function (Blueprint $table) {
            $table->id('id_pembayaran');
            $table->unsignedBigInteger('id_tagihan');
            $table->date('tanggal_pembayaran');
            $table->decimal('jumlah_bayar', 12, 2);
            $table->text('bukti')->nullable();
            $table->enum('status_validasi', ['Menunggu Validasi', 'Valid', 'Ditolak']);
            $table->timestamps();
            $table->foreign('id_tagihan')->references('id_tagihan')->on('tagihan')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pembayaran');
    }
};