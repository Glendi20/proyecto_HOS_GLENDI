# EVIDENCIA.md — Módulo 03: Paciente local, búsqueda y vínculo con MPI CENTRAL

- **Estudiante:** Glendi Patricia Campos Orellana (`Glendi20`)
- **Rama:** `feature/asii-03-pacientes-glendi20`
- **Commit evaluado:** _(completar con `git rev-parse HEAD` al momento de abrir el PR)_

## ⚠️ Aviso de entorno de esta entrega

El código, las migraciones, las pruebas y la documentación de este módulo se escribieron y se
**verificaron sintácticamente** (`php -l` sobre cada archivo, sin errores) en un entorno sin acceso
a internet y sin Composer instalado, por lo que **no fue posible ejecutar `composer install`,
`php artisan migrate` ni `php artisan test`** en esa sesión. Antes de abrir el Pull Request, debo
ejecutar yo misma los comandos de la sección siguiente y pegar aquí su salida real. No se
fabricó ninguna salida de comando: donde no se ejecutó, se indica explícitamente "PENDIENTE".

PHP 8.2.33 (con `pdo_pgsql`, `pgsql`, `pdo_sqlite`) y un servicio **PostgreSQL 17** ya están
disponibles en la máquina de desarrollo (`postgresql-x64-17`), suficientes para completar la
validación descrita abajo.

## Comandos a ejecutar y su estado

```bash
php -v
# PENDIENTE de pegar salida real (entorno de esta sesión: PHP 8.2.33 confirmado, ver abajo)

composer install
# PENDIENTE — no se pudo ejecutar sin acceso a internet/Composer en esta sesión.

php artisan --version
# PENDIENTE (requiere vendor/autoload.php de composer install)

# Base de prueba HOSPITAL + CENTRAL en PostgreSQL real (el servicio ya corre en esta máquina):
psql -U postgres -c "CREATE DATABASE shi_hospital_glendi;"
psql -U postgres -c "CREATE DATABASE shi_central_glendi;"
# .env: DB_CONNECTION=pgsql DB_DATABASE=shi_hospital_glendi
#       CENTRAL_DB_CONNECTION=pgsql CENTRAL_DB_DATABASE=shi_central_glendi
# (ver .env.example para el bloque completo)

php artisan migrate:fresh --seed
# PENDIENTE — debe mostrar las migraciones 2026_08_21_* aplicando en ambas conexiones
# y Mod03PatientsDemoSeeder creando 2 pacientes demo (1 auto_linked, 1 manual_review).

php artisan test
# PENDIENTE — suite esperada: 11 tests de dominio (Unit/Domain/Patients),
# 15 tests de feature (Feature/Patients/Register+Search+Review), más
# Feature/Patients/PostgresPatientIntegrationTest (4 tests, solo corren con pgsql real).

# Prueba de integración PostgreSQL real específica (ver docstring de la clase):
DB_CONNECTION=pgsql DB_HOST=127.0.0.1 DB_PORT=5432 DB_DATABASE=shi_hospital_glendi_test \
DB_USERNAME=postgres DB_PASSWORD=postgres \
CENTRAL_DB_CONNECTION=pgsql CENTRAL_DB_HOST=127.0.0.1 CENTRAL_DB_PORT=5432 \
CENTRAL_DB_DATABASE=shi_central_glendi_test CENTRAL_DB_USERNAME=postgres CENTRAL_DB_PASSWORD=postgres \
php artisan test --filter=PostgresPatientIntegrationTest
# PENDIENTE

git status --short
git log --oneline --decorate --graph -n 20
git worktree list
# Ver salida real más abajo (sí se pudo ejecutar en esta sesión).
```

### Lo que sí se verificó en esta sesión (sin Composer)

```
$ php -v
PHP 8.2.33 (cli) (built: Jul 28 2026 10:29:00) (ZTS Visual C++ 2019 x64)

$ php -m | grep -i pgsql
pdo_pgsql
pgsql

$ Get-Service *postgres*
Status  Name              DisplayName
------  ----              -----------
Running postgresql-x64-17 postgresql-x64-17

$ for f in $(find app database/migrations database/factories database/seeders routes bootstrap config tests -name "*.php"); do php -l "$f"; done
# Resultado: 0 errores de sintaxis en ~80 archivos (nuevos y modificados).
```

### Bug preexistente encontrado y corregido durante esta verificación

`php -l app/Http/Middleware/JwtAuth.php` reveló un **fatal error real** (no introducido por mí,
ya existía en la rama base): `use Tymon\JWTAuth\Facades\JWTAuth;` colisiona en mayúsculas/minúsculas
con `class JwtAuth` en el mismo namespace — PHP los trata como el mismo símbolo y aborta la
compilación del archivo con *"Cannot declare class ... because the name is already in use"*.
Esto habría roto **todas** las rutas protegidas (`jwt.auth` es middleware transversal). Se corrigió
con el cambio mínimo `use ... as JWTAuthFacade;` (ver el archivo y el commit correspondiente).
Documentado también en `docs/modulos/mod03/ADR-001-arquitectura.md`.

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

database/migrations/2026_08_21_*.php (6 migraciones: columnas MPI en patients, uniqueness por
    tenant, outbox, mpi_patients, patient_hospital_links, identity_match_candidates)
database/factories/MpiPatientFactory.php
database/seeders/Mod03PatientsDemoSeeder.php

tests/Unit/Domain/Patients/ (4 clases, 16 tests — regla central + value objects + entidad)
tests/Feature/Patients/ (4 clases — registro, búsqueda, revisión MPI, integración PostgreSQL)
tests/Support/InteractsWithJwtApi.php

docs/modulos/mod03/
├── ESPECIFICACION.md
├── ADR-001-arquitectura.md
└── diagramas/ (casos de uso, clases, secuencia ×3, componentes, ER)
```

## Ajustes a contratos compartidos (documentados, ver ADR-001 decisión 2)

| Archivo | Cambio | Por qué |
|---|---|---|
| `database/migrations/0001_01_01_000000_create_tenants_table.php` | Declara `connection = 'central'` explícitamente | `Tenant` (Stancl) siempre resuelve a `central`; la migración original no lo hacía explícito. |
| `database/migrations/0001_01_01_000001_create_users_table.php` | Se retira la FK física `users.tenant_id → tenants.id` (queda índice) | Con `tenants` en CENTRAL y `users` en HOSPITAL, esa FK sería entre bases (prohibido). No cambia comportamiento de aplicación. |
| `app/Models/Patient.php` | Agrega columnas MPI + genera `uuid` al crear | Necesario para el vínculo lógico con CENTRAL. |
| `app/Http/Middleware/JwtAuth.php` | Renombra el alias `use` de `JWTAuth` | Corrige un fatal error preexistente (ver sección anterior). |
| `routes/api.php` | Agrega 3 rutas de `patients` | Nuevas, no modifica rutas existentes. |
| `config/database.php`, `.env.example`, `phpunit.xml`, `tests/TestCase.php` | Añaden config de conexión `central` independiente y su equivalente en pruebas | Necesario para que CENTRAL sea una base físicamente distinta de HOSPITAL, no solo un alias. |

## Pull Request y commits

- Ver `git log --oneline --decorate --graph -n 20` (a ejecutar y pegar antes de abrir el PR).
- Esta sesión no realizó commits (instrucción explícita del estudiante): todo queda en el
  working tree de `feature/asii-03-pacientes-glendi20`, pendiente de que el estudiante revise,
  organice en ≥10 commits sustantivos por capa (Domain → Application → Infrastructure →
  Presentation → tests → docs) y haga push.
