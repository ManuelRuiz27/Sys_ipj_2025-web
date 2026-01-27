<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBeneficiarioRequest;
use App\Models\Beneficiario;
use App\Models\Municipio;
use Illuminate\Http\Request;
use Spatie\Activitylog\Models\Activity;

class MisRegistrosController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Beneficiario::class);
        $items = Beneficiario::with('municipio')
            ->where('created_by', $request->user()->uuid)
            ->orderByDesc('created_at')
            ->paginate(15);
        return view('mis_registros.index', compact('items'));
    }

    public function show(Beneficiario $beneficiario)
    {
        $this->authorize('view', $beneficiario);
        $activities = Activity::forSubject($beneficiario)->latest()->limit(10)->get();
        return view('mis_registros.show', compact('beneficiario','activities'));
    }

    public function edit(Beneficiario $beneficiario)
    {
        $this->authorize('update', $beneficiario);
        $municipios = Municipio::orderBy('nombre')->pluck('nombre','id');
        $domicilio = $beneficiario->domicilio;
        return view('mis_registros.edit', compact('beneficiario','municipios','domicilio'));
    }

    public function update(UpdateBeneficiarioRequest $request, Beneficiario $beneficiario)
    {
        $this->authorize('update', $beneficiario);
        $data = $request->validated();
        $beneficiario->fill($data)->save();

        $dom = $data['domicilio'] ?? [];
        $d = $beneficiario->domicilio ?: new \App\Models\Domicilio(['id' => (string) \Illuminate\Support\Str::uuid(), 'beneficiario_id' => $beneficiario->id]);
        $d->fill([
            'calle' => $dom['calle'] ?? '',
            'numero_ext' => $dom['numero_ext'] ?? '',
            'numero_int' => $dom['numero_int'] ?? null,
            'colonia' => $dom['colonia'] ?? '',
            'municipio' => $dom['municipio'] ?? '',
            'codigo_postal' => $dom['codigo_postal'] ?? '',
            'seccional' => $dom['seccional'] ?? '',
        ])->save();

        return redirect()->route('mis-registros.show', $beneficiario)->with('status', 'Actualizado correctamente');
    }
}
