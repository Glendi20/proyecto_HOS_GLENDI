<?php

namespace App\Http\Requests\Patients;

use Illuminate\Foundation\Http\FormRequest;

class ResolveMatchCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        // La revisión de identidad ambigua es una decisión administrativa/operativa;
        // se restringe igual que el alta (Recepcionista/Admin).
        $user = $this->user('api');

        return $user !== null && $user->hasAnyRole(['Recepcionista', 'Admin']);
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:confirm,reject'],
            'mpi_patient_id' => ['required_if:decision,confirm', 'nullable', 'string'],
        ];
    }
}
