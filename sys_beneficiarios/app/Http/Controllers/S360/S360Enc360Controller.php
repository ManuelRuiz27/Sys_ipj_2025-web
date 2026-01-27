<?php

namespace App\Http\Controllers\S360;

use App\Http\Controllers\Controller;
use App\Models\Beneficiario;
use App\Models\Salud360Assignment;
use App\Models\Salud360Session;
use App\Models\User;
use App\Http\Requests\S360\AssignRequest;
use App\Http\Requests\S360\ReassignRequest;
use App\Notifications\NewPsicologoAccount;
use App\Services\S360MetricsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class S360Enc360Controller extends Controller
{
    public function __construct(private S360MetricsService $metrics)
    {
    }
    public function view()
    {
        return view('s360.enc360.dashboard');
    }
    public function asignacionesView()
    {
        return view('s360.enc360.asignaciones');
    }
    public function psicologosView()
    {
        return view('s360.enc360.psicologos');
    }
    public function dash(Request $request)
    {
        $munId = $request->integer('municipio_id');
        $psicologoId = $request->integer('psicologo_id');
        $now = Carbon::now();
        $startWeek = (clone $now)->startOfWeek();
        $in7 = (clone $now)->addDays(7)->endOfDay();

        // Total pacientes asignados (activos)
        $assignQ = Salud360Assignment::query()->where('active', true)
            ->join('beneficiarios', 'salud360_assignments.beneficiario_id', '=', 'beneficiarios.id');
        if ($munId) {
            $assignQ->where('beneficiarios.municipio_id', $munId);
        }
        if ($psicologoId) {
            $assignQ->where('salud360_assignments.psicologo_id', $psicologoId);
        }
        $totalAsignados = (clone $assignQ)->count();

        // Sesiones de la semana
        $sessQ = Salud360Session::query()
            ->whereBetween('session_date', [$startWeek, $now]);
        if ($munId) {
            $sessQ->join('beneficiarios', 'salud360_sessions.beneficiario_id', '=', 'beneficiarios.id')
                  ->where('beneficiarios.municipio_id', $munId);
        }
        if ($psicologoId) {
            $sessQ->where('salud360_sessions.psicologo_id', $psicologoId);
        }
        $sesionesSemana = (clone $sessQ)->count();

        // Próximos a vencer (siguiente cita en <= 7 días) - contar beneficiarios únicos
        $dueQ = Salud360Session::query()
            ->whereNotNull('next_session_date')
            ->whereBetween('next_session_date', [$now->copy()->startOfDay(), $in7]);
        if ($munId) {
            $dueQ->join('beneficiarios', 'salud360_sessions.beneficiario_id', '=', 'beneficiarios.id')
                 ->where('beneficiarios.municipio_id', $munId);
        }
        if ($psicologoId) {
            $dueQ->where('salud360_sessions.psicologo_id', $psicologoId);
        }
        $proximosAVencer = (clone $dueQ)->distinct('salud360_sessions.beneficiario_id')->count('salud360_sessions.beneficiario_id');

        // Top psicólogos por cargas (asignaciones activas)
        $topQ = Salud360Assignment::query()->where('active', true)
            ->select('psicologo_id', DB::raw('COUNT(*) as c'))
            ->groupBy('psicologo_id')
            ->orderByDesc('c')
            ->limit(5);
        if ($munId) {
            $topQ->join('beneficiarios', 'salud360_assignments.beneficiario_id', '=', 'beneficiarios.id')
                 ->where('beneficiarios.municipio_id', $munId);
        }
        $topRaw = $topQ->get();
        $names = User::whereIn('id', $topRaw->pluck('psicologo_id'))
            ->pluck('name', 'id');
        $topPsicologos = $topRaw->map(function ($row) use ($names) {
            return [
                'psicologo_id' => (int) $row->psicologo_id,
                'name' => $names[$row->psicologo_id] ?? (string) $row->psicologo_id,
                'cargas' => (int) $row->c,
            ];
        })->values();

        // Recalcular KPIs con servicio reutilizable (aplica filtros)
        $filters = [
            'municipio_id' => $munId ?: null,
            'psicologo_id' => $psicologoId ?: null,
        ];
        $totalAsignados = $this->metrics->activePatientsCount($filters);
        $sesionesSemana = $this->metrics->sessionsThisWeekCount($filters);
        $proximosAVencer = $this->metrics->upcomingCount($filters);
        $topPsicologos = $this->metrics->workloadsByPsychologist($filters, 5, true);

        // Recalcular KPIs con servicio reutilizable
        $filters = [
            'municipio_id' => $munId ?: null,
            'psicologo_id' => $psicologoId ?: null,
        ];
        $totalAsignados = $this->metrics->activePatientsCount($filters);
        $sesionesSemana = $this->metrics->sessionsThisWeekCount($filters);
        $proximosAVencer = $this->metrics->upcomingCount($filters);
        $topPsicologos = $this->metrics->workloadsByPsychologist($filters, 5, true);

        return response()->json([
            'totalAsignados' => $totalAsignados,
            'sesionesSemana' => $sesionesSemana,
            'proximosAVencer' => $proximosAVencer,
            'topPsicologos' => $topPsicologos,
        ]);
    }

    public function latestSessions(Request $request)
    {
        $q = trim((string) $request->input('q'));
        $munId = $request->integer('municipio_id');
        $psicologoId = $request->integer('psicologo_id');

        $rows = DB::table('salud360_sessions as s')
            ->join('beneficiarios as b', 's.beneficiario_id', '=', 'b.id')
            ->join('users as u', 's.psicologo_id', '=', 'u.id')
            ->select('s.id','s.session_date','s.is_first','b.id as beneficiario_id', DB::raw("TRIM(b.nombre || ' ' || b.apellido_paterno || ' ' || b.apellido_materno) as beneficiario"), 'u.id as psicologo_id','u.name as psicologo')
            ->when($munId, fn($q2)=>$q2->where('b.municipio_id', $munId))
            ->when($psicologoId, fn($q2)=>$q2->where('s.psicologo_id', $psicologoId))
            ->when($q !== '', function ($q2) use ($q) {
                $like = '%'.$q.'%';
                $q2->where(function ($w) use ($like) {
                    $w->where('b.nombre', 'like', $like)
                      ->orWhere('b.apellido_paterno', 'like', $like)
                      ->orWhere('b.apellido_materno', 'like', $like);
                });
            })
            ->orderByDesc('s.session_date')
            ->orderByDesc('s.id')
            ->limit(50)
            ->get();

        return response()->json(['items' => $rows]);
    }

    public function upcoming(Request $request)
    {
        $now = Carbon::now()->startOfDay();
        $in7 = (clone $now)->addDays(7)->endOfDay();
        $q = trim((string) $request->input('q'));
        $munId = $request->integer('municipio_id');
        $psicologoId = $request->integer('psicologo_id');

        $rows = DB::table('salud360_sessions as s')
            ->join('beneficiarios as b', 's.beneficiario_id', '=', 'b.id')
            ->join('users as u', 's.psicologo_id', '=', 'u.id')
            ->select('s.id','s.next_session_date','b.id as beneficiario_id', DB::raw("TRIM(b.nombre || ' ' || b.apellido_paterno || ' ' || b.apellido_materno) as beneficiario"), 'u.id as psicologo_id','u.name as psicologo')
            ->whereNotNull('s.next_session_date')
            ->whereBetween('s.next_session_date', [$now, $in7])
            ->when($munId, fn($q2)=>$q2->where('b.municipio_id', $munId))
            ->when($psicologoId, fn($q2)=>$q2->where('s.psicologo_id', $psicologoId))
            ->when($q !== '', function ($q2) use ($q) {
                $like = '%'.$q.'%';
                $q2->where(function ($w) use ($like) {
                    $w->where('b.nombre', 'like', $like)
                      ->orWhere('b.apellido_paterno', 'like', $like)
                      ->orWhere('b.apellido_materno', 'like', $like);
                });
            })
            ->orderBy('s.next_session_date')
            ->limit(100)
            ->get();

        return response()->json(['items' => $rows]);
    }

    public function storePsicologo(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        $tempPassword = Str::random(12);

        $user = new User();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = $tempPassword; // hashed by cast
        $user->save();
        if (! $user->hasRole('psicologo')) {
            $user->assignRole('psicologo');
        }

        // Notificación por correo con credenciales temporales
        try {
            $user->notify(new NewPsicologoAccount($tempPassword));
        } catch (\Throwable $e) {
            // evitar romper flujo si mail falla
        }

        return response()->json([
            'ok' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ], 201);
    }

    public function assign(AssignRequest $request)
    {
        Gate::authorize('assign', Salud360Assignment::class);
        $data = $request->validated();

        $exists = Salud360Assignment::where('beneficiario_id', $data['beneficiario_id'])
            ->where('active', true)
            ->exists();
        if ($exists) {
            return response()->json(['message' => 'El beneficiario ya tiene un psicólogo asignado activo.'], 422);
        }

        $assignment = Salud360Assignment::create([
            'beneficiario_id' => $data['beneficiario_id'],
            'psicologo_id' => $data['psicologo_id'],
            'assigned_by' => $request->user()->id,
            'assigned_at' => Carbon::now(),
            'active' => true,
        ]);

        return response()->json(['ok' => true, 'assignment' => $assignment], 201);
    }

    public function reassign(ReassignRequest $request, Beneficiario $beneficiario)
    {
        $current = Salud360Assignment::where('beneficiario_id', $beneficiario->id)
            ->where('active', true)
            ->first();
        Gate::authorize('reassign', $current ?? new Salud360Assignment(['beneficiario_id' => $beneficiario->id]));
        $data = $request->validated();

        if ($current) {
            $current->update([
                'psicologo_id' => $data['psicologo_id'],
                'changed_by' => $request->user()->id,
                'changed_at' => Carbon::now(),
            ]);
        } else {
            // si no hay registro previo (por consistencia con UNIQUE), crear nuevo
            Salud360Assignment::create([
                'beneficiario_id' => $beneficiario->id,
                'psicologo_id' => $data['psicologo_id'],
                'assigned_by' => $request->user()->id,
                'assigned_at' => Carbon::now(),
                'active' => true,
            ]);
        }

        return response()->json(['ok' => true]);
    }

    // Listado de pacientes (asignaciones) con filtros
    public function patients(Request $request)
    {
        $status = $request->input('status', 'active'); // active|inactive|all
        $munId = $request->integer('municipio_id');
        $psicologoId = $request->integer('psicologo_id');

        $q = Salud360Assignment::query()
            ->leftJoin('beneficiarios', 'salud360_assignments.beneficiario_id', '=', 'beneficiarios.id')
            ->leftJoin('users as u', 'salud360_assignments.psicologo_id', '=', 'u.id')
            ->select([
                'salud360_assignments.*',
                'u.name as psicologo_name',
                'beneficiarios.nombre as ben_nombre',
                'beneficiarios.apellido_paterno as ben_apellido_paterno',
                'beneficiarios.apellido_materno as ben_apellido_materno',
                'beneficiarios.municipio_id',
            ]);

        if ($status !== 'all') {
            $q->where('salud360_assignments.active', $status === 'active');
        }
        if ($munId) {
            $q->where('beneficiarios.municipio_id', $munId);
        }
        if ($psicologoId) {
            $q->where('salud360_assignments.psicologo_id', $psicologoId);
        }

        $items = $q->orderByDesc('salud360_assignments.created_at')->paginate(20);

        return response()->json($items);
    }

    public function psicologos(Request $request)
    {
        $q = trim((string)$request->input('q'));
        $rows = DB::table('users as u')
            ->join('model_has_roles as r', function($j){
                $j->on('r.model_id','=','u.id')->where('r.model_type','=', \App\Models\User::class);
            })
            ->join('roles as ro','ro.id','=','r.role_id')
            ->leftJoin('salud360_assignments as a', function($j){
                $j->on('a.psicologo_id','=','u.id')->where('a.active','=',1);
            })
            ->where('ro.name','psicologo')
            ->when($q !== '', function($qb) use ($q){
                $like = '%'.$q.'%';
                $qb->where(function($w) use ($like){
                    $w->where('u.name','like',$like)->orWhere('u.email','like',$like);
                });
            })
            ->groupBy('u.id','u.name','u.email')
            ->select('u.id','u.name','u.email', DB::raw('COUNT(a.id) as cargas'))
            ->orderByDesc('cargas')
            ->orderBy('u.name')
            ->paginate(20);

        return response()->json($rows);
    }

    public function manageSessionView(Salud360Session $session)
    {
        Gate::authorize('update', $session);
        $session->loadMissing('beneficiario');
        $beneficiarioName = trim(($session->beneficiario->nombre ?? '').' '.($session->beneficiario->apellido_paterno ?? '').' '.($session->beneficiario->apellido_materno ?? ''));
        Log::info('Manage session view', ['session_id' => $session->id, 'user_id' => auth()->id()]);
        return view('s360.enc360.session_manage', [
            'session' => $session,
            'beneficiarioName' => $beneficiarioName,
        ]);
    }

    public function updateSession(Request $request, Salud360Session $session)
    {
        Gate::authorize('update', $session);

        $base = [
            'session_date' => ['required', 'date'],
            'next_session_date' => ['nullable', 'date', 'after:session_date'],
            'next_objective' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];

        if ($session->is_first) {
            $rules = $base + [
                'motivo_consulta' => ['required', 'string'],
                'riesgo_suicida' => ['required', 'boolean'],
                'uso_sustancias' => ['required', 'boolean'],
            ];
        } else {
            $rules = $base + [
                'motivo_consulta' => ['nullable', 'string'],
                'riesgo_suicida' => ['nullable', 'boolean'],
                'uso_sustancias' => ['nullable', 'boolean'],
            ];
        }

        $data = $request->validate($rules);

        $session->fill($data);
        $session->save(); // LogsActivity registrará diffs

        return redirect()->route('s360.enc360.sesiones.manage', $session)->with('status', 'Sesión actualizada');
    }
}

