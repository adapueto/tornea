# Tornea

Proyecto final de año: una app web para gestionar torneos de **cualquier deporte** (fútbol amateur, básquet, eSports, y más). Permite crear torneos, sumar participantes y seguir los resultados desde un solo lugar.

## Estado actual

🚧 En construcción. Por ahora el foco está en el **maquetado (HTML + CSS) mobile-first** de todas las pantallas principales. La conexión a base de datos y la lógica funcional (login, registro, creación de torneos, etc.) se van a agregar más adelante.

Los requerimientos completos del proyecto (RF, RNF y el modelo MER) están en [`docs/requerimientos.md`](docs/requerimientos.md).

## Páginas

| Página | Descripción |
| --- | --- |
| `index.html` | Home: hero, torneos destacados y features |
| `login.html` | Inicio de sesión |
| `register.html` | Registro (nombre, apellido, email, contraseña) |
| `torneos.html` | Listado completo de torneos publicados |
| `crear-torneo.html` | Formulario para crear un torneo |
| `perfil.html` | Vista de perfil del participante, con los torneos que publicó (datos de ejemplo) |
| `perfil-editar.html` | Formulario para editar los datos del perfil |
| `torneo-detalle-liga.html` | Detalle de un torneo tipo liga: tabla de posiciones y calendario/resultados |
| `torneo-detalle-eliminacion.html` | Detalle de un torneo de eliminación directa: llaves |
| `torneo-detalle-suizo.html` | Detalle de un torneo de sistema suizo: emparejamientos y clasificación |

Las tres páginas `torneo-detalle-*` son una plantilla estática por **tipo** de torneo (no una por torneo individual), pensada para el momento en que exista backend: ahí se van a fusionar en una sola vista dinámica `torneo-detalle.php?id=`. Se llega a ellas desde el botón "Ver detalle" de las tarjetas de torneo en `index.html`, `torneos.html` y `perfil.html`.

`perfil.html` y `perfil-editar.html` todavía no están enlazadas desde el resto del sitio — eso depende de tener sesión real.

## Stack

- **HTML5** semántico
- **CSS3** puro, mobile-first (sin frameworks todavía — Flexbox y Grid, con `@media (min-width: ...)` para tablet/laptop)
- Google Fonts: `Baloo 2` (títulos), `Nunito Sans` (texto) y `Material Symbols Rounded` (íconos del hero)
- Próximamente: JavaScript y PHP + MySQL (arquitectura MVC) para persistir torneos, usuarios y resultados

## Estructura del proyecto

```
tornea/
├── index.html                       # Home
├── login.html                        # Inicio de sesión
├── register.html                     # Registro
├── torneos.html                       # Listado de torneos
├── crear-torneo.html                  # Crear torneo
├── perfil.html                         # Vista de perfil + torneos publicados
├── perfil-editar.html                  # Editar perfil
├── torneo-detalle-liga.html            # Detalle de torneo — tipo liga
├── torneo-detalle-eliminacion.html     # Detalle de torneo — eliminación directa
├── torneo-detalle-suizo.html           # Detalle de torneo — sistema suizo
├── css/
│   ├── style.css          # Estilos base (header, hero, features, footer, íconos)
│   ├── auth.css            # Login, registro, crear torneo, editar perfil
│   ├── torneos.css         # Torneos destacados y listado de torneos (componente .torneo-card)
│   ├── torneo-detalle.css  # Llaves, tabla de posiciones y calendario de las páginas de detalle
│   └── perfil.css          # Vista de perfil
├── img/
│   └── logo.png
├── docs/
│   └── requerimientos.md  # RF, RNF y resumen del MER
├── CLAUDE.md               # Contexto del proyecto para Claude Code
├── README.md
└── .claude/
    └── skills/
        └── git-sync/       # Skill para guardar/subir cambios a GitHub
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

## Cómo levantarlo con Docker

Requiere Docker Desktop. Desde la raíz del proyecto:

```bash
docker compose up --build
```

- App: <http://localhost:8080> (redirige a `/tornea/`)
- MySQL: `localhost:3307` (usuario `root`, contraseña `root_dev`)

La primera vez (volumen vacío) MySQL ejecuta solo, en orden, los scripts de `/docker-entrypoint-initdb.d/`:

1. `database/tornea.sql` — estructura (DDL)
2. `database/docker/02-dcl.sh` — usuarios `tornea_app`, `tornea_readonly`, `tornea_backup` y sus permisos (DCL)
3. `database/seed.sql` — datos de prueba

La app se conecta con `tornea_app` (solo `SELECT/INSERT/UPDATE/DELETE`). Las contraseñas se pueden cambiar copiando `.env.example` a `.env`.

**Cuentas de prueba** (contraseña `tornea123` para todas): `admin@tornea.test` (admin), usuarios 2 a 13 son organizadores y el resto participantes. Los emails se pueden ver con `SELECT id, email FROM usuarios;`.

Para borrar la base y volver a cargar todo desde cero (por ejemplo, después de cambiar `tornea.sql` o `seed.sql`):

```bash
docker compose down -v
```

### Datos de prueba

`database/seed.sql` se genera con `python database/tools/generar_seed.py` (determinístico, sin dependencias). Tiene 120 usuarios, 60 torneos de los tres tipos y en todos los estados, equipos con invitaciones, inscripciones aprobadas/pendientes/rechazadas, rondas y enfrentamientos coherentes con cada tipo (todos contra todos, llaves donde avanza el ganador, suizo sin repetir rival), resultados, tabla de posiciones calculada y auditoría. Todas las tablas superan los 50 registros, salvo `roles` (son 3 por definición).

## Próximos pasos

- [x] Maquetar vistas de detalle de torneo (calendario, resultados, tabla de posiciones, llaves)
- [ ] Definir los deportes que va a soportar la plataforma
- [ ] Sumar interactividad con JavaScript (estado de sesión, guardado de cambios de perfil, etc.)
- [ ] Conectar a una base de datos (torneos, usuarios, resultados) con PHP + MySQL — ahí las 3 plantillas `torneo-detalle-*` se fusionan en una sola vista dinámica
- [ ] Deploy

## Git

El repo está conectado a GitHub (`adapueto/tornea`). El flujo de trabajo es: un issue por cada tarea, una rama `<número-de-issue>-<slug>` desde `main`, y merge vía Pull Request — no se pushea directo a `main`.

Para el día a día de guardar avances, está la skill `/git-sync` (ver `.claude/skills/git-sync/SKILL.md`).
