<?php

namespace App\Http\Controllers;

use App\Models\Beneficiario;
use App\Models\Domicilio;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class DomicilioController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->get('q');
        $domicilios = Domicilio::with('beneficiario')
            ->when($q, function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('calle', 'like', "%$q%")
                        ->orWhere('colonia', 'like', "%$q%")
                        ->orWhere('municipio', 'like', "%$q%")
                        ->orWhere('seccional', 'like', "%$q%")
                        ->orWhere('codigo_postal', 'like', "%$q%");
                });
            })
            ->orderBy('created_at','desc')
            ->paginate(15)
            ->withQueryString();

        return view('domicilios.index', compact('domicilios','q'));
    }

    public function create()
    {
        $beneficiarios = Beneficiario::orderBy('nombre')
            ->select(['id','nombre','apellido_paterno','apellido_materno','folio_tarjeta'])
            ->limit(100)
            ->get();
        return view('domicilios.create', compact('beneficiarios'));
    }

    public function store(Request $request)
    {
        $data = $this->validateData($request);

        $domicilio = new Domicilio($data);
        $domicilio->id = (string) Str::uuid();
        $domicilio->save();

        return redirect()->route('domicilios.index')->with('status', 'Domicilio creado correctamente');
    }

    public function edit(Domicilio $domicilio)
    {
        $beneficiarios = Beneficiario::orderBy('nombre')
            ->select(['id','nombre','apellido_paterno','apellido_materno','folio_tarjeta'])
            ->limit(100)
            ->get();
        return view('domicilios.edit', compact('domicilio','beneficiarios'));
    }

    public function update(Request $request, Domicilio $domicilio)
    {
        $data = $this->validateData($request, $domicilio);
        $domicilio->fill($data);
        $domicilio->save();
        return redirect()->route('domicilios.index')->with('status', 'Domicilio actualizado correctamente');
    }

    public function destroy(Domicilio $domicilio)
    {
        $domicilio->delete();
        return redirect()->route('domicilios.index')->with('status', 'Domicilio eliminado');
    }

    protected function validateData(Request $request, ?Domicilio $domicilio = null): array
    {
        return $request->validate([
            'beneficiario_id' => ['required', Rule::exists('beneficiarios','id')],
            'calle' => ['required','string','max:255'],
            'numero_ext' => ['required','string','max:50'],
            'numero_int' => ['nullable','string','max:50'],
            'colonia' => ['required','string','max:255'],
            'municipio' => ['required','string','max:255'],
            'codigo_postal' => ['required','string','max:20'],
            'seccional' => ['required','string','max:255'],
        ]);
    }
}

