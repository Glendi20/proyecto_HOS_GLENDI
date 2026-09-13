# Módulo 03 — Contrato API y plan de integración (Semana 5)

Entregable de la semana 5 del plan ASII: contrato API del flujo vertical del módulo (endpoints,
payloads, respuestas, errores y permisos) y plan de integración técnica (issue, rama, worktree,
PR). Documenta el contrato **tal como está implementado hoy** (`routes/api.php`,
`PatientController`, Form Requests) — no es un contrato aspiracional.

## 1. Convenciones transversales

- Prefijo base: `/api/v1`.
- Todas las rutas de este módulo exigen la cabecera `X-Tenant-ID` (UUID del hospital) y, salvo
  donde se indique lo contrario, un `Authorization: Bearer <JWT>` vigente.
- El tenant se resuelve del header, nunca de un parámetro que envíe el cliente en el body — así se
  garantiza que un usuario nunca pueda leer/escribir pacientes de otro hospital (RF-10, RNF-02).
- Content-Type de request y response: `application/json`.

### 1.1 Errores transversales (aplican a las tres rutas de este módulo)

| Código | Cuándo ocurre | Origen | Cuerpo de ejemplo |
|---|---|---|---|
| 400 | Falta la cabecera `X-Tenant-ID`. | `TenantMiddleware` | `{"message": "La cabecera X-Tenant-ID es obligatoria."}` |
| 401 | JWT ausente, inválido o expirado. | `JwtAuth` (jwt.auth) | `{"message": "Token inválido o expirado."}` |
| 403 | El tenant del JWT no coincide con `X-Tenant-ID`. | `JwtAuth` | `{"message": "El tenant indicado no coincide con el usuario del token."}` |
| 403 | El rol autenticado no tiene permiso para la acción (`authorize()` de la Form Request devuelve `false`). | Form Request | `{"message": "This action is unauthorized."}` |
| 404 | El tenant del header no existe. | `TenantMiddleware` | `{"message": "Tenant no encontrado."}` |
| 422 | El payload no cumple las reglas de validación. | Form Request | `{"message": "...", "errors": {"campo": ["mensaje"]}}` |

## 2. `POST /api/v1/patients` — Registrar paciente local

**Permiso:** `Recepcionista` o `Admin` (RF-09). `RegisterLocalPatientRequest::authorize()`.

**Request**

```json
{
  "first_name": "Ana",
  "last_name": "Ramírez",
  "birth_date": "1995-06-01",
  "gender": "F",
  "dpi": "1111111111111"
}
```

| Campo | Tipo | Obligatorio | Reglas |
|---|---|---|---|
| `first_name` | string | sí | máx. 100 |
| `last_name` | string | sí | máx. 100 |
| `birth_date` | date (`YYYY-MM-DD`) | sí | no puede ser futura |
| `gender` | string | sí | `M`, `F` u `otro` |
| `dpi` | string\|null | no | exactamente 13 dígitos si se envía |

**Respuesta 201 (éxito)** — el cuerpo varía según lo que haya decidido `PatientMatchingPolicy`:

```json
{
  "uuid": "b3c1...-uuid-local",
  "code": "PAC-0007",
  "mpi_link_status": "auto_linked",
  "global_id": "f9a2...-uuid-mpi",
  "match_candidate_id": null,
  "central_available": true
}
```

| `mpi_link_status` | Significa | `global_id` | `match_candidate_id` |
|---|---|---|---|
| `auto_linked` | Vínculo automático con el MPI (identidad nueva o candidato dominante). | presente | `null` |
| `manual_review` | Coincidencia ambigua; requiere `POST /patients/match-candidates/{id}/resolve`. | `null` | presente |
| `pending` | CENTRAL no respondió; alta local confirmada igual, evento en outbox. | `null` | `null` |

`central_available` es `false` únicamente en el caso `pending` (RF-16 / RNF-11: el alta nunca
falla por una caída de CENTRAL).

**Errores propios de este endpoint**

| Código | Causa | Cuerpo |
|---|---|---|
| 409 | Ya existe un paciente con ese DPI en el mismo hospital (RF-19). | `{"message": "Ya existe un paciente registrado con el DPI ... en este hospital."}` |

## 3. `GET /api/v1/patients` — Buscar pacientes

**Permiso:** `Recepcionista`, `Admin`, `Médico` o `Enfermera` (RF-05, solo lectura).

**Query params**

| Parámetro | Tipo | Obligatorio | Reglas |
|---|---|---|---|
| `q` | string | no | máx. 150; busca por nombre, apellido, DPI o código |
| `page` | int | no | ≥ 1 (default 1) |
| `per_page` | int | no | 1–100 (default 15) |

**Respuesta 200**

```json
{
  "data": [
    {
      "uuid": "b3c1...-uuid-local",
      "code": "PAC-0007",
      "first_name": "Ana",
      "last_name": "Ramírez",
      "birth_date": "1995-06-01",
      "gender": "F",
      "dpi": "1111111111111",
      "mpi_link_status": "auto_linked",
      "global_id": "f9a2...-uuid-mpi"
    }
  ],
  "meta": { "total": 1, "page": 1, "per_page": 15 }
}
```

Sin coincidencias → `data: []`, `meta.total: 0`, HTTP 200 (nunca error; RF-06).

## 4. `POST /api/v1/patients/match-candidates/{matchCandidate}/resolve` — Resolver coincidencia MPI

**Permiso:** `Recepcionista` o `Admin` (misma decisión operativa que el alta).

**Parámetro de ruta:** `matchCandidate` — UUID de la `identity_match_candidate` pendiente.

**Request (confirmar)**

```json
{ "decision": "confirm", "mpi_patient_id": "f9a2...-uuid-mpi-candidato-a" }
```

**Request (rechazar)**

```json
{ "decision": "reject" }
```

| Campo | Tipo | Obligatorio | Reglas |
|---|---|---|---|
| `decision` | string | sí | `confirm` o `reject` |
| `mpi_patient_id` | string | sí si `decision=confirm` | debe ser uno de los candidatos originales |

**Respuesta 200**

```json
{
  "match_candidate_id": "a1b2...-uuid-candidato",
  "global_id": "f9a2...-uuid-mpi-candidato-a",
  "mpi_link_status": "auto_linked"
}
```

**Errores propios de este endpoint**

| Código | Causa | Cuerpo |
|---|---|---|
| 404 | No existe una `identity_match_candidate` con ese id. | `{"message": "No existe una coincidencia MPI pendiente con id ..."}` |
| 404 | El paciente local asociado ya no existe. | `{"message": "No existe un paciente local con uuid ... en este hospital."}` |
| 409 | La coincidencia ya fue resuelta antes (inmutable, RF-17). | `{"message": "La coincidencia MPI ... ya fue resuelta (estado: ...) y no puede reprocesarse."}` |
| 422 | `mpi_patient_id` no está entre los candidatos originales. | `{"message": "«...» no está entre los candidatos registrados para esta revisión."}` |

## 5. Tabla resumen de permisos

| Endpoint | Recepcionista | Admin | Médico | Enfermera |
|---|:---:|:---:|:---:|:---:|
| `POST /patients` | ✅ | ✅ | ❌ (403) | ❌ (403) |
| `GET /patients` | ✅ | ✅ | ✅ | ✅ |
| `POST /patients/match-candidates/{id}/resolve` | ✅ | ✅ | ❌ (403) | ❌ (403) |

## 6. Plan de integración técnica

### 6.1 Issue

- **Issue:** `ASII-03 — Pacientes` (según convención de `docs/worktree-guide.md`), ampliado con la
  actividad de arquitectura federada (ver `docs/modulos/mod03/ESPECIFICACION.md`).

### 6.2 Rama y worktree

```bash
git fetch origin
git worktree add ../shi-asii-03-pacientes -b feature/asii-03-pacientes-glendi20 origin/develop
cd ../shi-asii-03-pacientes
```

- Worktree: `../shi-asii-03-pacientes` (ya creado y en uso).
- Rama: `feature/asii-03-pacientes-glendi20` (ya creada, con commits sustantivos por capa).

### 6.3 Integración con otros módulos

| Módulo relacionado | Tipo de dependencia | Contrato que consumen de mí |
|---|---|---|
| #01 Identidad/Tenant | Entrada transversal (`tenant`, `jwt.auth`) | Ninguno; yo los consumo. |
| #02 RBAC | Roles vía Spatie (`hasAnyRole`) | Ninguno; yo los consumo. |
| #07 Admisión | Lectura de `patients.id` / `patients.uuid` | `patient_id` como referencia lógica. |
| #10 EMR | Relación 1:1 con `patients.id` | `patient_id` como referencia lógica. |
| #12 Alergias | Lectura de `patients.id` | `patient_id` como referencia lógica. |

Ningún otro módulo consume `/api/v1/patients*` directamente hoy; son endpoints nuevos de este PR.

### 6.4 Plan de Pull Request

- **Destino:** `develop` (nunca `main`), modo borrador hasta completar evidencia.
- **Título:** `ASII-03: pacientes — glendi20`.
- **Checklist a incluir** (ver `docs/module-03/README.md` y plantilla `.github/pull_request_template.md`):
  - RF/RNF y criterios de aceptación enlazados (`ESPECIFICACION.md`).
  - Los 5 artefactos UML (`docs/modulos/mod03/diagramas/`).
  - Contrato API (este documento) con payloads/errores/permisos.
  - Evidencia de pruebas (`EVIDENCIA.md`) y declaración de IA (`DECLARACION_IA.md`).
  - Riesgos conocidos: ver `ADR-001-arquitectura.md`, sección "Riesgos conocidos".
- **No se fusiona** desde esta rama: la integración final la hace el proceso de revisión del
  docente (regla general del curso, `docs/worktree-guide.md`).
