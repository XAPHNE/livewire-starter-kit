<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tier extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'concurrent_sessions',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'tier_user');
    }
}
