<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    use HasFactory;
    protected $fillable = ['category', 'description', 'amount', 'expense_date', 'created_by'];
    protected $casts = [
        'expense_date' => 'date',
    ];

    public function user() { return $this->belongsTo(User::class, 'created_by'); }
}