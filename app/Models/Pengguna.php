<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Pengguna extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table      = 'pengguna';
    protected $primaryKey = 'id_pengguna';

    protected $fillable = [
        'nama', 'email', 'username', 'password', 'role',
        'google_id', 'avatar',
    ];

    protected $hidden = ['password'];

    public function sewaKamar()
    {
        return $this->hasMany(SewaKamar::class, 'id_pengguna');
    }

    public function notifikasi()
    {
        return $this->hasMany(Notifikasi::class, 'id_pengguna');
    }

    public function forum()
    {
        return $this->hasMany(Forum::class, 'id_pengguna');
    }
}
