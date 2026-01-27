<?php

namespace App\Http\Controllers\S360;

use App\Http\Controllers\Controller;
use App\Models\Salud360Assignment;
use App\Models\Salud360Session;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Notifications\NewEncargadoAccount;
use App\Services\S360MetricsService;

class S360BienestarController extends Controller
{
    public function __construct(private S360MetricsService $metrics)
    {
    }
    public function view()
    {
        return view('s360.bienestar.dashboard');
    }
    public function dash(Request $request)
    {
        $now = Carbon::now();
        $startWeek = (clone $now)->startOfWeek();

        $totalEnc360 = User::role('encargado_360')->count();
        $totalPsicologos = User::role('psicologo')->count();
        $asignacionesActivas = Salud360Assignment::where('active', true)->count();
        $sesionesSemana = Salud360Session::whereBetween('session_date', [$startWeek, $now])->count();
        $promedioCargaPorPsicologo = $totalPsicologos > 0 ? round($asignacionesActivas / $totalPsicologos, 2) : 0;

        // Top psicólogos por carga
        $topP = Salud360Assignment::query()->where('active', true)
            ->select('psicologo_id', DB::raw('COUNT(*) as c'))
            ->groupBy('psicologo_id')
            ->orderByDesc('c')
            ->limit(5)
            ->get();
        $namesP = User::whereIn('id', $topP->pluck('psicologo_id'))->pluck('name', 'id');
        $topPsicologos = $topP->map(fn($r) => [
            'psicologo_id' => (int) $r->psicologo_id,
            'name' => $namesP[$r->psicologo_id] ?? (string) $r->psicologo_id,
            'cargas' => (int) $r->c,
        ])->values();

        // Top encargados por asignaciones realizadas
        $topE = Salud360Assignment::query()
            ->select('assigned_by', DB::raw('COUNT(*) as c'))
            ->groupBy('assigned_by')
            ->orderByDesc('c')
            ->limit(5)
            ->get();
        $namesE = User::whereIn('id', $topE->pluck('assigned_by'))->pluck('name', 'id');
        $topEncargados = $topE->map(fn($r) => [
            'user_id' => (int) $r->assigned_by,
            'name' => $namesE[$r->assigned_by] ?? (string) $r->assigned_by,
            'asignaciones' => (int) $r->c,
        ])->values();

        // Enlaces a dashboards disponibles
        $dashboards = [
            'enc360' => route('s360.enc360.dash'),
            'bienestar' => route('s360.bienestar.dash'),
            'admin' => route('s360.admin.dash'),
        ];

        // Recalcular KPIs con servicio
        $asignacionesActivas = $this->metrics->activePatientsCount();
        $sesionesSemana = $this->metrics->sessionsThisWeekCount();
        $topPsicologos = $this->metrics->workloadsByPsychologist([], 5, true);
        $promedioCargaPorPsicologo = $totalPsicologos > 0 ? round($asignacionesActivas / $totalPsicologos, 2) : 0;

        return response()->json([
            'usuarios' => [
                'encargados_360' => $totalEnc360,
                'psicologos' => $totalPsicologos,
            ],
            'asignacionesActivas' => $asignacionesActivas,
            'sesionesSemana' => $sesionesSemana,
            'promedioCargaPorPsicologo' => $promedioCargaPorPsicologo,
            'topPsicologos' => $topPsicologos,
            'topEncargados' => $topEncargados,
            'dashboards' => $dashboards,
        ]);
    }

    public function latestSessions(Request $request)
    {
        $q = trim((string) $request->input('q'));
        $assignedBy = $request->integer('assigned_by'); // filtrar por encargado_360 que asignó
        $rows = DB::table('salud360_sessions as s')
            ->join('beneficiarios as b', 's.beneficiario_id', '=', 'b.id')
            ->join('users as up', 's.psicologo_id', '=', 'up.id')
            ->leftJoin('salud360_assignments as a', 'a.beneficiario_id', '=', 'b.id')
            ->select('s.id','s.session_date','s.is_first','b.id as beneficiario_id', DB::raw("TRIM(b.nombre || ' ' || b.apellido_paterno || ' ' || b.apellido_materno) as beneficiario"), 'up.id as psicologo_id','up.name as psicologo')
            ->when($assignedBy, fn($q2)=>$q2->where('a.assigned_by', $assignedBy))
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
        $now = now()->startOfDay();
        $in7 = now()->addDays(7)->endOfDay();
        $q = trim((string) $request->input('q'));
        $assignedBy = $request->integer('assigned_by');
        $rows = DB::table('salud360_sessions as s')
            ->join('beneficiarios as b', 's.beneficiario_id', '=', 'b.id')
            ->join('users as up', 's.psicologo_id', '=', 'up.id')
            ->leftJoin('salud360_assignments as a', 'a.beneficiario_id', '=', 'b.id')
            ->select('s.id','s.next_session_date','b.id as beneficiario_id', DB::raw("TRIM(b.nombre || ' ' || b.apellido_paterno || ' ' || b.apellido_materno) as beneficiario"), 'up.id as psicologo_id','up.name as psicologo')
            ->whereNotNull('s.next_session_date')
            ->whereBetween('s.next_session_date', [$now, $in7])
            ->when($assignedBy, fn($q2)=>$q2->where('a.assigned_by', $assignedBy))
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

    public function storeEncargado360(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
        ]);

        $user = new User();
        $user->name = $data['name'];
        $user->email = $data['email'];
        $temp = Str::random(12);
        $user->password = $temp;
        $user->email_verified_at = now();
        $user->save();
        $user->assignRole('encargado_360');
        try {
            $user->notify(new NewEncargadoAccount($temp));
        } catch (\Throwable $e) {
            // no romper flujo si falla email
        }

        return response()->json([
            'ok' => true,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => 'encargado_360',
            ],
        ], 201);
    }
}
