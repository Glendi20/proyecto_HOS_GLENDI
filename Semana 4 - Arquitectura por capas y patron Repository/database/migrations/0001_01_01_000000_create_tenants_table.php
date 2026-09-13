<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Ajuste mínimo a un contrato compartido (documentado en docs/modulos/mod03/ADR-001-arquitectura.md
// y en el Pull Request): App\Models\Tenant extiende Stancl\Tenancy\...\Tenant, que SIEMPRE resuelve
// a la conexión "central" (por eso config/database.php ya declaraba esa conexión). Esta migración
// original no lo hacía explícito y creaba la tabla en la conexión por defecto; "funcionaba" solo
// porque en desarrollo central y default apuntan al mismo archivo SQLite. Con CENTRAL como base
// físicamente independiente (línea base de esta actividad), tenants debe migrarse en "central" de
// verdad. Ningún otro módulo depende de en qué conexión física vive `tenants`, solo de que el
// modelo Tenant siga funcionando igual (y sigue: Stancl ya la resolvía así).
return new class extends Migration
{
    protected $connection = 'central';

    public function up(): void
    {
        Schema::connection('central')->create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('data')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('central')->dropIfExists('tenants');
    }
};
