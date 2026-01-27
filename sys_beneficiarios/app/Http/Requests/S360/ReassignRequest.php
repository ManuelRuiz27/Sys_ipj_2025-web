<?php

namespace App\Http\Requests\S360;

use App\Models\Salud360Assignment;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class ReassignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('s360.enc360.assign') && $this->user()?->hasRole('encargado_360');
    }

    public function rules(): array
    {
        return [
            'psicologo_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
            $beneficiario = $this->route('beneficiario');
            $current = $beneficiario ? Salud360Assignment::where('beneficiario_id', $beneficiario->id)->where('active', true)->first() : null;
            if (! $current) {
                $v->errors()->add('beneficiario_id', 'No existe una asignación activa para este beneficiario.');
            }
            $id = (int) $this->input('psicologo_id');
            if ($id) {
                $u = User::find($id);
                if (! $u || ! $u->hasRole('psicologo')) {
                    $v->errors()->add('psicologo_id', 'El usuario debe tener rol psicólogo.');
                }
            }
        });
    }
}

