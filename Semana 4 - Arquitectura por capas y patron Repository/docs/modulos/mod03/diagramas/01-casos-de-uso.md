# Diagrama de casos de uso — Módulo 03

Acota al módulo (paciente local + vínculo MPI). Actores y CU-01..CU-04 heredados de
[`docs/module-03/actores-casos-de-uso.md`](../../module-03/actores-casos-de-uso.md);
CU-05 y CU-06 son nuevos de esta actividad.

```mermaid
flowchart LR
    Recepcionista((Recepcionista))
    Admin((Admin))
    Medico((Médico))
    Enfermera((Enfermera))
    Central((CENTRAL<br/>MPI))
    Sync((Sistema<br/>outbox sync))

    subgraph SHI["Módulo Pacientes — HOSPITAL"]
        CU3([CU-03 Buscar pacientes])
        CU5([CU-05 Registrar paciente local<br/>+ vínculo MPI])
        CU6([CU-06 Revisar coincidencia<br/>MPI ambigua])
    end

    Admin -.->|generalización| Recepcionista

    Recepcionista --> CU5
    Recepcionista --> CU3
    Recepcionista --> CU6

    Medico --> CU3
    Enfermera --> CU3

    CU5 -->|"intenta vincular<br/>(best-effort)"| Central
    CU6 -->|confirma/rechaza| Central
    Sync -.->|"reintenta pendientes<br/>(outbox)"| Central
    Sync -.->|actualiza mpi_link_status| CU5
```

## Notas

- **CU-05** siempre se completa aunque `Central` no responda (línea punteada de `Sync` representa el reintento posterior, no una dependencia dura).
- **CU-06** es la única forma de resolver una coincidencia ambigua: nunca ocurre automáticamente.
- Los cuatro roles y su generalización (Admin hereda de Recepcionista) se mantienen igual que en la semana 1.
