# Módulo 03 — RF/RNF, criterios de aceptación y principio SOLID (Semana 2)

## Requerimientos funcionales (RF)

| # | Requerimiento | Caso de uso | Actor(es) |
|---|---|---|---|
| RF-01 | El sistema debe permitir registrar un paciente con datos demográficos, contacto, dirección y seguro. | CU-01 | Recepcionista, Admin |
| RF-02 | El sistema debe generar automáticamente un código único correlativo por tenant (formato `PAC-0001`) al registrar un paciente. | CU-01 | Sistema |
| RF-03 | El sistema debe impedir el registro de dos pacientes con el mismo DPI dentro del mismo tenant. | CU-01 | Sistema |
| RF-04 | El sistema debe permitir editar los datos de un paciente existente (contacto, dirección, seguro, contacto de emergencia, tipo de sangre, notas). | CU-02 | Recepcionista, Admin |
| RF-05 | El sistema debe permitir buscar pacientes por nombre, DPI o código, acotado al tenant activo. | CU-03 | Recepcionista, Admin, Médico, Enfermera |
| RF-06 | El sistema debe devolver los resultados de búsqueda paginados. | CU-03 | Sistema |
| RF-07 | El sistema debe mostrar el detalle completo de un paciente, incluyendo el resumen de su admisión activa (si existe). | CU-04 | Recepcionista, Admin, Médico, Enfermera |
| RF-08 | El sistema debe mostrar en el detalle del paciente las alergias registradas y la referencia a su expediente médico. | CU-04 | Recepcionista, Admin, Médico, Enfermera |
| RF-09 | El sistema debe restringir a Médico y Enfermera a operaciones de solo lectura (búsqueda y detalle), sin permitir crear ni editar pacientes. | CU-01, CU-02 | Sistema (RBAC) |
| RF-10 | El sistema debe aislar todos los datos de pacientes por tenant, validando `X-Tenant-ID` en cada operación. | CU-01 a CU-04 | Sistema (Tenant) |

## Requerimientos no funcionales (RNF)

| # | Requerimiento | Categoría |
|---|---|---|
| RNF-01 | Toda operación sobre pacientes debe validar un JWT vigente antes de ejecutarse. | Seguridad |
| RNF-02 | Los datos de un tenant nunca deben ser visibles ni editables desde otro tenant. | Seguridad / Aislamiento multi-tenant |
| RNF-03 | La búsqueda de pacientes debe responder con buen desempeño aun con el catálogo creciendo, apoyándose en los índices `tenant_id+last_name+first_name`, `tenant_id+dpi` y `tenant_id+code` ya definidos en la migración. | Rendimiento |
| RNF-04 | El código de paciente generado debe ser único e irrepetible por tenant, incluso ante registros concurrentes. | Confiabilidad / Integridad de datos |
| RNF-05 | Los campos sensibles (DPI, contacto de emergencia, seguro) deben tratarse como datos administrativos/clínicos sensibles conforme a las reglas de gobernanza del HIS. | Privacidad / Cumplimiento |
| RNF-06 | La UI de registro/edición debe validar campos obligatorios y formatos antes de enviar la solicitud, mostrando errores claros por campo. | Usabilidad |
| RNF-07 | La búsqueda y el detalle de paciente deben mantenerse disponibles para Médico y Enfermera durante la atención clínica, sin degradarse por la carga de otros módulos. | Disponibilidad |

## Criterios de aceptación

### CU-01 — Registrar paciente

- Dado un Recepcionista o Admin autenticado que envía datos válidos, cuando registra un paciente, entonces el sistema crea el registro y devuelve un código único con formato `PAC-0001`.
- Dado un DPI ya existente en el mismo tenant, cuando se intenta registrar otro paciente con ese DPI, entonces el sistema rechaza la operación e indica el conflicto.
- Dado un campo obligatorio faltante o inválido, cuando se envía el formulario, entonces el sistema responde con errores de validación por campo y no crea el registro.

### CU-02 — Editar paciente

- Dado un paciente existente del tenant activo, cuando Recepcionista o Admin actualiza sus datos, entonces el sistema guarda los cambios y refleja los valores actualizados.
- Dado un paciente que pertenece a otro tenant, cuando se intenta editar, entonces el sistema responde como acceso denegado / recurso no encontrado.

### CU-03 — Buscar pacientes

- Dado un término de búsqueda (nombre, DPI o código), cuando cualquier actor autorizado busca, entonces el sistema devuelve solo pacientes del tenant activo que coincidan, paginados.
- Dado un término sin coincidencias, cuando se busca, entonces el sistema devuelve una lista vacía sin generar error.

### CU-04 — Ver detalle de paciente

- Dado un paciente existente del tenant activo, cuando se consulta su detalle, entonces el sistema muestra sus datos completos, la admisión activa (si existe), alergias registradas y la referencia a su expediente médico.
- Dado un Médico o Enfermera autenticado, cuando intenta registrar o editar un paciente, entonces el sistema deniega la operación por permisos insuficientes.

## Principio SOLID aplicado: Responsabilidad Única (Single Responsibility Principle)

Fuente: [mvpcluster.com/diseno-de-software-2](https://mvpcluster.com/diseno-de-software-2/) — "Una clase debería tener una y sólo una razón para cambiar."

### Problema si no se aplica

El flujo de **CU-01 Registrar paciente** (RF-01 a RF-03) combina varias responsabilidades distintas: validar el payload HTTP, verificar la unicidad del DPI, generar el código correlativo `PAC-0001` por tenant y persistir el registro. Si toda esa lógica vive en un único método `PatientController@store`, esa clase tendría **múltiples razones para cambiar**: un ajuste en las reglas de validación, un cambio en el algoritmo de generación de código, o un cambio en la regla de unicidad de DPI, todos obligarían a modificar el mismo método, aumentando el riesgo de romper responsabilidades que no tenían relación entre sí.

### Diseño propuesto aplicando SRP

Separar la responsabilidad en clases con una única razón de cambio cada una:

| Clase | Responsabilidad única | Razón de cambio |
|---|---|---|
| `PatientController` | Recibir la petición HTTP, delegar y devolver la respuesta. | Cambios en el contrato de la API (rutas, formato de respuesta). |
| `StorePatientRequest` (Form Request) | Validar formato y reglas de entrada del payload. | Cambios en qué campos son obligatorios o en su formato. |
| `PatientCodeGenerator` (servicio) | Generar el siguiente código correlativo por tenant (`PAC-0001`). | Cambios en el formato o algoritmo de generación del código. |
| `Patient` (modelo Eloquent) | Representar y persistir la entidad paciente. | Cambios en el esquema de datos o relaciones. |

```php
// Antes: el controller mezcla validación, reglas de negocio y persistencia
class PatientController
{
    public function store(Request $request)
    {
        $data = $request->validate([...]);
        if (Patient::where('dpi', $data['dpi'])->exists()) {
            abort(409, 'DPI duplicado');
        }
        $data['code'] = 'PAC-' . str_pad(Patient::count() + 1, 4, '0', STR_PAD_LEFT);
        return Patient::create($data);
    }
}

// Después: cada clase tiene una sola responsabilidad
class PatientController
{
    public function store(StorePatientRequest $request, PatientCodeGenerator $codes)
    {
        $data = $request->validated();
        $data['code'] = $codes->nextFor($request->tenant());
        return Patient::create($data);
    }
}
```

Con esta separación, cambiar la regla de generación del código (por ejemplo, pasar de un contador simple a un correlativo atómico por tenant) solo afecta a `PatientCodeGenerator`, sin tocar el controller ni las validaciones. Este diseño se formalizará en el diagrama por capas de la Semana 4.
