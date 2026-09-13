# Diagrama de componentes/capas — Módulo 03 dentro del SHI

Ubica el módulo dentro del SHI y muestra CENTRAL, HOSPITAL, contratos y el punto de falla parcial.

```mermaid
flowchart TB
    subgraph Presentation["Presentation"]
        Ctrl["PatientController"]
        Req["RegisterLocalPatientRequest /<br/>SearchPatientsRequest /<br/>ResolveMatchCandidateRequest"]
    end

    subgraph Application["Application"]
        UC1["RegisterLocalPatientUseCase"]
        UC2["SearchLocalPatientsUseCase"]
        UC3["ReviewMatchCandidateUseCase"]
        UC4["SyncPendingPatientsUseCase"]
        Ports["Ports (interfaces)"]
    end

    subgraph Domain["Domain"]
        Entity["LocalPatient"]
        Policy["PatientMatchingPolicy<br/>(regla central)"]
        VO["Dpi / PatientDemographics /<br/>PatientUuid / MatchDecision"]
    end

    subgraph InfraHospital["Infrastructure — conexión HOSPITAL (default)"]
        EloqLocal["EloquentLocalPatientRepository"]
        EloqOutbox["EloquentPatientSyncOutboxRepository"]
        DBHospital[("PostgreSQL HOSPITAL<br/>patients, patient_sync_outbox")]
    end

    subgraph InfraCentral["Infrastructure — conexión CENTRAL"]
        EloqMpi["EloquentMpiPatientRepository"]
        EloqLink["EloquentPatientHospitalLinkRepository"]
        EloqMatch["EloquentIdentityMatchCandidateRepository"]
        DBCentral[("PostgreSQL CENTRAL<br/>tenants, mpi_patients,<br/>patient_hospital_links,<br/>identity_match_candidates")]
    end

    subgraph InfraFakes["Infrastructure — dobles de prueba"]
        Fakes["InMemory*Repository"]
    end

    Ctrl --> Req
    Ctrl --> UC1
    Ctrl --> UC2
    Ctrl --> UC3

    UC1 --> Ports
    UC2 --> Ports
    UC3 --> Ports
    UC4 --> Ports
    UC1 --> Policy
    UC4 --> Policy
    UC1 --> Entity
    Entity --> VO
    Policy --> VO

    Ports -.->|implementa| EloqLocal
    Ports -.->|implementa| EloqOutbox
    Ports -.->|implementa| EloqMpi
    Ports -.->|implementa| EloqLink
    Ports -.->|implementa| EloqMatch
    Ports -.->|implementa, en tests| Fakes

    EloqLocal --> DBHospital
    EloqOutbox --> DBHospital
    EloqMpi --> DBCentral
    EloqLink --> DBCentral
    EloqMatch --> DBCentral

    EloqMpi -.->|"CentralUnavailableException<br/>si falla la conexión/red"| UC1
    EloqOutbox -.->|"encola evento pendiente<br/>ante esa falla"| DBHospital

    subgraph SHI["Otros módulos del SHI (consumen patients.uuid/id)"]
        Adm["#07 Admisión"]
        Emr["#10 EMR"]
        Alg["#12 Alergias"]
        Auth["#01 Identidad / #02 RBAC<br/>(tenant, jwt.auth)"]
    end

    DBHospital -.->|"UUID lógico<br/>(patient_id / uuid)"| Adm
    DBHospital -.->|"UUID lógico<br/>(patient_id, 1:1)"| Emr
    DBHospital -.->|"UUID lógico<br/>(patient_id)"| Alg
    Auth -.->|"middleware tenant + jwt.auth"| Ctrl
```

## Lectura del diagrama

- La línea punteada entre `EloqMpi` y `UC1` es el punto exacto donde puede ocurrir la **falla parcial**: si CENTRAL no responde, la excepción se captura en `RegisterLocalPatientUseCase`, no se propaga como error HTTP.
- `InfraFakes` no participa en producción; sustituye a los adaptadores de CENTRAL en las pruebas de aplicación (`tests/Feature/Patients/*`) para poder controlar cada rama de decisión sin infraestructura real.
- El límite `Domain` no tiene flechas de salida hacia `Infrastructure` ni hacia `Presentation`: solo `VO` (sin dependencias externas).
