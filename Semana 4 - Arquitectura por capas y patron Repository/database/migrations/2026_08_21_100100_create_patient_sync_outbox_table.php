<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Outbox HOSPITAL -> CENTRAL (patrón outbox/inbox exigido por la línea base arquitectónica).
// Vive en la base HOSPITAL (conexión "default"): el alta local se confirma primero y este
// registro garantiza que, si CENTRAL no respondió, el evento se reintenta después sin
// perder el hecho de que el paciente ya existe localmente. event_id es único -> idempotencia.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_sync_outbox', function (Blueprint $table) {
            $table->char('event_id', 36)->primary();
            $table->char('tenant_id', 36)->index();
            $table->char('local_patient_uuid', 36)->index();
            $table->unsignedInteger('aggregate_version')->default(1);
            $table->string('event_type', 60);
            $table->json('payload');
            $table->string('status', 20)->default('pending'); // pending|sent|failed
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'tenant_id'], 'idx_outbox_status_tenant');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_sync_outbox');
    }
};
