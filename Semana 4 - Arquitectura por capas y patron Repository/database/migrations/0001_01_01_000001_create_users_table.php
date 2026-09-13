<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            // Referencia lógica a tenants.id (base CENTRAL), no una FK física: desde que
            // `tenants` vive en la conexión "central" (ver esa migración), una FK real aquí
            // sería una FK entre bases, prohibida por la arquitectura federada de esta
            // actividad (docs/modulos/mod03/ADR-001-arquitectura.md). Ajuste mínimo a un
            // contrato compartido, documentado en el PR: no cambia ningún comportamiento de
            // aplicación (User::tenant() sigue siendo un belongsTo por valor de columna),
            // solo retira una restricción de integridad a nivel de motor que ya no aplica.
            $table->string('tenant_id')->index();
            $table->string('name');
            $table->string('email');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();

            $table->unique(['tenant_id', 'email']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
