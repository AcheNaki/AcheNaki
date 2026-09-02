<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnonymousReporter extends Model
{
    protected $fillable = ['token_hash'];

    protected $hidden = ['token_hash'];

    public function utilityReports(): HasMany
    {
        return $this->hasMany(UtilityReport::class);
    }
}
