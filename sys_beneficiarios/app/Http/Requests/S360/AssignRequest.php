<?php

namespace App\Http\Requests\S360;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('s360.enc360.assign') && $this->user()?->hasRole('encargado_360');
    }

    public function rules(): array
    {
        return [
            'beneficiario_id' => ['required', 'uuid', 'exists:beneficiarios,id'],
            'psicologo_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($v) {
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

