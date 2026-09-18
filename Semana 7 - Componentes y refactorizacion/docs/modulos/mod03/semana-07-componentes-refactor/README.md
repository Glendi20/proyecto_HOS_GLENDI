# Módulo 03 — Semana 7: Diseño de componentes y refactorización

- **Estudiante:** Glendi Patricia Campos Orellana
- **GitHub:** `Glendi20`
- **Rama:** `feature/asii-03-pacientes-glendi20`
- **Módulo:** Paciente local, búsqueda y vínculo con MPI CENTRAL

## 1. Propósito de esta entrega

1. **Diseño de componentes backend y frontend** del módulo, consolidando el backend ya
   implementado (semana 4) y proponiendo la vista de componentes frontend (aún no
   implementada en este proyecto, que hasta ahora es una API backend).
2. **Refactorización de un punto real de acoplamiento**, con código que ya vive en el
   proyecto (no un ejemplo aparte): `PatientController` repetía un `try/catch` de
   excepción → código HTTP en cada acción. Se extrae esa traducción a un componente propio,
   reutilizable y con una prueba unitaria dedicada.
3. **Antes / después justificado**, con las pruebas existentes del módulo como evidencia de
   que el comportamiento observable (contrato HTTP) no cambió.

## 2. Estructura de esta entrega

```text
semana-07-componentes-refactor/
├── README.md                    # Esta ficha
├── INFORME.md                   # Informe técnico de componentes y refactor
├── COMPONENTES.md               # Diagrama de componentes backend + propuesta frontend
└── REFACTOR_ANTES_DESPUES.md    # Comparación antes/después con evidencia de pruebas
```

## 3. Cambio de código real de esta entrega

| Archivo | Cambio |
|---|---|
| [`app/Http/Support/Patients/PatientExceptionResponder.php`](../../../../app/Http/Support/Patients/PatientExceptionResponder.php) | **Nuevo.** Mapa único excepción de dominio → respuesta HTTP. |
| [`app/Http/Controllers/Api/V1/PatientController.php`](../../../../app/Http/Controllers/Api/V1/PatientController.php) | **Refactorizado.** `store()` y `resolveMatchCandidate()` delegan en `tryAction()`; ya no repiten `try/catch`. |
| [`tests/Unit/Http/Support/Patients/PatientExceptionResponderTest.php`](../../../../tests/Unit/Http/Support/Patients/PatientExceptionResponderTest.php) | **Nuevo.** Cubre el mapa de las 5 excepciones de dominio del módulo. |

Verificado con `php artisan test --filter=Patients`: 36 pruebas en verde (4 omitidas por
requerir PostgreSQL real), mismo resultado que antes del refactor — ver
[`REFACTOR_ANTES_DESPUES.md`](./REFACTOR_ANTES_DESPUES.md).
