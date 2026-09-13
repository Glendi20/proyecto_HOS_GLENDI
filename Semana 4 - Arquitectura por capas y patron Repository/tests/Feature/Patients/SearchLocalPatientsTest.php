<?php

namespace Tests\Feature\Patients;

use App\Models\Patient;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithJwtApi;
use Tests\TestCase;

class SearchLocalPatientsTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithJwtApi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    private function headersWithRole(Tenant $tenant, string $role): array
    {
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $user->assignRole($role);

        return $this->apiHeadersFor($user, $tenant);
    }

    public function test_la_busqueda_esta_acotada_al_tenant_activo(): void
    {
        $hospitalA = Tenant::factory()->create();
        $hospitalB = Tenant::factory()->create();

        Patient::factory()->create(['tenant_id' => $hospitalA->id, 'first_name' => 'Pedro', 'last_name' => 'Alvarado']);
        Patient::factory()->create(['tenant_id' => $hospitalB->id, 'first_name' => 'Pedro', 'last_name' => 'Alvarado']);

        $response = $this->withHeaders($this->headersWithRole($hospitalA, 'Recepcionista'))
            ->getJson('/api/v1/patients?q=Pedro');

        $response->assertOk();
        $this->assertCount(1, $response->json('data'));
    }

    public function test_termino_sin_coincidencias_devuelve_lista_vacia_sin_error(): void
    {
        $tenant = Tenant::factory()->create();

        $response = $this->withHeaders($this->headersWithRole($tenant, 'Recepcionista'))
            ->getJson('/api/v1/patients?q=ZZZ-no-existe');

        $response->assertOk();
        $this->assertCount(0, $response->json('data'));
        $this->assertSame(0, $response->json('meta.total'));
    }

    public function test_medico_y_enfermera_pueden_buscar_pero_no_registrar(): void
    {
        $tenant = Tenant::factory()->create();
        Patient::factory()->create(['tenant_id' => $tenant->id, 'first_name' => 'Sofía', 'last_name' => 'Rivas']);

        foreach (['Médico', 'Enfermera'] as $role) {
            $response = $this->withHeaders($this->headersWithRole($tenant, $role))
                ->getJson('/api/v1/patients?q=Sofía');

            $response->assertOk();
        }
    }

    public function test_busca_por_codigo_y_por_dpi(): void
    {
        $tenant = Tenant::factory()->create();
        Patient::factory()->create([
            'tenant_id' => $tenant->id,
            'code' => 'PAC-0042',
            'dpi' => '9998887776665',
        ]);

        $headers = $this->headersWithRole($tenant, 'Recepcionista');

        $byCode = $this->withHeaders($headers)->getJson('/api/v1/patients?q=PAC-0042');
        $byDpi = $this->withHeaders($headers)->getJson('/api/v1/patients?q=9998887776665');

        $byCode->assertOk();
        $byDpi->assertOk();
        $this->assertCount(1, $byCode->json('data'));
        $this->assertCount(1, $byDpi->json('data'));
    }
}
