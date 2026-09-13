# Módulo 03 — Pacientes: registro, edición, búsqueda y detalle

## Alcance

El módulo **Pacientes** administra el ciclo de vida del registro administrativo del paciente dentro de un tenant (hospital): alta, edición, búsqueda y consulta de detalle. Es la fuente de verdad de los datos demográficos, de contacto y de seguro que usan el resto de módulos clínicos del HIS.

Recepcionista y Admin registran y editan pacientes; el sistema genera un código único (`PAC-0001`) por tenant. Recepcionista, Admin, Médico y Enfermera pueden buscar pacientes (por nombre, DPI o código) y ver su detalle, que incluye un resumen de la admisión actual, alergias registradas y referencia al expediente médico..

## Límites

- No cubre el proceso de admisión ni asignación de cama (módulo #7 — Oscar Cruz).
- No cubre la historia clínica, notas SOAP ni signos vitales (módulos #10, #11 y #13).
- No cubre el detalle de alergias clínicas ni su alerta visual (módulo #12 — Axel Herrera).
- No cubre prescripciones electrónicas (módulo #15 — María López Fajardo).
- No cubre reportes ni analítica de pacientes (módulos #22, #23 y #27).

## Dependencias

- Módulo #7 (Admisión hospitalaria) y módulo #12 (Alergias) — consumen el `patient_id` que expone este módulo.
- Módulo #10 (Expediente médico electrónico base) — se relaciona 1 a 1 con el paciente registrado aquí.
- Módulo #1 (Usuarios, tenants y autenticación) — provee el aislamiento por `X-Tenant-ID` y el usuario autenticado que usa este módulo.
- Módulo #2 (RBAC) — define los roles/permisos (`Recepcionista`, `Admin`, `Médico`, `Enfermera`) que este módulo respeta.
