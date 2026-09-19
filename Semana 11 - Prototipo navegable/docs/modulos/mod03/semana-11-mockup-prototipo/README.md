# Módulo 03 — Semana 11: Mockup / prototipo navegable

- **Estudiante:** Glendi Patricia Campos Orellana
- **GitHub:** `Glendi20`
- **Rama:** `feature/asii-03-pacientes-glendi20`

## 1. Prototipo navegable

El prototipo es el archivo [`prototipo.html`](./prototipo.html) de este mismo directorio.

**Cómo abrirlo:** doble clic sobre el archivo (o clic derecho → "Abrir con" → tu navegador). Es un
único archivo HTML autocontenido (HTML + CSS + JS en el mismo archivo, sin backend ni build):
abre directo en Chrome, Edge o Firefox desde el disco, sin servidor ni instalación.

Es un prototipo **realmente navegable** (no una imagen ni un PDF): tiene botones, formularios y
estados que responden de verdad. Cumple el entregable de la semana 11 ("Mockup en Figma, Canva,
Excalidraw o herramienta equivalente") con una herramienta equivalente que además permite
interactuar con el flujo, no solo mirarlo.

## 2. Qué se puede probar en el prototipo

| Control | Qué demuestra |
|---|---|
| Selector **Rol activo** | RBAC en la UI: cambiar a Médico/Enfermera oculta "Registrar paciente" y "Revisar coincidencias MPI" del menú (no solo los deshabilita). |
| Interruptor **CENTRAL disponible / no disponible** | La regla central del módulo: con CENTRAL "caído", registrar un paciente muestra el desenlace `pending` en vez de bloquear el alta. |
| Pestaña **Buscar pacientes** | Estados de carga (shimmer), resultados y vacío ("Sin resultados"), con datos de ejemplo tomados de `Mod03PatientsDemoSeeder` (mismos pacientes ficticios que ya existen en la base de datos real del módulo). |
| Pestaña **Registrar paciente** | Formulario real (mismos campos y reglas que `RegisterLocalPatientRequest`), con los 3 desenlaces posibles del alta y el caso de DPI duplicado (409). |
| Pestaña **Revisar coincidencias MPI** | El caso de ambigüedad real del seeder (Carlos Hernández Ruiz, 2 candidatos con score 0.65) y las dos acciones humanas explícitas (confirmar / ninguna es correcta). |
| Selector de tema del sistema (claro/oscuro) | La paleta completa está definida para ambos temas — pruébalo cambiando el tema de tu sistema operativo. |

## 3. Fuente editable

El HTML es texto plano editable — [`prototipo.html`](./prototipo.html). Cualquier cambio (colores,
copys, datos de ejemplo) se hace directo en ese archivo y se ve recargando la página en el
navegador.

## 4. Decisiones de diseño (resumen)

- **Color:** acento teal (`#0e6b5c` en claro / `#4fbfa8` en oscuro) sobre un neutro verde-grisáceo
  frío, no un gris puro — evoca un sistema clínico sin caer en el azul genérico de "app de
  hospital" ni en paletas de moda ajenas al dominio. Los 4 colores de estado (verde/ámbar/morado/
  rojo) son semánticos y siempre van acompañados de texto, nunca solo color.
- **Tipografía:** Archivo (títulos), IBM Plex Sans (interfaz) e IBM Plex Mono (códigos de
  paciente, UUID, fechas) — la familia IBM Plex se eligió porque fue diseñada explícitamente para
  software técnico/empresarial, coherente con un sistema de registro hospitalario.
- **Layout:** shell de aplicación (barra superior + nav lateral + panel), no una landing page — es
  una herramienta que se opera, no un contenido que se lee de arriba a abajo.

## 5. Correcciones aplicadas tras la semana 9

El hallazgo "Must" de accesibilidad de
[`semana-09-usabilidad-accesibilidad/HALLAZGOS_MEJORAS.md`](../semana-09-usabilidad-accesibilidad/HALLAZGOS_MEJORAS.md)
(navegación deshabilitada invisible para teclado/lector de pantalla) ya está corregido en
`prototipo.html` (`aria-disabled` + explicación en vez de `disabled`).
