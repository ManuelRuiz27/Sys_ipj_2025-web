<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class S360MetricsService
{
    /**
     * Pacientes activos = count(assignments where active=1).
     */
    public function activePatientsCount(array $filters = []): int
    {
        $q = DB::table('salud360_assignments as a')->where('a.active', true);
        if (!empty($filters['municipio_id'])) {
            $q->join('beneficiarios as b', 'a.beneficiario_id', '=', 'b.id')
              ->where('b.municipio_id', (int) $filters['municipio_id']);
        }
        if (!empty($filters['psicologo_id'])) {
            $q->where('a.psicologo_id', (int) $filters['psicologo_id']);
        }
        return (int) $q->count();
    }

    /**
     * Sesiones esta semana = count(sessions where week(session_date)=current).
     */
    public function sessionsThisWeekCount(array $filters = []): int
    {
        $now = Carbon::now();
        $startWeek = (clone $now)->startOfWeek();
        $q = DB::table('salud360_sessions as s')
            ->whereBetween('s.session_date', [$startWeek, $now]);
        if (!empty($filters['municipio_id'])) {
            $q->join('beneficiarios as b', 's.beneficiario_id', '=', 'b.id')
              ->where('b.municipio_id', (int) $filters['municipio_id']);
        }
        if (!empty($filters['psicologo_id'])) {
            $q->where('s.psicologo_id', (int) $filters['psicologo_id']);
        }
        return (int) $q->count();
    }

    /**
     * Próximas citas = count(sessions where next_session_date between today and +7d).
     */
    public function upcomingCount(array $filters = []): int
    {
        $now = Carbon::now()->startOfDay();
        $in7 = (clone $now)->addDays(7)->endOfDay();
        $q = DB::table('salud360_sessions as s')
            ->whereNotNull('s.next_session_date')
            ->whereBetween('s.next_session_date', [$now, $in7]);
        if (!empty($filters['municipio_id'])) {
            $q->join('beneficiarios as b', 's.beneficiario_id', '=', 'b.id')
              ->where('b.municipio_id', (int) $filters['municipio_id']);
        }
        if (!empty($filters['psicologo_id'])) {
            $q->where('s.psicologo_id', (int) $filters['psicologo_id']);
        }
        return (int) $q->count();
    }

    /**
     * Carga por psicólogo = count(assignments group by psicologo).
     * Devuelve array de [psicologo_id, cargas]. Si $withNames true, añade 'name'.
     */
    public function workloadsByPsychologist(array $filters = [], int $limit = 5, bool $withNames = true): array
    {
        $q = DB::table('salud360_assignments as a')
            ->where('a.active', true)
            ->select('a.psicologo_id', DB::raw('COUNT(*) as c'))
            ->groupBy('a.psicologo_id')
            ->orderByDesc('c');
        if (!empty($filters['municipio_id'])) {
            $q->join('beneficiarios as b', 'a.beneficiario_id', '=', 'b.id')
              ->where('b.municipio_id', (int) $filters['municipio_id']);
        }
        if ($limit > 0) {
            $q->limit($limit);
        }
        $rows = $q->get();
        if ($withNames && $rows->count() > 0) {
            $names = DB::table('users')->whereIn('id', $rows->pluck('psicologo_id'))
                ->pluck('name', 'id');
            return $rows->map(fn($r)=>[
                'psicologo_id' => (int) $r->psicologo_id,
                'name' => $names[$r->psicologo_id] ?? (string) $r->psicologo_id,
                'cargas' => (int) $r->c,
            ])->values()->all();
        }
        return $rows->map(fn($r)=>[
            'psicologo_id' => (int) $r->psicologo_id,
            'cargas' => (int) $r->c,
        ])->values()->all();
    }
}

