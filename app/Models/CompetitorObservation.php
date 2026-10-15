<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CompetitorObservation extends Model
{
    use HasFactory;

    protected $fillable = ['workshop_id', 'observed_on', 'observed_hour', 'created_by'];

    protected $casts = ['observed_on' => 'date', 'observed_hour' => 'integer'];

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    public function workTypes(): BelongsToMany
    {
        return $this->belongsToMany(WorkType::class)->withPivot('quantity');
    }
}
