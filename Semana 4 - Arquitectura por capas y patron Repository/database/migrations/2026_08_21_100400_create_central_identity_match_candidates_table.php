<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// CENTRAL: coincidencias MPI ambiguas pendientes de revisión humana. Nunca se
// resuelven solas; existen exactamente para cumplir la regla "nunca fusiona
// silenciosamente ni mueve el expediente fuera del hospital fuente".
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->create('identity_match_candidates', function (Blueprint $table) {
            $table->char('id', 36)->primary();
            $table->char('tenant_id', 36);
            $table->char('local_patient_uuid', 36); // UUID lógico -> patients.uuid en HOSPITAL
            $table->json('candidates'); // [{mpi_patient_id, score, matched_fields}, ...]
            $table->string('reason', 255);
            $table->string('status', 20)->default('pending'); // pending|confirmed|rejected
            $table->char('reviewed_by', 36)->nullable(); // users.id, sin FK remota
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->restrictOnDelete();

            $table->index(['tenant_id', 'status'], 'idx_match_candidates_tenant_status');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('identity_match_candidates');
    }
};
