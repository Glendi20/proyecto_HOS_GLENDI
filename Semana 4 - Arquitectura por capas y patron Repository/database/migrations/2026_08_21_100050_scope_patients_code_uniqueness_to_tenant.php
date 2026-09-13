<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ajuste mínimo a un contrato compartido (documentado en docs/modulos/mod03/ADR-001-arquitectura.md
// y en el Pull Request): la migración original de `patients` declaró `code` como único a nivel
// GLOBAL, pero RF-02 exige un correlativo único POR HOSPITAL (tenant_id) tipo PAC-0001 — con la
// restricción global, dos hospitales nunca podrían tener ambos un "PAC-0001". Ningún otro módulo
// depende de la unicidad global de `code` (solo usan el FK patient_id, ver vista-arquitectonica.md),
// por lo que este cambio no rompe contratos ajenos.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropUnique('patients_code_unique');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->unique(['tenant_id', 'code'], 'uq_patients_tenant_code');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropUnique('uq_patients_tenant_code');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->unique('code', 'patients_code_unique');
        });
    }
};
