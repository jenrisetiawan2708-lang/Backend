<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tagihan extends Model
{
    protected $table = 'tagihan';
    protected $primaryKey = 'id_tagihan';
    protected $fillable = ['id_sewa', 'bulan', 'jumlah', 'denda', 'status_tagihan'];

    public function sewaKamar() {
        return $this->belongsTo(SewaKamar::class, 'id_sewa');
    }
    public function pembayaran() {
        return $this->hasMany(Pembayaran::class, 'id_tagihan');
    }
    public function notifikasi() {
        return $this->hasMany(Notifikasi::class, 'id_tagihan');
    }
}