<?php

namespace Database\Factories;

use App\Models\Salud360Assignment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Salud360Assignment>
 */
class Salud360AssignmentFactory extends Factory
{
    protected $model = Salud360Assignment::class;

    public function definition(): array
    {
        return [
            'beneficiario_id' => null,
            'psicologo_id' => null,
            'assigned_by' => null,
            'changed_by' => null,
            'active' => true,
            'assigned_at' => now(),
            'changed_at' => null,
        ];
    }
}

