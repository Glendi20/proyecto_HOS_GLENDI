<?php

namespace App\Http\Requests\Patients;

use Illuminate\Foundation\Http\FormRequest;

class RegisterLocalPatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        // RF-09: solo Recepcionista/Admin pueden registrar. RBAC completo es del
        // módulo #2; aquí se aplica el chequeo mínimo necesario para no exponer
        // el alta a roles de solo lectura, igual que el resto del proyecto lo hace
        // hoy (no hay policies de Spatie registradas todavía para Patient).
        $user = $this->user('api');

        return $user !== null && $user->hasAnyRole(['Recepcionista', 'Admin']);
    }

    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', 'in:M,F,otro'],
            'dpi' => ['nullable', 'digits:13'],
        ];
    }

    public function messages(): array
    {
        return [
            'dpi.digits' => 'El DPI debe tener exactamente 13 dígitos numéricos.',
            'birth_date.before_or_equal' => 'La fecha de nacimiento no puede ser futura.',
        ];
    }
}
