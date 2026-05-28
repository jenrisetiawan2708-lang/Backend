<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Kamar extends Model
{
    protected $table = 'kamar';
    protected $primaryKey = 'id_kamar';
    protected $fillable = ['nomor_kamar', 'harga', 'status_kamar'];

    public function fasilitas() {
        return $this->belongsToMany(Fasilitas::class, 'kamar_fasilitas', 'id_kamar', 'id_fasilitas');
    }
    public function sewaKamar() {
        return $this->hasMany(SewaKamar::class, 'id_kamar');
    }
}