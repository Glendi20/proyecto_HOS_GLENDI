# Módulo 03 — Semana 10: Diseño para movilidad

- **Estudiante:** Glendi Patricia Campos Orellana
- **GitHub:** `Glendi20`
- **Rama:** `feature/asii-03-pacientes-glendi20`

## 1. Propósito de esta entrega

Adaptar el flujo principal del módulo (buscar → registrar → resolver coincidencia MPI) a uso
responsive/móvil, y documentar los escenarios reales de uso móvil específicos de este módulo:
recepción con tablet, y el caso particular de **conectividad intermitente**, que en este módulo
no es un detalle de infraestructura sino parte del contrato de negocio (RF-16, continuidad ante
falla de CENTRAL).

## 2. Estructura de esta entrega

```text
semana-10-diseno-movil/
├── README.md                # Esta ficha
└── DISENO_RESPONSIVE.md     # Breakpoints, layout adaptado y escenarios móviles
```

## 3. Ya implementado, no solo propuesto

El [prototipo navegable](../semana-11-mockup-prototipo/README.md) ya es responsive de verdad
(se probó achicando la ventana hasta ~380px, no solo se describió en un documento): la navegación
pasa de columna vertical a fila horizontal con scroll, y el formulario de dos columnas pasa a una
sola columna por debajo de 480px. El detalle está en `DISENO_RESPONSIVE.md`.
