<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// CENTRAL: vínculo lógico entre un local_patient (HOSPITAL) y un mpi_patient (CENTRAL).
// tenant_id -> tenants.id SÍ es FK real porque ambas tablas viven en CENTRAL.
// local_patient_uuid es un UUID lógico hacia la base HOSPITAL: nunca una FK remota.
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->create('patient_hospital_links', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('tenant_id', 36);
            $table->char('local_patient_uuid', 36); // UUID lógico -> patients.uuid en HOSPITAL
            $table->char('mpi_patient_id', 36);
            $table->string('status', 20); // auto_linked|manual_confirmed|revoked
            $table->char('linked_by', 36)->nullable(); // users.id, sin FK remota
            $table->timestamp('linked_at')->useCurrent();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();
            $table->foreign('mpi_patient_id')->references('id')->on('mpi_patients')->restrictOnDelete();

            $table->index(['tenant_id', 'local_patient_uuid'], 'idx_links_tenant_local_patient');
            $table->index('mpi_patient_id', 'idx_links_mpi_patient');
        });

        // Un local_patient solo puede tener UN vínculo activo (no revocado) a la vez.
        // PostgreSQL soporta índices únicos parciales; en SQLite (tests/desarrollo rápido)
        // se omite y la regla la sigue garantizando la capa de aplicación.
        if (DB::connection('central')->getDriverName() === 'pgsql') {
            DB::connection('central')->statement(
                'CREATE UNIQUE INDEX uq_links_active_per_local_patient
                 ON patient_hospital_links (tenant_id, local_patient_uuid)
                 WHERE status <> \'revoked\''
            );
        }
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('patient_hospital_links');
    }
};
