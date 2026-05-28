<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SewaKamar extends Model
{
    protected $table = 'sewa_kamar';
    protected $primaryKey = 'id_sewa';
    protected $fillable = ['id_pengguna', 'id_kamar', 'tanggal_masuk', 'tanggal_keluar', 'status_sewa'];

    public function pengguna() {
        return $this->belongsTo(Pengguna::class, 'id_pengguna');
    }
    public function kamar() {
        return $this->belongsTo(Kamar::class, 'id_kamar');
    }
    public function tagihan() {
        return $this->hasMany(Tagihan::class, 'id_sewa');
    }
}