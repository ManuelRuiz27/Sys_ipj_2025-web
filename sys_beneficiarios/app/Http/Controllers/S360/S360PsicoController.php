<?php

namespace App\Http\Controllers\S360;

use App\Http\Controllers\Controller;
use App\Models\Beneficiario;
use App\Models\Salud360Assignment;
use App\Models\Salud360Session;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Services\SessionNumberService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class S360PsicoController extends Controller
{
    public function __construct(private SessionNumberService $numbers)
    {
    }
    public function pacientes(Request $request)
    {
        $user = $request->user();

        $items = \DB::table('salud360_assignments')
            ->where('active', true)
            ->where('psicologo_id', $user->id)
            ->join('beneficiarios', 'salud360_assignments.beneficiario_id', '=', 'beneficiarios.id')
            ->select([
                'beneficiarios.id as id',
                'salud360_assignments.id as assignment_id',
                'beneficiarios.nombre',
                'beneficiarios.apellido_paterno',
                'beneficiarios.apellido_materno',
                'beneficiarios.telefono',
                'beneficiarios.municipio_id',
                'beneficiarios.seccional',
            ])
            ->orderBy('beneficiarios.nombre')
            ->get();

        return response()->json(['items' => $items]);
    }

    public function pacientesView()
    {
        return view('s360.psico.pacientes');
    }

    public function showPaciente(string $id)
    {
        $userId = auth()->id();
        $beneficiario = Beneficiario::findOrFail($id);

        $assigned = Salud360Assignment::where('beneficiario_id', $beneficiario->id)
            ->where('psicologo_id', $userId)
            ->where('active', true)
            ->exists();
        if (! $assigned) {
            abort(403);
        }

        $edad = $beneficiario->fecha_nacimiento ? Carbon::parse($beneficiario->fecha_nacimiento)->age : null;

        return response()->json([
            'id' => $beneficiario->id,
            'nombre' => trim($beneficiario->nombre.' '.$beneficiario->apellido_paterno.' '.$beneficiario->apellido_materno),
            'edad' => $edad,
            'telefono' => $beneficiario->telefono,
            'correo' => $beneficiario->email ?? null,
        ]);
    }

    public function pacienteView(string $id)
    {
        Log::info('Paciente view', ['beneficiario_id' => $id, 'user_id' => auth()->id()]);
        return view('s360.psico.paciente_show', ['beneficiarioId' => $id]);
    }

    public function storeSesion(Request $request)
    {
        $beneficiarioId = $request->input('beneficiario_id');
        $beneficiario = Beneficiario::findOrFail($beneficiarioId);
        Gate::authorize('create', [Salud360Session::class, $beneficiario]);

        $isFirst = filter_var($request->boolean('is_first'), FILTER_VALIDATE_BOOL);

        $baseRules = [
            'beneficiario_id' => ['required', 'uuid', 'exists:beneficiarios,id'],
            'session_date' => ['required', 'date'],
            'is_first' => ['sometimes', 'boolean'],
        ];

        if ($isFirst) {
            $rules = $baseRules + [
                'motivo_consulta' => ['required', 'string'],
                'riesgo_suicida' => ['required', 'boolean'],
                'uso_sustancias' => ['required', 'boolean'],
            ];
        } else {
            $rules = $baseRules + [
                'next_session_date' => ['nullable', 'date', 'after:session_date'],
                'next_objective' => ['nullable', 'string'],
                'notes' => ['nullable', 'string'],
            ];
        }

        $data = $request->validate($rules);

        $existingCount = (int) Salud360Session::where('beneficiario_id', $beneficiario->id)->count();

        // Validaciones adicionales
        if ($isFirst && $existingCount > 0) {
            return response()->json(['message' => 'Ya existe al menos una sesión para este beneficiario. No puede marcarse como primera.'], 422);
        }
        if (! $isFirst && $existingCount === 0) {
            return response()->json(['message' => 'Primero debe registrarse una sesión inicial (is_first=true).'], 422);
        }

        // Evitar dos sesiones el mismo día para el beneficiario
        $sameDay = Salud360Session::where('beneficiario_id', $beneficiario->id)
            ->whereDate('session_date', Carbon::parse($data['session_date'])->toDateString())
            ->exists();
        if ($sameDay) {
            return response()->json(['message' => 'Ya existe una sesión para este beneficiario en esa fecha.'], 422);
        }

        $session = DB::transaction(function () use ($beneficiario, $data, $isFirst) {
            $sessionNumber = $this->numbers->nextForBeneficiario($beneficiario->id);
            $s = new Salud360Session();
            $s->beneficiario_id = $beneficiario->id;
            $s->psicologo_id = auth()->id();
            $s->session_date = Carbon::parse($data['session_date']);
            $s->session_number = $sessionNumber;
            $s->is_first = $isFirst;
            $s->motivo_consulta = $data['motivo_consulta'] ?? null;
            $s->riesgo_suicida = array_key_exists('riesgo_suicida', $data) ? (bool)$data['riesgo_suicida'] : null;
            $s->uso_sustancias = array_key_exists('uso_sustancias', $data) ? (bool)$data['uso_sustancias'] : null;
            $s->next_session_date = $data['next_session_date'] ?? null;
            $s->next_objective = $data['next_objective'] ?? null;
            $s->notes = $data['notes'] ?? null;
            $s->created_by = auth()->id();
            $s->save();
            return $s;
        });

        return response()->json(['ok' => true, 'session' => $session], 201);
    }

    public function historial(Beneficiario $beneficiario)
    {
        $user = auth()->user();

        // psicólogo debe estar asignado o tener rol de acceso más amplio, pero dado el grupo de rutas solo psicólogos llegan aquí
        $assigned = Salud360Assignment::where('beneficiario_id', $beneficiario->id)
            ->where('psicologo_id', $user->id)
            ->where('active', true)
            ->exists();
        if (! $assigned) {
            abort(403);
        }

        $items = Salud360Session::where('beneficiario_id', $beneficiario->id)
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->get();

        return response()->json(['items' => $items]);
    }

    public function historialView(Beneficiario $beneficiario)
    {
        Log::info('Historial view', ['beneficiario_id' => $beneficiario->id, 'user_id' => auth()->id()]);
        return view('s360.psico.historial', ['beneficiarioId' => $beneficiario->id]);
    }

    // Agenda semanal del psicólogo: pendientes (next_session_date) y atendidos (session_date)
    public function agendaSemana(Request $request)
    {
        $userId = $request->user()->id;
        $start = Carbon::now()->startOfWeek();
        $end = Carbon::now()->endOfWeek();

        $attended = DB::table('salud360_sessions as s')
            ->join('beneficiarios as b', 's.beneficiario_id', '=', 'b.id')
            ->where('s.psicologo_id', $userId)
            ->whereBetween('s.session_date', [$start, $end])
            ->select(
                'b.id as beneficiario_id',
                DB::raw("TRIM(b.nombre || ' ' || b.apellido_paterno || ' ' || b.apellido_materno) as nombre"),
                's.session_date as fecha'
            )
            ->orderBy('s.session_date', 'desc')
            ->get()
            ->map(function ($r) {
                return [
                    'beneficiario_id' => $r->beneficiario_id,
                    'nombre' => $r->nombre,
                    'fecha' => $r->fecha,
                    'estado' => 'atendido',
                ];
            });

        $pending = DB::table('salud360_sessions as s')
            ->join('beneficiarios as b', 's.beneficiario_id', '=', 'b.id')
            ->where('s.psicologo_id', $userId)
            ->whereNotNull('s.next_session_date')
            ->whereBetween('s.next_session_date', [$start, $end])
            ->select(
                'b.id as beneficiario_id',
                DB::raw("TRIM(b.nombre || ' ' || b.apellido_paterno || ' ' || b.apellido_materno) as nombre"),
                's.next_session_date as fecha'
            )
            ->orderBy('s.next_session_date', 'asc')
            ->get()
            ->map(function ($r) {
                return [
                    'beneficiario_id' => $r->beneficiario_id,
                    'nombre' => $r->nombre,
                    'fecha' => $r->fecha,
                    'estado' => 'pendiente',
                ];
            });

        $byId = [];
        foreach ($pending as $p) { $byId[$p['beneficiario_id']] = $p; }
        foreach ($attended as $a) { $byId[$a['beneficiario_id']] = $a; }
        $items = array_values($byId);
        usort($items, function ($a, $b) { return strcmp($a['fecha'], $b['fecha']); });

        return response()->json(['items' => $items]);
    }
}
