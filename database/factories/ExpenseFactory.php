<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Expense> */
class ExpenseFactory extends Factory
{
    public function definition(): array
    {
        return ['category' => 'Listrik & Air', 'description' => 'Pengeluaran pengujian', 'amount' => 150000, 'expense_date' => now()->toDateString(), 'created_by' => User::factory()];
    }
}
