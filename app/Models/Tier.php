<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OwenIt\Auditing\Contracts\Auditable;

class Tier extends Model implements Auditable
{
    use HasFactory, SoftDeletes, \OwenIt\Auditing\Auditable;

    protected $fillable = [
        'name',
        'description',
        'concurrent_sessions',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class , 'tier_user');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class , 'created_by')->withTrashed();
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class , 'updated_by')->withTrashed();
    }

    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class , 'deleted_by')->withTrashed();
    }
}
