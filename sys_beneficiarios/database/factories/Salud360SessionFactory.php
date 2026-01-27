<?php

namespace Database\Factories;

use App\Models\Salud360Session;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Salud360Session>
 */
class Salud360SessionFactory extends Factory
{
    protected $model = Salud360Session::class;

    public function definition(): array
    {
        return [
            'beneficiario_id' => null,
            'psicologo_id' => null,
            'session_date' => now()->toDateString(),
            'session_number' => 1,
            'is_first' => false,
            'motivo_consulta' => null,
            'riesgo_suicida' => null,
            'uso_sustancias' => null,
            'next_session_date' => null,
            'next_objective' => null,
            'notes' => null,
            'created_by' => null,
        ];
    }
}

