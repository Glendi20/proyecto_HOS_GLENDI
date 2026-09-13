<?php

namespace App\Http\Requests\Patients;

use Illuminate\Foundation\Http\FormRequest;

class SearchPatientsRequest extends FormRequest
{
    public function authorize(): bool
    {
        // RF-05: Recepcionista, Admin, Médico y Enfermera pueden buscar (solo lectura).
        $user = $this->user('api');

        return $user !== null
            && $user->hasAnyRole(['Recepcionista', 'Admin', 'Médico', 'Enfermera']);
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:150'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
