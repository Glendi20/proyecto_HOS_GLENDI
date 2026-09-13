<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// CENTRAL: Master Patient Index (MPI). Identidad de red, independiente de cualquier
// hospital. Nunca almacena el expediente clínico completo, solo lo mínimo para
// desambiguar identidad (nombre normalizado, fecha de nacimiento, DPI normalizado).
return new class extends Migration
{
    /** Esta migración corre contra la base CENTRAL, no la HOSPITAL por defecto. */
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->create('mpi_patients', function (Blueprint $table) {
            $table->char('id', 36)->primary(); // global_id
            $table->string('full_name_normalized', 200)->index();
            $table->date('birth_date');
            $table->string('gender', 10);
            $table->string('dpi_normalized', 13)->nullable()->index();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['dpi_normalized', 'birth_date'], 'idx_mpi_patients_dpi_birth');
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('mpi_patients');
    }
};
