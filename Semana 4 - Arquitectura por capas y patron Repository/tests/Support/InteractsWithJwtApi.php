<?php

namespace Tests\Support;

use App\Models\Tenant;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * Helper de pruebas: las rutas de este módulo pasan por los middlewares reales
 * `tenant` + `jwt.auth`, así que un test de feature necesita un JWT firmado de
 * verdad (no basta con $this->actingAs()) y la cabecera X-Tenant-ID.
 */
trait InteractsWithJwtApi
{
    /**
     * @return array{Authorization: string, X-Tenant-ID: string}
     */
    protected function apiHeadersFor(User $user, Tenant $tenant): array
    {
        $token = JWTAuth::fromUser($user);

        return [
            'Authorization' => "Bearer {$token}",
            'X-Tenant-ID' => (string) $tenant->id,
        ];
    }
}
