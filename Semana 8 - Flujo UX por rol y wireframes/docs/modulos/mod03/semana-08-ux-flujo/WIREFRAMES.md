# Wireframes de baja fidelidad — Módulo Pacientes

Wireframes iniciales (previos al prototipo navegable de alta fidelidad de la
[semana 11](../semana-11-mockup-prototipo/README.md)). Deliberadamente sin color ni tipografía:
solo estructura, jerarquía y ubicación de cada elemento.

## Pantalla 1 — Buscar pacientes (todos los roles)

```
┌─────────────────────────────────────────────────────────────┐
│ [Hospital: ▾]                    [Rol: ▾]   [● CENTRAL: on] │
├───────────┬───────────────────────────────────────────────┤
│ >Buscar    │  Buscar pacientes                              │
│  Registrar │  ┌─────────────────────────────────┐ [Actualizar]│
│  Revisar   │  │ 🔍 nombre, DPI o código...       │            │
│            │  └─────────────────────────────────┘            │
│            │  ┌───────────┬──────┬─────────┬────────┬──────┐ │
│            │  │ Paciente  │Código│Nacimiento│Estado  │Global│ │
│            │  ├───────────┼──────┼─────────┼────────┼──────┤ │
│            │  │ Nombre... │PAC-..│ AAAA-MM │ (pill) │ id.. │ │
│            │  │ ...       │      │         │        │      │ │
│            │  └───────────┴──────┴─────────┴────────┴──────┘ │
└───────────┴───────────────────────────────────────────────┘
```

- Barra de búsqueda siempre visible arriba de la tabla, nunca dentro de un modal.
- Columna "Estado" siempre visible junto al nombre — el estado del vínculo MPI es información
  de primer nivel, no algo que haya que abrir el detalle para ver.

## Pantalla 2 — Registrar paciente (Recepcionista, Admin)

```
┌─────────────────────────────────────────────────────────────┐
│  Registrar paciente                                          │
│  ┌─────────────────────┐ ┌─────────────────────┐             │
│  │ Nombres             │ │ Apellidos           │             │
│  └─────────────────────┘ └─────────────────────┘             │
│  ┌─────────────────────┐ ┌─────────────────────┐             │
│  │ Fecha de nacimiento │ │ Género  [F ▾]       │             │
│  └─────────────────────┘ └─────────────────────┘             │
│  ┌───────────────────────────────────────────────┐           │
│  │ DPI (opcional, 13 dígitos)                     │           │
│  └───────────────────────────────────────────────┘           │
│  [Guardar paciente]                                           │
│  ┌───────────────────────────────────────────────┐           │
│  │ ✅ / 🟣 / 🟠  Resultado del registro (ver         │           │
│  │ FLUJO_UX.md §3 para los 3 desenlaces posibles)  │           │
│  └───────────────────────────────────────────────┘           │
└─────────────────────────────────────────────────────────────┘
```

- El panel de resultado aparece **debajo** del formulario, no reemplaza el formulario ni abre un
  modal — el usuario puede ver ambos a la vez y registrar al siguiente paciente sin perder contexto.

## Pantalla 3 — Revisar coincidencias MPI (Recepcionista, Admin)

```
┌─────────────────────────────────────────────────────────────┐
│  Revisar coincidencias MPI                                   │
│  ┌───────────────────────────────────────────────┐           │
│  │ Nombre del paciente local        (pill: revisión)│         │
│  │ ┌───────────────────────────────┬────────────┐ │           │
│  │ │ Candidato A · campos coincid. │ score 0.65 │ │           │
│  │ ├───────────────────────────────┼────────────┤ │           │
│  │ │ Candidato B · campos coincid. │ score 0.65 │ │           │
│  │ └───────────────────────────────┴────────────┘ │           │
│  │ [Confirmar identidad A]  [Ninguna es correcta]  │           │
│  └───────────────────────────────────────────────┘           │
└─────────────────────────────────────────────────────────────┘
```

- Los candidatos se listan uno debajo del otro, cada uno con su score y campos coincidentes
  visibles **sin necesidad de expandir nada** — la decisión debe poder tomarse leyendo la pantalla,
  no explorándola.
- Las dos acciones (confirmar / ninguna es correcta) tienen el mismo tamaño visual: ninguna se
  destaca como "la opción recomendada", porque la política de negocio no favorece ninguna —
  ambas son decisiones humanas igualmente válidas.

## De baja a alta fidelidad

Las tres pantallas de arriba se implementaron como prototipo navegable real (HTML/CSS/JS, no
imágenes estáticas) en la semana 11: ver
[`semana-11-mockup-prototipo/README.md`](../semana-11-mockup-prototipo/README.md).
