# Tornea

Proyecto final de año: una app web para gestionar torneos de **cualquier deporte** (fútbol amateur, básquet, eSports, y más). Permite crear torneos, sumar participantes y seguir los resultados desde un solo lugar.

## Estado actual

🚧 En construcción. Por ahora el foco está en el **maquetado (HTML + CSS)** de la landing page / home. La conexión a base de datos y la lógica funcional (login, registro, creación de torneos, etc.) se van a agregar más adelante.

## Stack

- **HTML5** semántico
- **CSS3** puro (sin frameworks todavía — Flexbox y Grid)
- Google Fonts: `Baloo 2` (títulos) y `Nunito Sans` (texto)
- Próximamente: JavaScript y una base de datos para persistir torneos, usuarios y resultados

## Estructura del proyecto

```
tornea/
├── index.html          # Home de la aplicación
├── css/
│   └── style.css       # Estilos del home
├── README.md
└── .claude/
    └── skills/
        └── git-sync/    # Skill para guardar/subir cambios a GitHub
```

## Cómo verla localmente

Es HTML/CSS estático, no necesita instalación. Alcanza con abrir `index.html` en el navegador, o levantar un servidor simple:

```bash
# opción 1: abrir directo
start index.html          # Windows
open index.html           # macOS

# opción 2: servidor local (recomendado para evitar problemas de rutas)
npx serve .
# o
python -m http.server 5500
```

## Próximos pasos

- [ ] Maquetar el resto de las secciones (torneos destacados, detalle de torneo, login/registro)
- [ ] Definir los deportes que va a soportar la plataforma
- [ ] Sumar interactividad con JavaScript
- [ ] Conectar a una base de datos (torneos, usuarios, resultados)
- [ ] Deploy

## Git

El repo se inicializa localmente por ahora (todavía no hay remoto en GitHub). Cuando crees el repositorio en GitHub, conectalo con:

```bash
git remote add origin <URL-del-repo>
git push -u origin main
```

Para el día a día de guardar avances, está la skill `/git-sync` (ver `.claude/skills/git-sync/SKILL.md`).
