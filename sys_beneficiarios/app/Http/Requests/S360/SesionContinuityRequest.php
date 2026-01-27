<?php

namespace App\Http\Requests\S360;

use App\Models\Beneficiario;
use App\Models\Salud360Session;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SesionContinuityRequest extends FormRequest
{
    public function authorize(): bool
    {
        $beneficiario = Beneficiario::find($this->input('beneficiario_id'));
        return $beneficiario ? Gate::allows('create', [\App\Models\Salud360Session::class, $beneficiario]) : false;
    }

    public function rules(): array
    {
        return [
            'beneficiario_id' => ['required', 'uuid', 'exists:beneficiarios,id'],
            'session_date' => ['required', 'date'],
            'is_first' => ['nullable', 'boolean'],
            'next_session_date' => ['nullable', 'date', 'after:session_date'],
            'next_objective' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $beneficiarioId = $this->input('beneficiario_id');
            if ($beneficiarioId && ! Salud360Session::where('beneficiario_id', $beneficiarioId)->exists()) {
                $v->errors()->add('beneficiario_id', 'Debe existir una sesión inicial.');
            }
        });
    }
}

