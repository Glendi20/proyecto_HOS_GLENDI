<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// Módulo 03 — Paciente local, búsqueda y vínculo con MPI CENTRAL (Glendi Campos Orellana).
// Extiende la tabla `patients` (HOSPITAL) sin romper el contrato que ya consumen otros
// módulos (admisión, EMR, alergias): solo agrega columnas nuevas, todas nullable/con
// default seguro. `uuid` es el identificador lógico que CENTRAL puede referenciar desde
// patient_hospital_link.local_patient_uuid (nunca el id autoincremental interno).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->char('uuid', 36)->nullable()->after('id');
            $table->char('global_id', 36)->nullable()->after('uuid');
            $table->string('mpi_link_status', 20)->default('pending')->after('global_id');
            $table->timestamp('mpi_synced_at')->nullable()->after('mpi_link_status');
        });

        // Backfill de uuid para filas existentes (seeds previos) antes de exigir unicidad.
        foreach (DB::table('patients')->whereNull('uuid')->select('id')->cursor() as $row) {
            DB::table('patients')->where('id', $row->id)->update([
                'uuid' => (string) Str::uuid(),
            ]);
        }

        Schema::table('patients', function (Blueprint $table) {
            $table->unique('uuid', 'uq_patients_uuid');
            $table->index(['tenant_id', 'mpi_link_status'], 'idx_patients_tenant_mpi_status');
            $table->index('global_id', 'idx_patients_global_id');
        });
    }

    public function down(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->dropIndex('idx_patients_tenant_mpi_status');
            $table->dropIndex('idx_patients_global_id');
            $table->dropUnique('uq_patients_uuid');
            $table->dropColumn(['uuid', 'global_id', 'mpi_link_status', 'mpi_synced_at']);
        });
    }
};
