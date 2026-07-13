---
name: git-sync
description: Usar cuando el usuario pida guardar, commitear, subir (push) o traer (pull) cambios del proyecto Tornea a/desde GitHub — frases como "guardá esto", "subí los cambios", "hacé commit", "traé lo último de github", "pull", "push", "sincronizá". Maneja el flujo completo de git para este proyecto.
---

# Git sync — Tornea

Flujo de trabajo de git para este proyecto. Seguir estos pasos según lo que pida el usuario.

## 1. Antes que nada

- Correr `git status` para ver el estado del repo.
- Si el usuario pidió "traer cambios" / "pull", ir directo al paso 4.
- Si pidió "guardar" / "commitear" / "subir", seguir con el paso 2.

## 2. Guardar cambios (commit)

- Revisar `git status` y `git diff` para entender qué cambió. No usar `git add -A` a ciegas: agregar por nombre los archivos relevantes (`git add index.html css/style.css ...`), evitando archivos que no deberían versionarse (por ejemplo `node_modules/`, `.env`, archivos temporales).
- Si es la primera vez que se versiona el proyecto y no hay un `.gitignore`, crear uno básico para HTML/CSS/JS (`node_modules/`, `.DS_Store`, `*.log`, etc.) antes de commitear.
- Escribir un mensaje de commit corto y en español, describiendo el "qué" en modo imperativo, por ejemplo:
  - `Maqueta el home con hero y sección de features`
  - `Ajusta estilos responsive del header`
- Crear el commit con:
  ```
  git commit -m "<mensaje>"
  ```

## 3. Subir a GitHub (push)

- Verificar si existe un remoto: `git remote -v`.
- **Si no hay remoto configurado**: avisar al usuario que todavía no hay un repositorio en GitHub conectado, y preguntarle si ya creó uno. Si tiene la URL, configurarlo con:
  ```
  git remote add origin <URL>
  git push -u origin main
  ```
- **Si ya hay remoto**: antes de pushear, confirmar con el usuario a qué rama se va a subir (normalmente la actual). Luego:
  ```
  git push
  ```
- Nunca usar `--force` / `--force-with-lease` salvo que el usuario lo pida explícitamente.

## 4. Traer cambios (pull)

- Antes de traer cambios, correr `git status` y avisar si hay cambios locales sin commitear (podrían generar conflicto).
- Traer los cambios con:
  ```
  git pull
  ```
- Si hay conflictos de merge, mostrárselos al usuario y resolverlos junto con él en vez de descartar cambios (`git checkout --ours/--theirs` solo si el usuario lo indica).

## 5. Al terminar

- Confirmar en una o dos líneas qué se hizo (commit creado, push realizado, o cambios traídos) y el estado final del repo.
