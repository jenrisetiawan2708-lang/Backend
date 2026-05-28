<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KamarFasilitas extends Model
{
    protected $table = 'kamar_fasilitas';
    public $timestamps = false;
    protected $fillable = ['id_kamar', 'id_fasilitas'];
}