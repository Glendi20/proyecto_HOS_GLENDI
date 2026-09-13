<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * La arquitectura federada usa dos conexiones físicamente independientes:
     * "default" (HOSPITAL) y "central" (CENTRAL, ver config/database.php). Sin
     * declarar esto, RefreshDatabase solo envuelve en transacción la conexión
     * por defecto (null) y las filas creadas en "central" (tenants, mpi_patients,
     * patient_hospital_links, identity_match_candidates) persistirían entre tests.
     */
    protected $connectionsToTransact = [null, 'central'];
}
