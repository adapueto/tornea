# Tornea — contexto del proyecto

App web para gestionar torneos de cualquier deporte (liga, eliminación directa, sistema suizo).

## Requerimientos

Antes de agregar o cambiar funcionalidad, revisar `docs/requerimientos.md` — es la transcripción de `Requerimientos.pdf` (RF-01 a RF-66, RNF-01 a RNF-38 y el resumen del modelo MER). Cada pantalla o campo nuevo debería poder trazarse a algún RF/RNF de ahí, o marcarse explícitamente como fuera de alcance.

## Estado actual

En fase de **maquetado HTML/CSS semántico** (primera entrega). Todavía no hay backend, JS, ni base de datos — eso llega después según RNF-02, RNF-05, RNF-06.

Páginas existentes: `index.html` (home), `login.html`, `register.html`.

## Stack (según RNF)

- HTML5 semántico, CSS3 puro (sin frameworks)
- Próximamente: PHP + MySQL + arquitectura MVC (RNF-02, RNF-06, RNF-07)

## Convenciones de este repo

- Ramas de trabajo: `<número-de-issue>-<slug-descriptivo>` (ej. `1-create-register-page`).
- Merge a `main` vía Pull Request en GitHub (no push directo a main).
- Mensajes de commit en español, en modo imperativo.
