<?php

namespace App\Models;

use Database\Factories\RoundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Round extends Model
{
    /** @use HasFactory<RoundFactory> */
    use HasFactory;

    protected $fillable = ['name', 'sequence'];

    public function matches(): HasMany
    {
        return $this->hasMany(DebateMatch::class, 'round_id');
    }
}
