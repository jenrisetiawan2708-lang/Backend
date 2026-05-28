<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notifikasi extends Model
{
    protected $table = 'notifikasi';
    protected $primaryKey = 'id_notifikasi';
    protected $fillable = ['id_pengguna', 'id_tagihan', 'pesan', 'tanggal_kirim', 'status_baca'];

    public function pengguna() {
        return $this->belongsTo(Pengguna::class, 'id_pengguna');
    }
    public function tagihan() {
        return $this->belongsTo(Tagihan::class, 'id_tagihan');
    }
}