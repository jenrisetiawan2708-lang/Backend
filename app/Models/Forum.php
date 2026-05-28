<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Forum extends Model
{
    protected $table = 'forum';
    protected $primaryKey = 'id_forum';
    protected $fillable = ['id_pengguna', 'parent_id', 'isi_pesan', 'tanggal'];

    public function pengguna() {
        return $this->belongsTo(Pengguna::class, 'id_pengguna');
    }
    public function replies() {
        return $this->hasMany(Forum::class, 'parent_id');
    }
}