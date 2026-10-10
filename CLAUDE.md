# Tornea — contexto del proyecto

App web para gestionar torneos de cualquier deporte (liga, eliminación directa, sistema suizo).

## Requerimientos

Antes de agregar o cambiar funcionalidad, revisar `docs/requerimientos.md` — es la transcripción de `Requerimientos.pdf` (RF-01 a RF-66, RNF-01 a RNF-38 y el resumen del modelo MER). Cada pantalla o campo nuevo debería poder trazarse a algún RF/RNF de ahí, o marcarse explícitamente como fuera de alcance.

## Estado actual

Tercera entrega: sistema completo y portable con Docker. Funcionan usuarios y roles, torneos de los tres tipos, inscripciones, equipos permanentes, rondas, resultados con tabla automática, panel de administración (reportes, usuarios, módulos, configuración e historial) y auditoría. El detalle de qué hay y cómo levantarlo está en `README.md`.

## Stack (según RNF)

- PHP 8.2 + MySQL con arquitectura MVC (RNF-02, RNF-06, RNF-07): `app/models`, `app/controllers`, `app/views`
- HTML5 semántico, CSS3 puro (sin frameworks) y JavaScript solo donde hace falta (RNF-05)
- Docker: `php:8.2-apache` + `mysql:8.0` (RNF-28)

## Convenciones de este repo

- Ramas de trabajo: `<número-de-issue>-<slug-descriptivo>` (ej. `1-create-register-page`).
- Merge a `main` vía Pull Request en GitHub (no push directo a main).
- Mensajes de commit en español, en modo imperativo.
