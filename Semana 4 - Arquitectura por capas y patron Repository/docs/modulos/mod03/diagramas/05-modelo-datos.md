# Vista de datos (ER) — Módulo 03

Fragmento derivado del ER de 42 entidades del diagnóstico, acotado a lo que este módulo
implementa. Ver claves, restricciones e índices exactos en las migraciones
(`database/migrations/2026_08_21_*`).

```mermaid
erDiagram
    TENANTS ||--o{ PATIENT_HOSPITAL_LINKS : "tenant_id (FK real, misma base CENTRAL)"
    TENANTS ||--o{ IDENTITY_MATCH_CANDIDATES : "tenant_id (FK real, misma base CENTRAL)"
    MPI_PATIENTS ||--o{ PATIENT_HOSPITAL_LINKS : "mpi_patient_id (FK real, misma base CENTRAL)"

    PATIENTS {
        bigint id PK
        char_36 uuid UK "identificador logico hacia CENTRAL"
        char_36 tenant_id "hospital (sin FK fisica)"
        varchar code "unico por tenant_id"
        varchar first_name
        varchar last_name
        date birth_date
        varchar dpi "nullable, 13 digitos"
        char_36 global_id "copia de solo lectura, sin FK"
        varchar mpi_link_status "pending/auto_linked/manual_review/unlinked"
        timestamp mpi_synced_at
    }

    PATIENT_SYNC_OUTBOX {
        char_36 event_id PK "idempotencia"
        char_36 tenant_id
        char_36 local_patient_uuid "logico -> patients.uuid"
        int aggregate_version
        varchar event_type
        json payload
        varchar status "pending/sent/failed"
        int attempts
    }

    TENANTS {
        varchar id PK
        varchar name
        varchar slug UK
    }

    MPI_PATIENTS {
        char_36 id PK "global_id"
        varchar full_name_normalized
        date birth_date
        varchar dpi_normalized
        varchar status
    }

    PATIENT_HOSPITAL_LINKS {
        char_36 id PK
        char_36 tenant_id FK
        char_36 local_patient_uuid "logico -> patients.uuid (sin FK)"
        char_36 mpi_patient_id FK
        varchar status "auto_linked/manual_confirmed/revoked"
        char_36 linked_by "logico -> users.id (sin FK)"
        timestamp linked_at
    }

    IDENTITY_MATCH_CANDIDATES {
        char_36 id PK
        char_36 tenant_id FK
        char_36 local_patient_uuid "logico -> patients.uuid (sin FK)"
        json candidates "[{mpi_patient_id, score, matched_fields}]"
        varchar reason
        varchar status "pending/confirmed/rejected"
        char_36 reviewed_by "logico -> users.id (sin FK)"
        timestamp reviewed_at
    }

    PATIENTS ||..o{ PATIENT_SYNC_OUTBOX : "uuid (logico, misma base HOSPITAL)"
```

## Restricciones e índices esenciales

| Tabla | Conexión | Restricción / índice | Motivo |
|---|---|---|---|
| `patients` | HOSPITAL (default) | `unique(uuid)` | Único identificador citable desde CENTRAL. |
| `patients` | HOSPITAL | `unique(tenant_id, code)` | RF-11: correlativo único por hospital, no global (ver ADR-001 decisión 2.3). |
| `patients` | HOSPITAL | `index(tenant_id, mpi_link_status)` | Listar pendientes de sincronizar por hospital. |
| `patient_sync_outbox` | HOSPITAL | `PK(event_id)` | Idempotencia del outbox. |
| `mpi_patients` | CENTRAL | `index(dpi_normalized, birth_date)` | Shortlist de candidatos antes de puntuar (evita escaneo completo). |
| `patient_hospital_links` | CENTRAL | `FK(tenant_id → tenants.id)`, `FK(mpi_patient_id → mpi_patients.id)` | Ambas tablas viven en CENTRAL: FK real válida. |
| `patient_hospital_links` | CENTRAL (PostgreSQL) | Índice único parcial `(tenant_id, local_patient_uuid) WHERE status <> 'revoked'` | Un paciente local solo puede tener un vínculo **activo** a la vez (RNF-10); se omite en SQLite (dev/tests), documentado en la migración. |
| `identity_match_candidates` | CENTRAL | `index(tenant_id, status)` | Listar pendientes de revisión por hospital. |

## Notas de propiedad de datos

- **HOSPITAL** es dueño de `patients` y `patient_sync_outbox`: escritura y verdad clínica/local.
- **CENTRAL** es dueño de `mpi_patients`, `patient_hospital_links`, `identity_match_candidates`, `tenants`: identidad de red, nunca expediente clínico.
- Ninguna fila de `mpi_patients`/`patient_hospital_links`/`identity_match_candidates` contiene datos clínicos; solo lo mínimo para desambiguar identidad (nombre, fecha de nacimiento, DPI).
