# Checklist de usabilidad y accesibilidad — Módulo Pacientes

Aplicado sobre el [prototipo navegable](../semana-11-mockup-prototipo/README.md). Basado en
heurísticas de Nielsen (usabilidad) y en pautas WCAG 2.1 nivel AA (accesibilidad), adaptado al
alcance de un módulo backend con UI de referencia.

Leyenda: ✅ cumple · ⚠️ cumple parcialmente · ❌ no cumple (ver `HALLAZGOS_MEJORAS.md`)

## Usabilidad (heurísticas de Nielsen)

| # | Heurística | Evaluación en el prototipo | Resultado |
|---|---|---|---|
| 1 | Visibilidad del estado del sistema | El estado del vínculo MPI (`auto_linked`/`manual_review`/`pending`) es visible en la tabla de búsqueda sin abrir detalle; el resultado del registro aparece de inmediato como banner. | ✅ |
| 2 | Coincidencia entre el sistema y el mundo real | Los tres desenlaces del registro usan lenguaje del dominio ("vínculo automático", "revisión pendiente"), no jerga técnica (`auto_linked` nunca se muestra crudo al usuario). | ✅ |
| 3 | Control y libertad del usuario | El usuario puede seguir buscando/registrando aunque haya un banner de resultado visible; nada bloquea la pantalla ni exige "Aceptar" para continuar. | ✅ |
| 4 | Consistencia y estándares | El mismo componente "pill" de estado se usa igual en la tabla de búsqueda y en la pantalla de revisión. | ✅ |
| 5 | Prevención de errores | El campo DPI muestra el formato esperado (13 dígitos) *antes* de escribir, no solo como error después. | ✅ |
| 6 | Reconocer antes que recordar | La pantalla de revisión muestra el score y los campos coincidentes junto a cada candidato — el usuario no necesita recordar por qué el sistema los marcó como posibles coincidencias. | ✅ |
| 7 | Flexibilidad y eficiencia de uso | La búsqueda filtra mientras se escribe, sin paso adicional de "Buscar". | ✅ |
| 8 | Diseño estético y minimalista | Un solo acento de color (teal) para acciones primarias; los colores de estado (verde/ámbar/morado/rojo) son semánticos, no decorativos. | ✅ |
| 9 | Ayudar a reconocer y recuperarse de errores | El error de DPI duplicado (409) indica el DPI en conflicto y enlaza a la búsqueda; no es un mensaje genérico. | ✅ |
| 10 | Ayuda y documentación | No hay ayuda contextual más allá del texto bajo el campo DPI (p. ej. no hay un "¿por qué pendiente?" expandible en el banner `pending`). | ⚠️ |

## Accesibilidad (WCAG 2.1 AA, adaptado)

| # | Criterio | Evaluación en el prototipo | Resultado |
|---|---|---|---|
| 1 | Etiquetas de formulario | Todo `<input>`/`<select>` tiene `<label for>` asociado (incluyendo el buscador, con `.visually-hidden`). | ✅ |
| 2 | No depender solo del color | Cada estado combina color **y** texto ("Vinculado (auto)", no solo un punto verde). | ✅ |
| 3 | Foco de teclado visible | `:focus-visible` con contorno de 2px en todos los controles interactivos. | ✅ |
| 4 | Tamaño de objetivo táctil | Botones con padding ≥ 8px vertical; en escritorio cumple, no se midió con dedo real en dispositivo táctil. | ⚠️ |
| 5 | Contraste de texto | Texto principal sobre fondo claro y sobre fondo oscuro verificado visualmente (>4.5:1 estimado); no se ejecutó un verificador automático de contraste. | ⚠️ |
| 6 | `prefers-reduced-motion` | La animación de carga (shimmer) se desactiva si el usuario lo pide en el sistema operativo. | ✅ |
| 7 | Idioma de la página | El documento no declara `lang="es"` explícitamente. | ❌ |
| 8 | Elementos deshabilitados y lectores de pantalla | "Registrar" y "Revisar" se ocultan del orden de tabulación con `disabled` para Médico/Enfermera, pero un lector de pantalla no anuncia *por qué* no están disponibles. | ❌ |
| 9 | Modo oscuro / alto contraste del sistema | La paleta completa está definida para tema claro y oscuro (`prefers-color-scheme` + `data-theme`). | ✅ |
| 10 | Navegación 100% por teclado | Se recorrió el flujo completo (cambiar rol, buscar, registrar, revisar) solo con Tab/Enter/flechas de `<select>`, sin usar el mouse. | ✅ |

## Resumen

- **Usabilidad:** 9/10 sin observaciones, 1 parcial (ayuda contextual).
- **Accesibilidad:** 6/10 completos, 2 parciales, 2 incumplidos (`lang`, botones deshabilitados sin explicación).

Detalle y priorización de cada hallazgo en [`HALLAZGOS_MEJORAS.md`](./HALLAZGOS_MEJORAS.md).
