# Diseño responsive y escenarios móviles — Módulo Pacientes

## 1. Breakpoints usados (los mismos del prototipo real)

| Rango | Layout |
|---|---|
| `> 720px` (escritorio de recepción) | Navegación lateral fija (200px) + panel de contenido a la derecha. |
| `481px – 720px` (tablet) | Navegación pasa de columna a fila horizontal con scroll propio; panel debajo, a todo el ancho. |
| `≤ 480px` (teléfono) | Formulario de registro pasa de 2 columnas a 1; la tabla de búsqueda se desplaza horizontalmente dentro de su propio contenedor (nunca la página completa). |

Ningún elemento tiene un ancho mínimo mayor al de la pantalla; el layout se prueba manualmente
desde ~380px de ancho en el prototipo real (no es una afirmación sin verificar: se redimensionó
la ventana del navegador sobre el artefacto publicado).

## 2. Por qué la navegación lateral y no una barra inferior de pestañas

Se evaluaron dos opciones para el móvil:

| Opción | A favor | En contra |
|---|---|---|
| Barra inferior de pestañas (patrón app nativa) | Más fácil de alcanzar con el pulgar. | Con roles de solo lectura, dos de las tres pestañas desaparecerían, dejando una barra con una sola pestaña — se ve rota, no minimalista. |
| **Nav superior en fila horizontal con scroll** (elegido) | Se adapta naturalmente a 1, 2 o 3 opciones visibles según el rol, sin verse vacía. | Requiere que el usuario deslice si hay scroll (mitigado: nunca hay más de 3 opciones). |

## 3. Escenarios móviles reales del módulo

### Escenario A — Recepcionista con tablet en el mostrador de admisión

Registra pacientes de pie, con una sola mano libre. El formulario de una columna en móvil permite
completar cada campo sin hacer zoom ni desplazarse lateralmente. El banner de resultado aparece
inmediatamente debajo del botón "Guardar paciente" — visible sin desplazarse hacia abajo en la
mayoría de tablets en orientación vertical.

### Escenario B — Médico/Enfermera consultando desde el pasillo (solo lectura)

Solo necesita buscar y ver el estado de un paciente entre una atención y otra. La navegación en
este caso muestra una sola opción disponible ("Buscar pacientes"): no hay pestañas vacías ni
opciones deshabilitadas ocupando espacio de pantalla en un dispositivo pequeño.

### Escenario C — Conectividad intermitente en la sala de espera (el escenario específico de este módulo)

A diferencia de un formulario web genérico, aquí la conectividad intermitente **no es un caso de
error a evitar, es un caso de uso documentado** (RF-16 / RNF-11, ver
`docs/modulos/mod03/ESPECIFICACION.md`). En móvil esto importa el doble: el wifi de un hospital
grande es notoriamente inestable en zonas de espera.

- El indicador "CENTRAL disponible / no disponible" de la barra superior es deliberadamente
  compacto (un punto de color + una palabra) para que quepa sin recortarse incluso en pantallas
  angostas — no es solo un adorno de escritorio.
- Si CENTRAL cae mientras la recepcionista está registrando pacientes en la tablet, el banner
  `pending` deja claro que **el registro ya se guardó** — crítico en móvil, donde reintentar un
  envío por duda de "¿se habrá guardado?" es más común que en escritorio (menos certeza visual,
  más prisa).

## 4. Qué NO se adaptó (fuera de alcance de este módulo)

- No se diseñó una app nativa ni PWA instalable — el módulo #30 (Hugo Moscoso, NativePHP) es quien
  evalúa esa vía para todo el HIS.
- No se rediseñó la tabla de búsqueda como tarjetas apiladas en móvil (patrón común en tablas
  responsive): se mantuvo como tabla con scroll horizontal contenido porque el número de columnas
  (5) y su naturaleza tabular (comparar código/fecha/estado entre pacientes) se pierde si se
  convierte en tarjetas. Es una decisión, no un olvido.
