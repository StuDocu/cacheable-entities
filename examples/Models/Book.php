<?php

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    public function scopeWhereValid(): void
    {
        // --
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }
}
