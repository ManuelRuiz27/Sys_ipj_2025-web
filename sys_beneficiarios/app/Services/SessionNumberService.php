<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class SessionNumberService
{
    public function nextForBeneficiario(string $beneficiarioId): int
    {
        // lock rows for this beneficiario to prevent race conditions
        $max = DB::table('salud360_sessions')
            ->where('beneficiario_id', $beneficiarioId)
            ->lockForUpdate()
            ->max('session_number');
        return ((int) $max) + 1;
    }
}

