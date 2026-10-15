<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class WorkType extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    public function observations(): BelongsToMany
    {
        return $this->belongsToMany(CompetitorObservation::class)->withPivot('quantity');
    }
}
