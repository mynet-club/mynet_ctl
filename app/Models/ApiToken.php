<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiToken extends Model
{
    protected $table = 'api_tokens';

    protected $fillable = [
        'token_id', 'name', 'token_hash', 'abilities',
        'last_used_at', 'expires_at', 'status',
    ];

    protected $casts = [
        'token_id'    => 'integer',
        'abilities'   => 'array',
        'last_used_at'=> 'datetime',
        'expires_at'  => 'datetime',
        'status'      => 'boolean',
    ];

    public function scopeActive($query)
    {
        return $query->where('status', 1)
                     ->where(function ($q) {
                         $q->whereNull('expires_at')
                           ->orWhere('expires_at', '>', now());
                     });
    }

    public function hasAbility(string $ability): bool
    {
        return in_array($ability, $this->abilities ?? [], true)
            || in_array('*', $this->abilities ?? [], true);
    }
}
