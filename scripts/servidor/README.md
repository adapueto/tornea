# Scripts del servidor (Administración de SO)

Scripts para administrar el servidor AlmaLinux donde corre Tornea con Docker (`docker-compose.prod.yml`).

En el servidor **no** están instalados Apache ni MySQL (no existen `httpd` ni `mysqld`). Corren dentro de dos contenedores:

| Contenedor | Qué es |
|---|---|
| `web` | Apache + PHP (la página) |
| `db`  | MySQL 8 (la base de datos) |

Por eso los scripts usan `docker compose` para los contenedores y `systemctl` solo para los servicios del sistema (`docker`, `sshd`, `firewalld`, `crond`).

| Script | Qué hace |
|---|---|
| `config.sh` | Rutas y nombres comunes. No se ejecuta solo, lo cargan los demás |
| `backup_tornea.sh` | Respaldo diario: completo los domingos, incremental el resto de los días, y la base entera todos los días |
| `monitoreo_tornea.sh` | CPU, RAM, disco, Docker, contenedores, salud de la base y si la página responde. Si un contenedor está caído intenta levantarlo |
| `gestion_servicios.sh` | Menú para iniciar, detener o reiniciar servicios del sistema o contenedores |
| `gestion_usuarios.sh` | Menú para crear, listar, modificar y eliminar usuarios de Linux |
| `cron/tornea` | Programa el respaldo (02:00) y el monitoreo (cada 30 minutos), para el repo clonado |
| `cron/crontab_root.txt` | Lo mismo, para los scripts sueltos en `/home/terobyte/scripts` (crontab de root) |

Todos se corren como root (`sudo`).

## Instalación

Se asume que el repo está clonado en `/home/terobyte/tornea` (como en la guía de despliegue: `git clone` en el home) y que la app ya está levantada:

```bash
cd /home/terobyte/tornea
cp .env.example .env    # y poner contraseñas propias
docker compose -f docker-compose.prod.yml up -d --build
```

Los scripts toman la carpeta del proyecto a partir de dónde están, así que no hace falta cambiar nada en ellos. Lo único con rutas fijas es el archivo de cron: si el repo está en otra carpeta, hay que cambiar `/home/terobyte/tornea` ahí.

```bash
# Que se puedan ejecutar
sudo chmod +x /home/terobyte/tornea/scripts/servidor/*.sh

# Programar respaldo y monitoreo (cron lee /etc/cron.d solo)
sudo cp /home/terobyte/tornea/scripts/servidor/cron/tornea /etc/cron.d/tornea
sudo chmod 644 /etc/cron.d/tornea
```

### Opción sin git: scripts sueltos en /home/terobyte/scripts

Los scripts también se pueden bajar uno por uno a otra carpeta. El proyecto (con `docker-compose.prod.yml` y `.env`) tiene que seguir en el servidor, porque los scripts manejan esos contenedores. Por defecto lo buscan en `/home/terobyte/tornea`. Si está en otro lado, cambiá `DIR_PROYECTO_SUELTO` en `config.sh`.

```bash
mkdir -p /home/terobyte/scripts && cd /home/terobyte/scripts
URL=https://raw.githubusercontent.com/adapueto/tornea/main/scripts/servidor
for f in config.sh backup_tornea.sh monitoreo_tornea.sh gestion_servicios.sh gestion_usuarios.sh; do
  curl -fsSO "$URL/$f"
done
curl -fsSO "$URL/cron/crontab_root.txt"
chmod +x *.sh
```

`config.sh` es obligatorio: los demás lo cargan.

El cron va en el crontab de **root**, no en el de terobyte, porque los scripts necesitan root. No importa en qué carpeta estés al instalarlo: cron usa las rutas completas que están en el archivo.

```bash
sudo crontab -l                      # si root ya tiene tareas, sumalas a crontab_root.txt antes
sudo crontab crontab_root.txt        # instala (reemplaza lo que hubiera)
sudo crontab -l                      # comprobar
```

## Respaldos

Quedan en `/var/backups/tornea` (solo root puede leerlos):

```
completos/       proyecto_completo_FECHA.tar.gz + db_FECHA.sql.gz   (domingos)
incrementales/   proyecto_inc_FECHA.tar.gz      + db_FECHA.sql.gz   (lunes a sábado)
proyecto.snar    archivo de control de tar (qué había en el último respaldo)
backup.log
```

- El incremental guarda solo lo que cambió desde el respaldo del día anterior. Si todavía no hay ningún completo (la primera vez), hace uno completo aunque no sea domingo.
- La base se respalda entera todos los días. La app guarda todo en la base, así que es lo más importante de respaldar.
- El volcado lo hace `mysqldump` dentro del contenedor `db`, con el usuario `tornea_backup`, que solo tiene `SELECT` y `LOCK TABLES`. La contraseña sale del `.env` (`DB_BACKUP_PASSWORD`) y no está escrita en el script.
- Se borran los archivos con más de 28 días.

### Restaurar

Base de datos (reemplaza los datos actuales):

```bash
cd /home/terobyte/tornea
gunzip -c /var/backups/tornea/incrementales/db_2026-10-16.sql.gz \
  | docker compose -f docker-compose.prod.yml exec -T db sh -c 'mysql -uroot -p"$MYSQL_ROOT_PASSWORD" tornea'
```

Archivos: primero el último completo y después cada incremental en orden de fecha:

```bash
mkdir /tmp/restaurado && cd /tmp/restaurado
tar -xzf /var/backups/tornea/completos/proyecto_completo_2026-10-11.tar.gz --listed-incremental=/dev/null
tar -xzf /var/backups/tornea/incrementales/proyecto_inc_2026-10-12.tar.gz --listed-incremental=/dev/null
# ... hasta el día que se quiera recuperar
```

## Monitoreo

Escribe en `/var/log/tornea/monitoreo.log`. Para ver las alertas:

```bash
grep ALERTA /var/log/tornea/monitoreo.log
```
