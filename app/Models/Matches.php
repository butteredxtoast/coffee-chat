<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Matches extends Model
{
    protected $fillable = [
        'member1_id',
        'member2_id',
        'met',
        'is_current',
        'matched_at',
        'met_confirmed_at',
    ];

    protected $casts = [
        'met' => 'boolean',
        'is_current' => 'boolean',
        'matched_at' => 'datetime',
        'met_confirmed_at' => 'datetime',
    ];

    public function member1(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member1_id');
    }

    public function member2(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member2_id');
    }
}
