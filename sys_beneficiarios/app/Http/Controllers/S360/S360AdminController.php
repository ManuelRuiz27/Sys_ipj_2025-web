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
use Illuminate\Support\Facades\Notification;
use App\Notifications\NewEncargadoAccount;

class S360AdminController extends Controller
{
    public function dash(Request $request)
    {
        $now = Carbon::now();
        $startWeek = (clone $now)->startOfWeek();
        $in7 = (clone $now)->addDays(7)->endOfDay();

        // Asignaciones activas
        $totalAsignados = Salud360Assignment::where('active', true)->count();

        // Sesiones de la semana
        $sesionesSemana = Salud360Session::whereBetween('session_date', [$startWeek, $now])->count();

        // Próximos a vencer (siguiente cita <= 7 días)
        $proximosAVencer = Salud360Session::whereNotNull('next_session_date')
            ->whereBetween('next_session_date', [$now->copy()->startOfDay(), $in7])
            ->distinct('beneficiario_id')->count('beneficiario_id');

        // Top psicólogos por carga
        $topRaw = Salud360Assignment::query()->where('active', true)
            ->select('psicologo_id', DB::raw('COUNT(*) as c'))
            ->groupBy('psicologo_id')
            ->orderByDesc('c')
            ->limit(5)
            ->get();
        $names = User::whereIn('id', $topRaw->pluck('psicologo_id'))->pluck('name', 'id');
        $topPsicologos = $topRaw->map(fn($r) => [
            'psicologo_id' => (int) $r->psicologo_id,
            'name' => $names[$r->psicologo_id] ?? (string) $r->psicologo_id,
            'cargas' => (int) $r->c,
        ])->values();

        // Top encargados_360 por asignaciones realizadas (assigned_by)
        $topEncRaw = Salud360Assignment::query()
            ->select('assigned_by', DB::raw('COUNT(*) as c'))
            ->groupBy('assigned_by')
            ->orderByDesc('c')
            ->limit(5)
            ->get();
        $encNames = User::whereIn('id', $topEncRaw->pluck('assigned_by'))->pluck('name', 'id');
        $topEncargados = $topEncRaw->map(fn($r) => [
            'user_id' => (int) $r->assigned_by,
            'name' => $encNames[$r->assigned_by] ?? (string) $r->assigned_by,
            'asignaciones' => (int) $r->c,
        ])->values();

        // Resumen de usuarios por rol
        $resumenUsuarios = [
            'encargados_360' => User::role('encargado_360')->count(),
            'encargados_bienestar' => User::role('encargado_bienestar')->count(),
            'psicologos' => User::role('psicologo')->count(),
        ];

        return response()->json([
            'totalAsignados' => $totalAsignados,
            'sesionesSemana' => $sesionesSemana,
            'proximosAVencer' => $proximosAVencer,
            'topPsicologos' => $topPsicologos,
            'topEncargados' => $topEncargados,
            'resumenUsuarios' => $resumenUsuarios,
        ]);
    }

    public function storeUsers(Request $request)
    {
        $data = $request->validate([
            'users' => ['sometimes', 'array'],
            'users.*.name' => ['required_with:users', 'string', 'max:255'],
            'users.*.email' => ['required_with:users', 'email', 'max:255', 'unique:users,email'],
            'users.*.role' => ['required_with:users', 'in:encargado_360,encargado_bienestar'],
            // single user payload alternative
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', 'unique:users,email'],
            'role' => ['sometimes', 'in:encargado_360,encargado_bienestar'],
        ]);

        $payloads = $data['users'] ?? [];
        if (empty($payloads) && isset($data['name'], $data['email'], $data['role'])) {
            $payloads[] = ['name' => $data['name'], 'email' => $data['email'], 'role' => $data['role']];
        }
        if (empty($payloads)) {
            return response()->json(['message' => 'No se proporcionaron usuarios.'], 422);
        }

        $created = [];
        foreach ($payloads as $u) {
            $tempPassword = Str::random(12);
            $user = new User();
            $user->name = $u['name'];
            $user->email = $u['email'];
            $user->password = $tempPassword; // hashed by cast
            $user->email_verified_at = now();
            $user->save();
            $user->assignRole($u['role']);
            try {
                $user->notify(new NewEncargadoAccount($tempPassword));
            } catch (\Throwable $e) {
                // loggear pero no romper flujo
            }
            $created[] = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $u['role'],
            ];
        }

        return response()->json(['ok' => true, 'users' => $created], 201);
    }
}
