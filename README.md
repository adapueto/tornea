# Tornea

Proyecto final de año: una app web para gestionar torneos de **cualquier deporte** (fútbol amateur, básquet, vóley, ajedrez, eSports, y más). Permite crear torneos, inscribir participantes o equipos, generar las rondas, cargar resultados y seguir la tabla o las llaves desde un solo lugar.

Los requerimientos completos del proyecto (RF, RNF y el modelo MER) están en [`docs/requerimientos.md`](docs/requerimientos.md).

## Qué se puede hacer

| Quién | Qué |
| --- | --- |
| Público | Ver los torneos publicados, buscarlos y filtrarlos; ver tablas, calendarios, resultados y llaves |
| Participante | Registrarse, editar su perfil, inscribirse en torneos, armar equipos permanentes, invitar integrantes e inscribir el equipo en varios torneos |
| Organizador | Crear torneos (al crear el primero, la cuenta pasa sola a organizador), editarlos, publicarlos, aprobar o rechazar inscripciones, iniciar el torneo, generar las rondas, cargar y corregir resultados, cerrar rondas y finalizar el torneo |
| Administrador | Todo lo anterior en cualquier torneo, más el panel de administración: reportes, todos los torneos, usuarios y roles, módulos de competencia, configuración e historial de cambios |

### Tipos de torneo

- **Liga:** todos contra todos (método del círculo). Se arma el fixture completo y se juega una fecha por vez. Con cantidad impar, a cada uno le toca descansar una fecha.
- **Eliminación directa:** la llave se sortea; si la cantidad no es potencia de 2, algunos pasan directo. Al cerrar cada ronda, los ganadores se cruzan solos en la siguiente, hasta la final. No hay empates.
- **Sistema suizo:** la primera ronda se sortea; desde la segunda se enfrentan participantes con puntajes parecidos, sin repetir rival. Con cantidad impar queda uno libre, que suma una victoria.

Cada resultado actualiza la tabla de posiciones o la llave automáticamente, y cada cambio queda registrado en la auditoría (quién, qué y cuándo).

## Stack

- **PHP 8.2** con arquitectura **MVC** (modelos, controladores y vistas separados), sin frameworks
- **MySQL 8 / MariaDB** con PDO, consultas preparadas y transacciones
- **HTML5** semántico y **CSS3** puro, mobile-first (Flexbox y Grid)
- **JavaScript** para el menú lateral en celulares y las confirmaciones antes de acciones que no se pueden deshacer
- **Docker** (`php:8.2-apache` + `mysql:8.0`)
- Google Fonts: `Baloo 2` (títulos), `Nunito Sans` (texto) y `Material Symbols Rounded` (íconos)

## Estructura del proyecto

```
tornea/
├── index.php                      # Página principal
├── app/
│   ├── controllers/               # Reciben los formularios (POST) y redirigen con un mensaje
│   │   ├── UsuarioController.php  #   registro, login, logout, perfil
│   │   ├── TorneoController.php   #   crear, editar, publicar, iniciar, eliminar
│   │   ├── InscripcionController.php  # inscribirse, cancelar, aprobar, rechazar
│   │   ├── EquipoController.php   #   equipos, invitaciones e inscripción de equipos
│   │   ├── RondaController.php    #   generar rondas, cargar resultados, cerrar ronda, finalizar
│   │   └── AdminController.php    #   usuarios, roles, módulos y configuración
│   ├── models/                    # Acceso a la base y reglas del negocio
│   │   ├── usuario.php, torneo.php, participante.php, equipo.php
│   │   ├── ronda.php              #   fixture, llaves, emparejamiento suizo y tabla de posiciones
│   │   ├── admin.php              #   reportes, usuarios e historial de cambios
│   │   ├── modulo.php             #   tipos de torneo habilitados
│   │   └── configuracion.php      #   puntos por victoria/empate y mínimo de participantes
│   ├── views/                     # Páginas
│   │   ├── torneos.php            #   listado con buscador y filtros
│   │   ├── torneo-detalle.php     #   detalle de un torneo (una vista para los tres tipos)
│   │   ├── crear-torneo.php, editar-torneo.php
│   │   ├── equipos.php, equipo.php
│   │   ├── perfil.php, perfil-editar.php, login.php, register.php
│   │   ├── admin.php              #   panel de administración
│   │   └── partials/              #   header, footer, tarjetas y formularios reutilizables
│   └── helpers/formato.php        # Funciones para mostrar datos (escape, fechas, etiquetas)
├── config/database.php            # Conexión PDO (lee DB_HOST, DB_NAME, DB_USER, DB_PASS)
├── css/                           # Un archivo por sección del sitio
├── js/menu.js                     # Menú lateral para celulares
├── img/
├── database/
│   ├── tornea.sql                 # Estructura (DDL) y datos fijos (roles, módulos, configuración)
│   ├── seed.sql                   # Datos de prueba
│   ├── tools/generar_seed.py      # Genera seed.sql
│   ├── dcl.example.sql            # Usuarios y permisos de MySQL (instalación local)
│   └── docker/02-dcl.sh           # Usuarios y permisos de MySQL (Docker)
├── docker/php.ini
├── Dockerfile
├── docker-compose.yml
└── docs/requerimientos.md         # RF, RNF y resumen del MER
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

Para borrar la base y volver a cargar todo desde cero (por ejemplo, después de cambiar `tornea.sql` o `seed.sql`):

```bash
docker compose down -v
```

## Cómo levantarlo con XAMPP (sin Docker)

1. Copiar o enlazar la carpeta del proyecto en `C:\xampp\htdocs\tornea` (las rutas del sitio empiezan con `/tornea/`).
2. Iniciar Apache y MySQL desde el panel de XAMPP.
3. Cargar la base desde la carpeta del proyecto:

   ```bash
   C:/xampp/mysql/bin/mysql.exe -uroot -e "DROP DATABASE IF EXISTS tornea"
   ```

   ```bash
   C:/xampp/mysql/bin/mysql.exe -uroot --default-character-set=utf8mb4 < database/tornea.sql
   ```

   ```bash
   C:/xampp/mysql/bin/mysql.exe -uroot --default-character-set=utf8mb4 tornea < database/seed.sql
   ```

4. Entrar a <http://localhost/tornea/>.

Sin variables de entorno, la app se conecta a `localhost` con el usuario `root` sin contraseña (lo que trae XAMPP). Cada vez que cambia `tornea.sql` hay que repetir el paso 3.

## Cuentas de prueba

Todas tienen la contraseña `tornea123`:

- `admin@tornea.test` — administrador
- usuarios 2 a 13 — organizadores
- el resto — participantes

Los emails se pueden ver con `SELECT id, email FROM usuarios;`.

### Datos de prueba

`database/seed.sql` se genera con `python database/tools/generar_seed.py` (determinístico, sin dependencias). Tiene 150 usuarios, 60 torneos de los tres tipos y en todos los estados, 60 equipos permanentes (cada uno inscripto en varios torneos, sin que un jugador quede en dos equipos del mismo torneo) con sus invitaciones, inscripciones aprobadas/pendientes/rechazadas, rondas y enfrentamientos coherentes con cada tipo (todos contra todos, llaves donde avanza el ganador, suizo sin repetir rival), resultados, tabla de posiciones calculada y auditoría. Todas las tablas superan los 50 registros, salvo los catálogos `roles`, `modulos` y `configuracion` (3 filas cada uno por definición: los tres roles, los tres tipos de torneo y los tres valores configurables, que se cargan en `tornea.sql`).

## Git

El repo está conectado a GitHub (`adapueto/tornea`). Cada tarea se trabaja en su propia rama y se integra a `main` vía Pull Request — no se pushea directo a `main`. Los mensajes de commit van en español y en modo imperativo.

Para el día a día de guardar avances, está la skill `/git-sync` (ver `.claude/skills/git-sync/SKILL.md`).
