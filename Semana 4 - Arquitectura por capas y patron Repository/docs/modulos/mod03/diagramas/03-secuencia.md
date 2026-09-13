# Diagramas de secuencia — Módulo 03

## 1. Camino principal: alta con CENTRAL disponible (auto_link)

```mermaid
sequenceDiagram
    actor R as Recepcionista
    participant C as PatientController
    participant UC as RegisterLocalPatientUseCase
    participant LP as LocalPatientRepository (Eloquent)
    participant Pol as PatientMatchingPolicy
    participant MPI as MpiPatientRepository (Eloquent, CENTRAL)
    participant Link as PatientHospitalLinkRepository (CENTRAL)

    R->>C: POST /api/v1/patients
    C->>UC: handle(RegisterPatientInput)
    UC->>LP: findByDpi(tenantId, dpi)
    LP-->>UC: null (sin duplicado)
    UC->>LP: nextCode(tenantId)
    LP-->>UC: "PAC-0007"
    UC->>LP: save(LocalPatient pending)
    Note over LP: Alta local CONFIRMADA aquí,<br/>antes de tocar CENTRAL.
    UC->>MPI: findCandidates(demographics)
    MPI-->>UC: [MpiCandidate score=0.97]
    UC->>Pol: decide(candidates)
    Pol-->>UC: MatchDecision::autoLink(winner)
    UC->>Link: link(tenantId, uuid, mpiPatientId, "auto_linked")
    UC->>LP: save(LocalPatient auto_linked)
    UC-->>C: RegisterPatientResult
    C-->>R: 201 { uuid, code, mpi_link_status: auto_linked, global_id }
```

## 2. Excepción: CENTRAL no disponible (continuidad offline)

```mermaid
sequenceDiagram
    actor R as Recepcionista
    participant C as PatientController
    participant UC as RegisterLocalPatientUseCase
    participant LP as LocalPatientRepository (Eloquent)
    participant MPI as MpiPatientRepository (Eloquent, CENTRAL)
    participant OB as PatientSyncOutboxRepository (HOSPITAL)
    participant Sync as SyncPendingPatientsUseCase

    R->>C: POST /api/v1/patients
    C->>UC: handle(RegisterPatientInput)
    UC->>LP: findByDpi / nextCode
    UC->>LP: save(LocalPatient pending)
    Note over LP: Alta local YA CONFIRMADA.
    UC->>MPI: findCandidates(demographics)
    MPI--xUC: throw CentralUnavailableException
    UC->>LP: save(LocalPatient pending) (sin cambios de estado)
    UC->>OB: enqueue(eventId, tenantId, uuid, payload)
    Note over OB: event_id único → idempotente
    UC-->>C: RegisterPatientResult (centralWasAvailable=false)
    C-->>R: 201 { mpi_link_status: pending, central_available: false }

    Note over OB: Más tarde (patients:sync-mpi)...
    OB-->>Sync: pending(limit)
    Sync->>MPI: findCandidates(demographics)
    MPI-->>Sync: [] o candidatos
    Sync->>LP: save(LocalPatient auto_linked|manual_review)
    Sync->>OB: markSent(eventId)
```

## 3. Excepción: coincidencia ambigua → revisión manual (nunca fusión automática)

```mermaid
sequenceDiagram
    actor R as Recepcionista
    participant C as PatientController
    participant UC as RegisterLocalPatientUseCase
    participant Pol as PatientMatchingPolicy
    participant MC as IdentityMatchCandidateRepository (CENTRAL)
    participant LP as LocalPatientRepository (Eloquent)

    R->>C: POST /api/v1/patients
    C->>UC: handle(...)
    UC->>Pol: decide([candA score=0.70, candB score=0.68])
    Pol-->>UC: MatchDecision::manualReview([candA, candB])
    UC->>MC: createPending(tenantId, uuid, decision)
    MC-->>UC: matchCandidateId
    UC->>LP: save(LocalPatient manual_review)
    UC-->>C: RegisterPatientResult(matchCandidateId)
    C-->>R: 201 { mpi_link_status: manual_review, global_id: null }

    Note over R,MC: Revisión humana posterior (CU-06)
    R->>C: POST /patients/match-candidates/{id}/resolve {decision: confirm, mpi_patient_id: candA}
    C->>MC: find(id) → status=pending
    C->>LP: findByUuid / save (auto_linked, global_id=candA)
    C->>MC: markResolved(id, "confirmed", reviewedBy)
```
