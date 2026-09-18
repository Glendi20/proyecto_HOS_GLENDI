# Informe de defensa — Módulo 03: Paciente local, búsqueda y vínculo con MPI CENTRAL

**Estudiante:** Glendi Patricia Campos Orellana (`Glendi20`)

## 1. Delimitación del sistema

El módulo resuelve dos responsabilidades que en la línea base del proyecto vivían mezcladas
en una sola tabla `patients` de alcance local: registrar y buscar el **paciente local** de
un hospital (autoridad clínica de esa sucursal), y reconocer cuándo ese registro corresponde
a la misma persona que ya existe en el **índice maestro de pacientes (MPI)** de la red
CENTRAL, sin fusionar nunca dos expedientes por error.

## 2. Actores y casos de uso (Semana 1)

- **Recepcionista / Admin**: registran, editan, buscan pacientes y resuelven coincidencias
  MPI ambiguas.
- **Médico / Enfermera**: solo lectura (búsqueda y detalle).
- **CENTRAL (MPI)**: sistema colaborador que puede no estar disponible.
- Casos de uso base CU-01..CU-04 (`docs/module-03/actores-casos-de-uso.md`) y su extensión
  CU-05/CU-06 (`ESPECIFICACION.md`, semana 4).

## 3. Requisitos y principio SOLID (Semana 2)

- RF-01..RF-10 y RNF-01..RNF-07 sobre el CRUD base de pacientes.
- Principio aplicado: **Responsabilidad Única (SRP)** — separar validación de formato
  (Form Requests), orquestación (casos de uso) y reglas de negocio (entidad `LocalPatient`).
  Se amplía en la sección 5 con **Inversión de Dependencias (DIP)** para el patrón Repository.

## 4. Vista arquitectónica (Semana 3)

Ubicación del módulo dentro del Sistema Hospitalario Integrado y sus dependientes
(`docs/module-03/vista-arquitectonica.md`): Admisión (#07), EMR (#10) y Alergias (#12)
consumen `patients.id`/`uuid` como referencia lógica, nunca acoplados a la implementación
interna del módulo.

## 5. Arquitectura en capas y patrón Repository (Semana 4)

```
Presentation  → PatientController (controlador delgado)
Application   → UseCases (RegisterLocalPatient, SearchLocalPatients,
                ReviewMatchCandidate, SyncPendingPatients) + Ports (interfaces)
Domain        → LocalPatient, PatientMatchingPolicy (regla central), value objects
Infrastructure→ EloquentXxxRepository (producción) / InMemoryXxxRepository (pruebas)
```

- **Patrón Repository por caso de uso** (no CRUD genérico): cada puerto expone solo lo que
  su caso de uso necesita (`findCandidates`, `nextCode`, `createPending`, `enqueue`...).
- **Regla central obligatoria** (`PatientMatchingPolicy`, clase pura sin dependencias de
  infraestructura): sin candidatos → identidad nueva; un candidato dominante → `auto_link`;
  cualquier ambigüedad → `manual_review` con `identity_match_candidate` explícito. **Nunca**
  fusiona silenciosamente.
- **Arquitectura federada CENTRAL/HOSPITAL**: dos conexiones de base de datos distintas
  (`default` = HOSPITAL, `central` = CENTRAL), referencias siempre por UUID lógico, jamás
  FK física entre bases (ver `ADR-001-arquitectura.md`).
- **Continuidad ante fallas**: si CENTRAL no responde, el alta local ya confirmada se
  conserva, el paciente queda `pending` y se encola un evento idempotente en
  `patient_sync_outbox`; `patients:sync-mpi` reintenta después sin duplicar vínculos.

## 6. Contrato API e integración (Semana 5)

- `POST /api/v1/patients`, `GET /api/v1/patients`,
  `POST /api/v1/patients/match-candidates/{id}/resolve` — documentados end-to-end en
  `docs/module-03/contrato-api.md`, incluyendo errores transversales (400/401/403/404/422)
  y permisos por rol.
- `config/tenancy.php` fija `database.central_connection = central`, cerrando un hallazgo
  real del repositorio base (la conexión central nunca estaba genuinamente separada de la
  conexión por defecto).

## 7. Trazabilidad extremo a extremo

CU-05 (Semana 1, ampliado Semana 4) → RF-11..RF-16 (Semana 4) → `PatientMatchingPolicy` +
`RegisterLocalPatientUseCase` (código, Semana 4) → `POST /api/v1/patients` (contrato,
Semana 5) → pruebas `tests/Feature/Patients/RegisterLocalPatientTest.php` (verde,
`EVIDENCIA.md`). No hay un eslabón de esta cadena sin evidencia verificable en el repositorio.

## 8. Respuesta ante un cambio práctico

Ver [`CAMBIO_PRACTICO.md`](./CAMBIO_PRACTICO.md): se analiza el impacto de agregar un rol
de solo lectura para auditoría de interoperabilidad (`AuditorMPI`) sobre las coincidencias
del MPI, mostrando que la separación en capas y el patrón Repository permiten extender el
módulo sin tocar la regla central ni el resto de casos de uso existentes.
