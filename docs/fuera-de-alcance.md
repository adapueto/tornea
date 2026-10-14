# Fuera de alcance

Requerimientos de [`requerimientos.md`](requerimientos.md) que no se hicieron, o que se hicieron solo en parte. Para cada uno se dice qué hay hoy, por qué quedó así y cómo se podría hacer más adelante.

Lo que no aparece acá está implementado.

## Resumen

| Requerimiento | Estado |
| --- | --- |
| RF-40 — Publicar rondas | No se hace (decisión de diseño) |
| RF-45 — Validar resultados | En parte |
| RF-49, RF-55 — Calendarios | En parte (orden de fechas, sin día y hora por partido) |
| RF-53 — Ver perfiles públicos | Trabajo futuro |
| RF-04, RF-05, RF-09, RF-10 — Modificar y eliminar usuarios | En parte (con límites a propósito) |
| RF-63, RNF-34 — Registrar los cambios | En parte (se sabe quién y cuándo, no qué cambió) |
| RNF-24 — Errores desde un único mecanismo | Hecho (falta unificar los avisos) |
| RNF-25 — Rutas claras (TICKET-7, front controller) | En parte (arranque común y acceso cerrado; rutas largas) |
| RNF-30 a RNF-33 — Respaldos y mantenimiento | En curso |
| RNF-35 — Seguridad (OWASP) | Hecho, con pendientes antes de publicar |
| RNF-36 — HTTPS al publicar | Pendiente del servidor |

Los números de TICKET son los de la lista interna de tareas de la entrega, no los issues de GitHub.

## Funcionales

### RF-40 — Publicar rondas

- **Hoy:** una ronda se ve en cuanto el organizador la genera. Sus estados son `pendiente`, `en_curso` y `finalizada`; no existe `publicada` (el MER pedía `generada`, `publicada`, `cerrada`).
- **Por qué:** el torneo ya es público desde que se publica, y un paso más ("generar" y después "publicar") no le aportaba nada a quien lo mira; solo le sumaba un clic al organizador.
- **Cómo se haría:** agregar `publicada` al enum de `rondas.estado`, una acción `publicar` en `RondaController` con su botón en `torneo-detalle.php`, y mostrar solo las rondas publicadas a quien no organiza el torneo.

### RF-45 — Validar resultados

- **Hoy:** no hay un estado de "resultado validado". Se cubre de otra forma: el marcador se revisa al cargarlo (enteros de 0 a 999, sin empate en eliminación directa) y solo pueden cargarlo o corregirlo el organizador del torneo o un administrador.
- **Por qué:** como los participantes no cargan resultados, no hace falta que otra persona los apruebe.
- **Cómo se haría:** si algún día cada participante cargara su propio resultado, sumar a `enfrentamientos` un estado "a confirmar" y que el organizador lo valide antes de que cuente en la tabla.

### RF-49 y RF-55 — Calendarios

- **Hoy:** el calendario es el orden de las fechas (liga) o rondas (eliminación y suizo), con quién juega contra quién en cada una. Solo el torneo tiene fecha de inicio y de fin; los partidos no tienen día ni hora.
- **Por qué:** en torneos amateur el día de cada partido lo arreglan los equipos entre ellos, y el sistema no maneja canchas ni horarios.
- **Cómo se haría:** una columna `fecha_hora` en `enfrentamientos` que el organizador complete al generar la ronda, y mostrarla en el detalle y en "Mi actividad".

### RF-53 — Ver perfiles públicos

- **Hoy:** la columna `usuarios.perfil_publico` existe y se puede cambiar desde "Editar perfil", pero no hay una página donde otra persona vea un perfil. Cada usuario solo ve el suyo.
- **Por qué:** se priorizaron las pantallas del torneo (tabla, llaves, resultados) y "Mi actividad" del participante.
- **Cómo se haría:** una vista `perfil-publico.php?id=…` que muestre nombre y torneos jugados solo si `perfil_publico = 1`, y links desde las tablas de posiciones. Antes habría que cambiar el valor por defecto de la columna a `0` (hoy es `1`), para que nadie quede público sin haberlo elegido.

### RF-04, RF-05, RF-09 y RF-10 — Modificar y eliminar usuarios

- **Hoy:** desde el panel de administración se puede crear usuarios, cambiarles el rol y eliminarlos. Nombre, email y contraseña los edita solo cada persona desde su perfil; el administrador no los cambia. Para eliminar hay límites: no se puede eliminar a uno mismo, a otro administrador (primero hay que cambiarle el rol) ni a alguien que organiza torneos, tiene inscripciones o está en un equipo.
- **Por qué:** borrar a alguien con historial dejaría torneos sin organizador y tablas de posiciones con huecos. El administrador no edita datos personales de otros para que cada persona sea dueña de sus datos.
- **Cómo se haría:** en lugar de borrar, una "baja" (columna `activo`) que impida entrar pero conserve todo. Eso también evitaría que la auditoría quede sin autor: hoy, al borrar una cuenta sin actividad, sus filas de auditoría (el registro y las ediciones de perfil) quedan con `usuario_id` vacío.

### RF-63 y RNF-34 — Registrar los cambios

- **Hoy:** la tabla `auditoria` guarda quién hizo el cambio, cuándo, qué acción (alta, cambio o baja) y en qué tabla y registro. No guarda qué valores cambiaron. Si se corrige un resultado de 2-1 a 1-1, el historial muestra que el resultado se modificó, pero no cuál era el anterior.
- **Por qué:** con quién, cuándo y dónde alcanza para la trazabilidad que pide RNF-37 sin hacer la tabla mucho más pesada.
- **Cómo se haría:** una columna `detalle` (JSON) con los valores de antes y de después, y mostrarla en el historial del panel de administración.

## No funcionales

### RNF-24 — Errores desde un único mecanismo

- **Hoy:** todas las páginas y controllers cargan primero `app/arranque.php`, que registra un manejador general (`set_exception_handler`): si un error se escapa sin que nadie lo ataje, el detalle queda en el log y la persona ve una página común "Algo salió mal" con código 500, sin rutas ni datos internos. La falla de conexión a la base usa la misma página, con código 503. Los errores esperados (datos inválidos, permisos) siguen el camino de siempre: el modelo devuelve `['exito' => …, 'mensaje' => …]` y el controller lo pasa a `$_SESSION['error']`.
- **Lo que falta:** cada vista muestra el aviso de `$_SESSION['error']` por su cuenta.
- **Cómo se haría:** mover ese aviso a un partial que usen todas las vistas.

### RNF-25 — Rutas claras (TICKET-7, front controller)

- **Hoy:** se hizo la parte de un front controller que no cambia las rutas:
  - **Arranque común:** todas las páginas y controllers empiezan cargando `app/arranque.php` (sesión, helpers y manejador de errores), en vez de repetir esas líneas cada una.
  - **Acceso cerrado por defecto (en Docker):** `docker/apache-tornea.conf` cierra todo `/tornea/` y abre solo lo que es web: `index.php`, `css/`, `js/`, `img/`, `app/views/` y `app/controllers/`. Modelos, helpers, partials, `config/`, la base, la documentación y los archivos de Docker responden 403. Es configuración de Apache dentro de la imagen: en una instalación sin Docker (XAMPP) no se aplica. Además, `.dockerignore` deja la documentación y los archivos de Docker fuera de la imagen de producción.
  - Las rutas siguen siendo las de los archivos, por ejemplo `/tornea/app/views/torneo-detalle.php?id=3`: predecibles (siguen las carpetas MVC), pero largas.
- **Por qué:** pasar a rutas cortas obliga a cambiar más de cien rutas escritas en vistas, controllers y JavaScript, a pocas semanas de la entrega. El riesgo de romper links era más alto que la mejora.
- **Cómo se haría:** activar `mod_rewrite` y mandar todas las visitas a un único `index.php` que, según la URL (`/tornea/torneos/3`), cargue la vista o el controller que corresponde. Con el arranque común ya hecho, ese archivo solo tendría que elegir la página.

### RNF-30 a RNF-33 — Respaldos y mantenimiento

- **Hoy:** la base ya tiene un usuario `tornea_backup` que solo puede leer y bloquear tablas (`SELECT, LOCK TABLES` en `database/docker/02-dcl.sh`), y los cambios importantes se hacen dentro de transacciones (si algo falla a mitad, no queda nada a medias). Pero todavía no hay un script que haga el respaldo ni una tarea programada: si se pierde el volumen de la base, se pierde todo.
- **Por qué:** está en curso (TICKET-10): se va a partir del script de respaldo que ya existe en el servidor.
- **Cómo se haría:** un script que corra `mysqldump --single-transaction --no-tablespaces` dentro del contenedor de la base con `tornea_backup` (sin `--no-tablespaces`, MySQL 8 pide un permiso que ese usuario no tiene), guarde el archivo con la fecha en una carpeta `backups/` (agregada a `.gitignore` y `.dockerignore`), borre los respaldos viejos y se ejecute todas las noches con `cron`.

### RNF-35 — Seguridad (OWASP)

- **Hoy:** consultas preparadas, contraseñas con `password_hash`, salida escapada, archivos internos cerrados al navegador (ver RNF-25), token CSRF en todos los formularios, cookie de sesión `HttpOnly` y `SameSite`, permisos por rol y un usuario de MySQL para la app con permisos mínimos.
- **Pendiente antes de publicar de verdad:**
  - `docker-compose.prod.yml` carga `database/seed.sql`, que trae cuentas de prueba con contraseña conocida (entre ellas un administrador). Sirve para la demo; en un servidor real hay que sacar esa línea y crear el administrador con una contraseña propia.
  - No hay límite de intentos de inicio de sesión.
  - Al iniciar sesión se guarda en la sesión la fila completa del usuario, incluido el hash de la contraseña.
- **Riesgo aceptado:** el registro avisa "El email ya está registrado", lo que deja saber si un email tiene cuenta. Se dejó así porque es más claro para quien se registra.

### RNF-36 — HTTPS al publicar

- **Hoy:** `docker-compose.prod.yml` sirve la app por HTTP en el puerto 80.
- **Por qué:** HTTPS necesita un dominio y un certificado, que dependen del servidor donde se publique, no del código.
- **Cómo se haría:** poner un proxy (por ejemplo Caddy o Nginx con Let's Encrypt) delante del contenedor web y, ya con HTTPS, activar `session.cookie_secure = 1` en `docker/php.prod.ini` para que la cookie de sesión solo viaje cifrada.
