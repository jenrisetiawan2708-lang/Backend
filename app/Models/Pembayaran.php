<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pembayaran extends Model
{
    protected $table = 'pembayaran';
    protected $primaryKey = 'id_pembayaran';
    protected $fillable = ['id_tagihan', 'tanggal_pembayaran', 'jumlah_bayar', 'bukti', 'status_validasi'];

    public function tagihan() {
        return $this->belongsTo(Tagihan::class, 'id_tagihan');
    }
}