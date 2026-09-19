# Hallazgos y mejoras priorizadas — Módulo Pacientes

Priorización estilo MoSCoW sobre los hallazgos del `CHECKLIST_USABILIDAD_ACCESIBILIDAD.md`.

## Must (bloquean una entrega accesible real)

| Hallazgo | Impacto | Mejora | Estado |
|---|---|---|---|
| Los enlaces de navegación deshabilitados (`disabled`) desaparecían del recorrido de teclado y un lector de pantalla los omitía sin explicar el motivo. | Un usuario con Médico/Enfermera que navega solo con teclado o con lector de pantalla no entendía por qué "Registrar" y "Revisar" no estaban ahí. | Cambiar `disabled` por `aria-disabled="true"` + `title` explicando el motivo por rol, manteniendo el control alcanzable por teclado. | ✅ **Corregido en esta misma entrega** — ver el prototipo republicado y el commit del cambio en `prototipo.html`. |
| El documento no declara idioma (`lang="es"`). | Un lector de pantalla puede pronunciar el español con reglas de inglés. | Declarar `lang="es"` en la raíz del documento. | ⚠️ **Limitación de la plataforma de publicación**: el Artifact envuelve el HTML en su propio `<html>` raíz y no expone ese atributo al autor del contenido. Queda documentado como riesgo a resolver cuando el flujo se implemente dentro del proyecto Vue real (`resources/views/app.blade.php` sí permite fijar `lang="es"` directamente). |

## Should (mejoran la experiencia, no bloquean)

| Hallazgo | Mejora propuesta | Prioridad |
|---|---|---|
| No hay verificación automática de contraste de color (solo inspección visual). | Correr un verificador (p. ej. axe DevTools o el contraste de Chrome DevTools) sobre la implementación Vue real antes de integrar a `develop`. | Should |
| El banner `pending` no explica *por qué* CENTRAL puede no estar disponible ni cuánto puede tardar la sincronización. | Agregar un enlace "¿Qué significa esto?" que abra una explicación breve (1–2 líneas) sin salir de la pantalla. | Should |
| El tamaño de los botones no se probó en un dispositivo táctil real (solo estimado en escritorio). | Validar con un dispositivo táctil real como parte de la semana 10 (diseño móvil). | Should |

## Could (mejoras menores, sin urgencia)

| Hallazgo | Mejora propuesta |
|---|---|
| La ayuda contextual es solo el texto bajo el campo DPI; no hay un ícono de ayuda ni tooltips en otros campos. | Evaluar si el resto de campos (nombres, fecha de nacimiento) necesita ayuda adicional una vez haya usuarios reales probando el flujo. |
| Los estados de las "pills" usan los mismos 4 colores en toda la app; no se probó con un simulador de daltonismo. | Correr un simulador de deuteranopia/protanopia sobre la implementación Vue real. |

## Trazabilidad

Cada fila de este documento corresponde a un ítem específico de
[`CHECKLIST_USABILIDAD_ACCESIBILIDAD.md`](./CHECKLIST_USABILIDAD_ACCESIBILIDAD.md) marcado ⚠️ o ❌.
Ningún hallazgo "Must" quedó sin una acción concreta (corregida o documentada como limitación).
