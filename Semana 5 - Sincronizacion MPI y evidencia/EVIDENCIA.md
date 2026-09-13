# EVIDENCIA.md — Módulo 03: Paciente local, búsqueda y vínculo con MPI CENTRAL

- **Estudiante:** Glendi Patricia Campos Orellana (`Glendi20`)
- **Rama:** `feature/asii-03-pacientes-glendi20`
- **Commit evaluado:** `df98ff0` (+ `config/tenancy.php`, ver "Estado pendiente" abajo)

## Comandos ejecutados y salida real

```
$ php -v
PHP 8.2.33 (cli) (built: Jul 28 2026 10:29:00) (ZTS Visual C++ 2019 x64)
Zend Engine v4.2.33, Copyright (c) Zend Technologies

$ php artisan --version
Laravel Framework 12.58.0

$ composer install
[...]
Generating optimized autoload files
> Illuminate\Foundation\ComposerScripts::postAutoloadDump
> @php artisan package:discover --ansi
  INFO  Discovering packages.
  laravel/pail, laravel/sail, laravel/tinker, nesbot/carbon, nunomaduro/collision,
  nunomaduro/termwind, spatie/laravel-permission, stancl/tenancy, tymon/jwt-auth ... DONE
(completado sin errores tras habilitar la extensión ext-fileinfo en php.ini — ver
"Problema encontrado y corregido" #3 más abajo)

$ php artisan key:generate
INFO  Application key set successfully.

$ php artisan jwt:secret --force
jwt-auth secret [...] set successfully.

$ php artisan migrate:fresh --seed
INFO  Preparing database.
INFO  Running migrations.
  0001_01_01_000000_create_tenants_table ................ DONE
  0001_01_01_000001_create_users_table ................... DONE
  0001_01_01_000002_create_cache_table ................... DONE
  0001_01_01_000003_create_jobs_table ..................... DONE
  0001_01_01_000004_create_permission_tables ............. DONE
  2026_04_26_100000_create_admission_catalogs ............ DONE
  2026_04_26_110000_create_admissions_and_appointments ... DONE
  2026_04_26_120000_create_emr_tables ..................... DONE
  2026_04_26_130000_create_laboratory_tables .............. DONE
  2026_04_26_140000_create_audit_and_notifications ........ DONE
  2026_08_19_000001_add_validation_to_lab_results ......... DONE
  2026_08_21_100000_add_mpi_federation_columns_to_patients_table ........ DONE
  2026_08_21_100050_scope_patients_code_uniqueness_to_tenant ............ DONE
  2026_08_21_100100_create_patient_sync_outbox_table ..................... DONE
  2026_08_21_100200_create_central_mpi_patients_table .................... DONE
  2026_08_21_100300_create_central_patient_hospital_links_table ......... DONE
  2026_08_21_100400_create_central_identity_match_candidates_table ...... DONE

INFO  Seeding database.
  Database\Seeders\RoleSeeder ............................ DONE
  Database\Seeders\TenantSeeder .......................... DONE
  Database\Seeders\MedicationCatalogSeeder ............... DONE
  Database\Seeders\DemoDataSeeder2026 .................... DONE
    → Demo 2026: Hospital General San Marcos (demo)
    → Demo 2026: Clínica Santa Elena (demo)
  Database\Seeders\Mod03PatientsDemoSeeder ............... DONE
    Mod03PatientsDemoSeeder: 2 pacientes demo (1 auto_linked, 1 manual_review).

$ php artisan test
Tests\Unit\Domain\Patients\DpiTest .............................. PASS (3)
Tests\Unit\Domain\Patients\LocalPatientTest ..................... PASS (4)
Tests\Unit\Domain\Patients\PatientDemographicsTest .............. PASS (3)
Tests\Unit\Domain\Patients\PatientMatchingPolicyTest ............ PASS (6)
Tests\Unit\ExampleTest .......................................... PASS (1)
Tests\Unit\Modulo20\LabResultDomainTest ......................... PASS (6)   [de otro módulo]
Tests\Feature\ExampleTest ....................................... FAIL (1)   [ver nota]
Tests\Feature\Modulo20\EloquentLabResultRepositoryIntegrationTest  PASS (2)   [de otro módulo]
Tests\Feature\Modulo20\ValidacionResultadoUseCaseTest ........... PASS (5)   [de otro módulo]
Tests\Feature\Patients\PostgresPatientIntegrationTest ........... WARN (4 skipped, esperado sin pgsql)
Tests\Feature\Patients\RegisterLocalPatientTest ................. PASS (6)
Tests\Feature\Patients\ReviewMatchCandidateTest ................. PASS (4)
Tests\Feature\Patients\SearchLocalPatientsTest .................. PASS (4)

Tests:    1 failed, 4 skipped, 44 passed (103 assertions)
Duration: 2.65s

$ php artisan route:list --path=api
POST       api/v1/auth/login .......................... AuthController@login
POST       api/v1/auth/logout ......................... AuthController@logout
GET|HEAD   api/v1/auth/me ............................. AuthController@me
POST       api/v1/auth/refresh ........................ AuthController@refresh
POST       api/v1/auth/register ....................... AuthController@register
GET|HEAD   api/v1/lab/results/pending ................. LabResultController@pending   [otro módulo]
POST       api/v1/lab/results/{id}/correct ............ LabResultController@correct   [otro módulo]
POST       api/v1/lab/results/{id}/reject ............. LabResultController@reject    [otro módulo]
POST       api/v1/lab/results/{id}/validate ........... LabResultController@validate  [otro módulo]
POST       api/v1/patients ............................. PatientController@store
GET|HEAD   api/v1/patients ............................. PatientController@index
POST       api/v1/patients/match-candidates/{matchCandidate}/resolve  PatientController@resolveMatchCandidate

Showing [12] routes

$ git status --short
?? config/tenancy.php

$ git worktree list
C:/Users/Glendi Orellana/Documents/sistema-hospitalario-integrado-SistenasII-2026  c3838ed [develop]
C:/Users/Glendi Orellana/Documents/shi-asii-03-pacientes                           df98ff0 [feature/asii-03-pacientes-glendi20]
```

**Las 30 pruebas de este módulo pasan (`Tests\Unit\Domain\Patients\*` y `Tests\Feature\Patients\*`, salvo 4 saltadas a propósito, ver abajo).**

### Único fallo del suite completo, no relacionado con este módulo

`Tests\Feature\ExampleTest > the application returns a successful response` falla porque
`public/build/manifest.json` no existe (nunca se corrió `npm run build`). Es la prueba genérica del
scaffold de Laravel sobre la vista `welcome` (frontend Vue), completamente ajena al alcance de este
módulo (API sin interfaz propia). No se intentó corregir por estar fuera de alcance.

## Estado pendiente antes de comitear/hacer push

`config/tenancy.php` quedó como archivo nuevo sin commitear (`git status --short` lo muestra). Debe
agregarse en un commit propio ("fix(tenancy): publicar config y fijar central_connection a la
conexión dedicada") — ver "Problema encontrado y corregido" #2 abajo para la justificación completa.

## Prueba de integración PostgreSQL real — pendiente de ejecutar

El servicio `postgresql-x64-17` está corriendo en esta máquina, pero esta sesión no creó las bases
de prueba ni ejecutó la suite contra PostgreSQL real. Comandos a correr antes de la entrega:

```bash
psql -U postgres -c "CREATE DATABASE shi_hospital_glendi_test;"
psql -U postgres -c "CREATE DATABASE shi_central_glendi_test;"

DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=shi_hospital_glendi_test \
DB_USERNAME=postgres DB_PASSWORD=postgres \
CENTRAL_DB_CONNECTION=pgsql CENTRAL_DB_HOST=127.0.0.1 CENTRAL_DB_PORT=5432 \
CENTRAL_DB_DATABASE=shi_central_glendi_test CENTRAL_DB_USERNAME=postgres CENTRAL_DB_PASSWORD=postgres \
php artisan test --filter=PostgresPatientIntegrationTest
```

También conviene repetir `migrate:fresh --seed` apuntando a PostgreSQL real (mismas variables sin
`--filter`) para tener evidencia del motor objetivo, no solo de SQLite.

## Problemas preexistentes encontrados y corregidos durante esta validación

Los tres no fueron introducidos por el diseño de este módulo; son gaps de configuración/código que
ya existían en la rama base y que solo se manifestaron al intentar ejecutar el proyecto de verdad.

1. **Fatal error en `app/Http/Middleware/JwtAuth.php`.** `use Tymon\JWTAuth\Facades\JWTAuth;`
   colisionaba en mayúsculas/minúsculas con `class JwtAuth` del mismo namespace — PHP los trata como
   el mismo símbolo y aborta la compilación ("Cannot declare class ... because the name is already
   in use"). Habría roto **todas** las rutas protegidas (`jwt.auth` es middleware transversal).
   Detectado con `php -l` antes de tener Composer instalado. Corregido con
   `use ... as JWTAuthFacade;`.
2. **`tenancy.database.central_connection` sin publicar.** El paquete `stancl/tenancy` nunca tuvo su
   config publicada; su default (`env('DB_CONNECTION', 'central')`) hacía que `Tenant` resolviera a
   la MISMA conexión que HOSPITAL, no a la conexión `central` dedicada de `config/database.php`. Con
   SQLite de un solo archivo esto era invisible; en las pruebas (dos SQLite `:memory:` separados) se
   manifestó como `SQLSTATE[HY000]: no such table: tenants` en las 14 pruebas de Feature de este
   módulo. Corregido publicando `config/tenancy.php` y fijando `'database.central_connection' =>
   'central'`. Detalle completo en `docs/modulos/mod03/ADR-001-arquitectura.md`, Decisión 2.
3. **Extensión PHP `fileinfo` deshabilitada.** `composer install` fallaba (`league/flysystem-local
   requires ext-fileinfo`) porque `php.ini` tenía `;extension=fileinfo` comentado. Se habilitó (el
   DLL ya estaba presente, solo faltaba la línea activa). Es un ajuste de entorno local, no de
   código del repositorio.

## Árbol de archivos entregados

```
app/Domain/Patients/
├── Exceptions/ (DuplicateDpiException, InvalidMatchCandidateSelectionException,
│                LocalPatientNotFoundException, MatchCandidateAlreadyResolvedException,
│                MatchCandidateNotFoundException)
├── Mpi/ (MatchDecision, MpiCandidate, PatientMatchingPolicy)
├── ValueObjects/ (Dpi, PatientDemographics, PatientUuid)
└── LocalPatient.php

app/Application/Patients/
├── DTO/ (RegisterPatientInput, RegisterPatientResult)
├── Exceptions/ (CentralUnavailableException)
├── Ports/ (LocalPatientRepository, MpiPatientRepository, PatientHospitalLinkRepository,
│           IdentityMatchCandidateRepository, PatientSyncOutboxRepository)
└── UseCases/ (RegisterLocalPatientUseCase, SearchLocalPatientsUseCase,
               ReviewMatchCandidateUseCase, SyncPendingPatientsUseCase)

app/Infrastructure/Patients/
├── Eloquent/ (5 modelos + 5 adaptadores Eloquent/PostgreSQL)
└── Fakes/ (5 dobles InMemory, mismo contrato que los adaptadores Eloquent)

app/Http/
├── Controllers/Api/V1/PatientController.php
├── Requests/Patients/ (RegisterLocalPatientRequest, SearchPatientsRequest, ResolveMatchCandidateRequest)
└── Resources/Patients/LocalPatientResource.php

app/Providers/PatientModuleServiceProvider.php
app/Console/Commands/SyncPendingPatientsCommand.php
config/tenancy.php (nuevo — fix de configuración, ver arriba)

database/migrations/2026_08_21_*.php (6 migraciones: columnas MPI en patients, uniqueness por
    tenant, outbox, mpi_patients, patient_hospital_links, identity_match_candidates)
database/factories/MpiPatientFactory.php
database/seeders/Mod03PatientsDemoSeeder.php

tests/Unit/Domain/Patients/ (4 clases, 16 pruebas — regla central + value objects + entidad)
tests/Feature/Patients/ (4 clases, 14 pruebas — registro, búsqueda, revisión MPI, integración PostgreSQL)
tests/Support/InteractsWithJwtApi.php

docs/modulos/mod03/
├── ESPECIFICACION.md
├── ADR-001-arquitectura.md
├── INFORME.md
└── diagramas/ (casos de uso, clases, secuencia ×3, componentes, ER)

docs/module-03/contrato-api.md (semana 5 — endpoints, payloads, respuestas, errores, permisos)
```

## Ajustes a contratos compartidos (documentados, ver ADR-001 Decisión 2)

| Archivo | Cambio | Por qué |
|---|---|---|
| `database/migrations/0001_01_01_000000_create_tenants_table.php` | Declara `connection = 'central'` explícitamente | Necesario para que `Tenant` (Stancl) viva realmente en CENTRAL. |
| `config/tenancy.php` (nuevo) | Publica la config de Stancl y fija `central_connection = 'central'` | El default del paquete apuntaba a la misma conexión que HOSPITAL (ver "Problemas encontrados" #2). |
| `database/migrations/0001_01_01_000001_create_users_table.php` | Se retira la FK física `users.tenant_id → tenants.id` (queda índice) | Con `tenants` en CENTRAL y `users` en HOSPITAL, esa FK sería entre bases (prohibido). No cambia comportamiento de aplicación. |
| `app/Models/Patient.php` | Agrega columnas MPI + genera `uuid` al crear | Necesario para el vínculo lógico con CENTRAL. |
| `app/Http/Middleware/JwtAuth.php` | Renombra el alias `use` de `JWTAuth` | Corrige el fatal error preexistente #1. |
| `routes/api.php` | Agrega 3 rutas de `patients` | Nuevas, no modifica rutas existentes. |
| `config/database.php`, `.env.example`, `phpunit.xml`, `tests/TestCase.php` | Añaden config de conexión `central` independiente y su equivalente en pruebas | Necesario para que CENTRAL sea una base físicamente distinta de HOSPITAL, no solo un alias. |

## Pull Request y commits

- `git log --oneline --decorate --graph -n 20` muestra el historial completo, incluyendo el merge de
  `develop` (`660cf82 solucionando conflictos`) y el commit de la semana 5 (`df98ff0`).
- Falta comitear `config/tenancy.php` (ver "Estado pendiente" arriba) antes de abrir el PR.
