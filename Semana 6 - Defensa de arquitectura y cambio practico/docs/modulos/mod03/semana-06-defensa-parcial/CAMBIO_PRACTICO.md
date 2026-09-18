# Análisis del cambio práctico — Rol de auditoría de interoperabilidad (`AuditorMPI`)

## 1. Planteamiento del requerimiento de cambio

Para una acreditación de interoperabilidad, se solicita que un auditor externo pueda revisar
**sin poder modificar nada** cómo el módulo decidió vincular (o no) a los pacientes locales
con el índice maestro (MPI):

> Como `AuditorMPI`, quiero listar y consultar los `identity_match_candidate` de un hospital
> (resueltos y pendientes), con su motivo y candidatos evaluados, para verificar que ninguna
> coincidencia se resolvió sin evidencia trazable — sin poder confirmar, rechazar ni crear
> pacientes.

Este análisis es **solo diseño**: no se implementa en esta entrega (eso corresponde a la
Semana 7, que sí incluye un cambio de código, aunque uno distinto — ver
[`../semana-07-componentes-refactor/`](../semana-07-componentes-refactor/)). El objetivo aquí
es demostrar que la arquitectura actual **absorbe** este requerimiento sin romper nada.

## 2. Análisis de impacto por capa

### Domain

- **Sin cambios.** `identity_match_candidate` y `MatchDecision` ya existen y ya son
  inmutables una vez resueltos (`MatchCandidateAlreadyResolvedException`). Un auditor de solo
  lectura no necesita una regla de negocio nueva, solo una vía de consulta.

### Application

- **Nuevo caso de uso:** `ListMatchCandidatesUseCase` (análogo a `SearchLocalPatientsUseCase`
  en su forma: recibe `tenantId` + filtros de paginación, no muta nada).
- **Puerto existente, un método nuevo:** `IdentityMatchCandidateRepository::paginateByTenant()`
  se agrega a la interfaz ya presente en
  [`app/Application/Patients/Ports/IdentityMatchCandidateRepository.php`](../../../../app/Application/Patients/Ports/IdentityMatchCandidateRepository.php),
  sin tocar los métodos que ya usan `RegisterLocalPatientUseCase` y `ReviewMatchCandidateUseCase`.

### Infrastructure

- `EloquentIdentityMatchCandidateRepository` implementa el método nuevo con una consulta de
  solo lectura acotada por `tenant_id` (mismo aislamiento multitenant que el resto del
  módulo).
- `InMemoryIdentityMatchCandidateRepository` (pruebas) implementa el mismo método sobre su
  colección en memoria — sin infraestructura real, igual que hoy.

### Presentation

- **Nueva ruta de solo lectura:** `GET /api/v1/patients/match-candidates`, protegida por el
  middleware de roles ya existente en el proyecto (RBAC, módulo #02) restringido al rol
  `AuditorMPI`. El controlador reutiliza el mismo patrón delgado de `PatientController`
  (delega en el caso de uso, traduce el resultado a JSON).
- **Sin cambios** en `POST /api/v1/patients` ni en
  `POST /api/v1/patients/match-candidates/{id}/resolve`: el auditor nunca llama esas rutas
  porque el middleware de rol se lo impide antes de llegar al controlador.

## 3. Por qué el diseño actual absorbe el cambio sin romperse

1. **La regla central no se toca.** `PatientMatchingPolicy` sigue siendo la única fuente de
   verdad sobre `AUTO_LINK` / `MANUAL_REVIEW` / `NO_MATCH`; el auditor solo lee su resultado
   ya persistido, nunca vuelve a evaluarlo.
2. **El puerto crece, no cambia.** Añadir `paginateByTenant()` a una interfaz ya existente es
   compatible hacia atrás: las dos implementaciones (Eloquent e InMemory) se actualizan una
   vez, y ningún caso de uso existente cambia su firma.
3. **La autorización es transversal, no ad-hoc.** El mismo middleware de roles que ya protege
   `store()` y `resolveMatchCandidate()` protege la ruta nueva; no se inventa un mecanismo de
   permisos distinto para el auditor.
4. **El aislamiento por tenant se hereda gratis.** Como toda consulta pasa por el mismo
   patrón (`tenant_id` resuelto del header `X-Tenant-ID`), el auditor de un hospital no puede
   ver candidatos de otro sin ningún código adicional.

## 4. Riesgo identificado

Si en el futuro se quisiera que un auditor viera candidatos de **varios** hospitales a la vez
(auditoría a nivel de red, no por hospital), el diseño actual no lo permite directamente,
porque el tenant se resuelve siempre de `X-Tenant-ID` de a uno. Eso requeriría un rol nuevo
a nivel CENTRAL (fuera del alcance individual de este módulo) y se documenta aquí como
**trabajo futuro**, no como una omisión de este análisis.
