<?php

namespace App\Http\Controllers\S360\Api;

use App\Http\Controllers\Controller;
use App\Models\Beneficiario;
use App\Models\Salud360Assignment;
use App\Models\Salud360Session;
use App\Services\S360MetricsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class S360ApiController extends Controller
{
    public function __construct(private S360MetricsService $metrics)
    {
    }

    // GET /api/s360/psico/pacientes
    public function psicoPacientes(Request $request)
    {
        $user = $request->user();

        $rows = DB::table('salud360_assignments as a')
            ->join('beneficiarios as b', 'a.beneficiario_id', '=', 'b.id')
            ->where('a.active', true)
            ->where('a.psicologo_id', $user->id)
            ->select('b.id as beneficiario_id', 'b.nombre', 'b.apellido_paterno', 'b.apellido_materno', 'b.fecha_nacimiento', 'b.telefono')
            ->orderBy('b.nombre')
            ->get();

        $items = $rows->map(function ($r) {
            $edad = null;
            if (!empty($r->fecha_nacimiento)) {
                try { $edad = Carbon::parse($r->fecha_nacimiento)->age; } catch (\Throwable) { $edad = null; }
            }
            return [
                'beneficiario_id' => $r->beneficiario_id,
                'nombre' => trim(($r->nombre ?? '').' '.($r->apellido_paterno ?? '').' '.($r->apellido_materno ?? '')),
                'edad' => $edad,
                'telefono' => $r->telefono,
                'correo' => null,
            ];
        })->all();

        return response()->json($items);
    }

    // GET /api/s360/psico/sesiones/{beneficiario}
    public function psicoHistorial(Request $request, Beneficiario $beneficiario)
    {
        // Asegurar que pertenece al psicólogo
        $assigned = Salud360Assignment::where('beneficiario_id', $beneficiario->id)
            ->where('psicologo_id', $request->user()->id)
            ->where('active', true)
            ->exists();
        if (! $assigned) {
            abort(403);
        }

        $items = Salud360Session::where('beneficiario_id', $beneficiario->id)
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->get(['id','beneficiario_id','session_number','session_date','is_first','next_session_date','next_objective','notes']);

        return response()->json($items);
    }

    // GET /api/s360/enc360/dash
    public function enc360Dash(Request $request)
    {
        $filters = [
            'municipio_id' => $request->integer('municipio_id') ?: null,
            'psicologo_id' => $request->integer('psicologo_id') ?: null,
        ];
        $pacientes = $this->metrics->activePatientsCount($filters);
        $semana = $this->metrics->sessionsThisWeekCount($filters);
        $proximas = $this->metrics->upcomingCount($filters);
        $work = $this->metrics->workloadsByPsychologist($filters, 10, true);
        $carga = array_map(fn($w)=>[
            'psicologo_id' => $w['psicologo_id'],
            'nombre' => $w['name'],
            'pacientes' => $w['cargas'],
        ], $work);
        return response()->json([
            'pacientes_activos' => $pacientes,
            'sesiones_semana' => $semana,
            'proximas_citas' => $proximas,
            'carga_por_psicologo' => $carga,
        ]);
    }

    // GET /api/s360/bienestar/dash
    public function bienestarDash(Request $request)
    {
        $pacientes = $this->metrics->activePatientsCount();
        $semana = $this->metrics->sessionsThisWeekCount();
        $proximas = $this->metrics->upcomingCount();
        $work = $this->metrics->workloadsByPsychologist([], 10, true);
        $carga = array_map(fn($w)=>[
            'psicologo_id' => $w['psicologo_id'],
            'nombre' => $w['name'],
            'pacientes' => $w['cargas'],
        ], $work);
        return response()->json([
            'pacientes_activos' => $pacientes,
            'sesiones_semana' => $semana,
            'proximas_citas' => $proximas,
            'carga_por_psicologo' => $carga,
        ]);
    }
}

