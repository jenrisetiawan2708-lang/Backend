<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum', function (Blueprint $table) {
            $table->id('id_forum');
            $table->unsignedBigInteger('id_pengguna');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->text('isi_pesan');
            $table->datetime('tanggal');
            $table->timestamps();
            $table->foreign('id_pengguna')->references('id_pengguna')->on('pengguna')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum');
    }
};