#!/bin/bash
###############################################################################
# gestion_usuarios.sh
# Proyecto: Tornea - Sistema de Gestión Deportiva Modular
# Materia: Administración de Sistemas Operativos - Primera entrega
# Sistema operativo destino: AlmaLinux
#
# Descripción: Script de gestión de usuarios del sistema operativo (Linux).
#              Permite crear, listar, modificar y eliminar cuentas de usuario
#              del servidor mediante un menú interactivo.
#
#
###############################################################################

# ---------------------------------------------------------------------------
# Colores para que la salida sea más legible en la terminal
# ---------------------------------------------------------------------------
VERDE='\033[0;32m'
ROJO='\033[0;31m'
AMARILLO='\033[1;33m'
AZUL='\033[0;34m'
SIN_COLOR='\033[0m'

# ---------------------------------------------------------------------------
# En AlmaLinux/RHEL, el grupo que otorga privilegios de administrador
# (sudo) es "wheel", a diferencia de Ubuntu/Debian donde es "sudo".
# Lo definimos como variable para que sea fácil de mantener.
# ---------------------------------------------------------------------------
GRUPO_ADMIN="wheel"

# ---------------------------------------------------------------------------
# Verificar que el script se ejecute como root (o con sudo)
# La gestión de usuarios requiere privilegios de administrador.
# ---------------------------------------------------------------------------
if [ "$EUID" -ne 0 ]; then
    echo -e "${ROJO}Error: este script debe ejecutarse como root o con sudo.${SIN_COLOR}"
    echo "Uso: sudo ./gestion_usuarios.sh"
    exit 1
fi

# ---------------------------------------------------------------------------
# FUNCIÓN: crear_usuario
# Crea un nuevo usuario del sistema, validando que no exista previamente.
# Permite elegir entre un usuario interactivo (persona) o una cuenta de
# servicio (sin acceso a login).
# ---------------------------------------------------------------------------
crear_usuario() {
    echo -e "${AZUL}--- Crear usuario ---${SIN_COLOR}"
    read -p "Nombre del nuevo usuario: " nombre

    # Validar que se ingresó un nombre
    if [ -z "$nombre" ]; then
        echo -e "${ROJO}El nombre no puede estar vacío.${SIN_COLOR}"
        return
    fi

    # Validar el formato: minúsculas, números, guion y guion bajo,
    # empezando por letra o guion bajo (lo que acepta useradd)
    if ! [[ "$nombre" =~ ^[a-z_][a-z0-9_-]*$ ]]; then
        echo -e "${ROJO}Nombre inválido: usá minúsculas, números, - o _, empezando por una letra.${SIN_COLOR}"
        return
    fi

    # Validar que el usuario no exista ya en el sistema
    if id "$nombre" &>/dev/null; then
        echo -e "${ROJO}El usuario '$nombre' ya existe.${SIN_COLOR}"
        return
    fi

    # Preguntar qué tipo de cuenta se va a crear.
    # - Usuario interactivo: persona que inicia sesión (shell /bin/bash)
    # - Cuenta de servicio: solo para correr un servicio (shell /sbin/nologin)
    echo "¿Qué tipo de cuenta querés crear?"
    echo "  1) Usuario interactivo (una persona que inicia sesión)"
    echo "  2) Cuenta de servicio (sin inicio de sesión, /sbin/nologin)"
    read -p "Opción [1-2]: " tipo_cuenta

    case $tipo_cuenta in
        1)
            # Usuario interactivo: con carpeta home (-m) y shell bash (-s)
            useradd -m -s /bin/bash "$nombre"
            ;;
        2)
            # Cuenta de servicio: shell /sbin/nologin para impedir el login.
            # Es una buena práctica de seguridad para cuentas que solo
            # ejecutan servicios (por ejemplo apache o mysql).
            useradd -r -s /sbin/nologin "$nombre"
            ;;
        *)
            echo -e "${ROJO}Opción inválida. Se cancela la creación.${SIN_COLOR}"
            return
            ;;
    esac
	# Si el comando anterior se hizo correctamente entonces va a ejecutarse este if
    if [ $? -eq 0 ]; then
        echo -e "${VERDE}Usuario '$nombre' creado correctamente.${SIN_COLOR}"

        # Las cuentas de servicio no necesitan contraseña ni grupo admin,
        # porque no se usan para iniciar sesión. Solo configuramos eso
        # para los usuarios interactivos.
        if [ "$tipo_cuenta" -eq 1 ]; then
            # Asignar contraseña
            echo "Definí una contraseña para '$nombre':"
            passwd "$nombre"

            # Preguntar si se agrega al grupo de administradores (wheel)
            read -p "¿Darle permisos de administrador (grupo $GRUPO_ADMIN)? [s/N]: " resp
            if [[ "$resp" =~ ^[sS]$ ]]; then
                usermod -aG "$GRUPO_ADMIN" "$nombre"
                echo -e "${VERDE}'$nombre' agregado al grupo $GRUPO_ADMIN.${SIN_COLOR}"
            fi
        fi
    else
        echo -e "${ROJO}Ocurrió un error al crear el usuario.${SIN_COLOR}"
    fi
}

# ---------------------------------------------------------------------------
# FUNCIÓN: listar_usuarios
# Muestra los usuarios "reales" del sistema (UID >= 1000), es decir, los
# creados por un administrador, no los usuarios internos del sistema.
# Se agrega el shell para poder distinguir cuentas interactivas de
# cuentas de servicio.
# ---------------------------------------------------------------------------
listar_usuarios() {
    echo -e "${AZUL}--- Usuarios del sistema (creados manualmente) ---${SIN_COLOR}"
    # El archivo /etc/passwd guarda todos los usuarios.
    # awk filtra los que tienen UID >= 1000 y != 65534 (usuario 'nobody')
    # Mostramos usuario, UID, home y shell (última columna de /etc/passwd)
    awk -F: '$3 >= 1000 && $3 != 65534 {print "Usuario: " $1 "  |  UID: " $3 "  |  Home: " $6 "  |  Shell: " $7}' /etc/passwd
}

# ---------------------------------------------------------------------------
# FUNCIÓN: modificar_usuario
# Permite cambiar la contraseña o los grupos de un usuario existente.
# ---------------------------------------------------------------------------
modificar_usuario() {
    echo -e "${AZUL}--- Modificar usuario ---${SIN_COLOR}"
    read -p "Nombre del usuario a modificar: " nombre

    # Validar que el usuario exista
    if ! id "$nombre" &>/dev/null; then
        echo -e "${ROJO}El usuario '$nombre' no existe.${SIN_COLOR}"
        return
    fi

    echo "¿Qué querés modificar?"
    echo "  1) Cambiar contraseña"
    echo "  2) Agregar a un grupo"
    read -p "Opción: " opcion_mod

    case $opcion_mod in
        1)
            passwd "$nombre"
            ;;
        2)
            read -p "Nombre del grupo: " grupo
            # Validar que el grupo exista
            if getent group "$grupo" &>/dev/null; then
                usermod -aG "$grupo" "$nombre"
                echo -e "${VERDE}'$nombre' agregado al grupo '$grupo'.${SIN_COLOR}"
            else
                echo -e "${ROJO}El grupo '$grupo' no existe.${SIN_COLOR}"
            fi
            ;;
        *)
            echo -e "${ROJO}Opción inválida.${SIN_COLOR}"
            ;;
    esac
}

# ---------------------------------------------------------------------------
# FUNCIÓN: eliminar_usuario
# Elimina un usuario del sistema, con confirmación previa.
# ---------------------------------------------------------------------------
eliminar_usuario() {
    echo -e "${AZUL}--- Eliminar usuario ---${SIN_COLOR}"
    read -p "Nombre del usuario a eliminar: " nombre

    # Validar que el usuario exista
    if ! id "$nombre" &>/dev/null; then
        echo -e "${ROJO}El usuario '$nombre' no existe.${SIN_COLOR}"
        return
    fi

    # Confirmación de seguridad antes de borrar
    read -p "¿Seguro que querés eliminar a '$nombre'? Esto es irreversible [s/N]: " confirmar
    if [[ "$confirmar" =~ ^[sS]$ ]]; then
        # Preguntar si se borra también su carpeta home
        read -p "¿Eliminar también su carpeta home? [s/N]: " borrar_home
        if [[ "$borrar_home" =~ ^[sS]$ ]]; then
            userdel -r "$nombre"   # -r elimina también el home
        else
            userdel "$nombre"
        fi
        # userdel falla, por ejemplo, si el usuario tiene una sesión abierta
        if [ $? -eq 0 ]; then
            echo -e "${VERDE}Usuario '$nombre' eliminado.${SIN_COLOR}"
        else
            echo -e "${ROJO}No se pudo eliminar a '$nombre'.${SIN_COLOR}"
        fi
    else
        echo -e "${AMARILLO}Operación cancelada.${SIN_COLOR}"
    fi
}

# ---------------------------------------------------------------------------
# MENÚ PRINCIPAL
# Bucle que muestra las opciones hasta que el usuario elige salir.
# ---------------------------------------------------------------------------
while true; do
    echo ""
    echo -e "${AZUL}=============================================${SIN_COLOR}"
    echo -e "${AZUL}   GESTIÓN DE USUARIOS - Servidor Tornea${SIN_COLOR}"
    echo -e "${AZUL}=============================================${SIN_COLOR}"
    echo "  1) Crear usuario"
    echo "  2) Listar usuarios"
    echo "  3) Modificar usuario"
    echo "  4) Eliminar usuario"
    echo "  5) Salir"
    echo -e "${AZUL}=============================================${SIN_COLOR}"
    read -p "Elegí una opción [1-5]: " opcion

    case $opcion in
        1) crear_usuario ;;
        2) listar_usuarios ;;
        3) modificar_usuario ;;
        4) eliminar_usuario ;;
        5)
            echo -e "${VERDE}¡Hasta luego!${SIN_COLOR}"
            exit 0
            ;;
        *)
            echo -e "${ROJO}Opción inválida. Elegí un número del 1 al 5.${SIN_COLOR}"
            ;;
    esac
done
