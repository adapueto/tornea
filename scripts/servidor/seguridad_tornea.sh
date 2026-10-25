#!/bin/bash
# ============================================
# Script de seguridad del servidor - Tornea
# Terobyte - Administración de SO
#
# Aplica y revisa la política de seguridad del servidor AlmaLinux:
# - firewalld: solo quedan abiertos SSH (22), HTTP (80) y HTTPS (443)
# - iptables: Docker agrega sus propias reglas y los puertos publicados por
#   los contenedores no pasan por las zonas de firewalld. Por eso, para
#   bloquear una IP también en la página, se usa la cadena DOCKER-USER, que
#   Docker deja reservada para reglas propias y revisa antes que las suyas.
# - fail2ban: bloquea por un rato las IP que fallan varias veces el login SSH
# - SSH: root no puede entrar directo (se entra como terobyte y se usa sudo)
# - ClamAV: antivirus, escaneo semanal del proyecto y carpetas temporales
# - SELinux en modo enforcing y actualizaciones de seguridad con dnf
#
# Uso: sudo ./seguridad_tornea.sh              (menú)
#      sudo ./seguridad_tornea.sh --escaneo    (antivirus, para el cron)
#      sudo ./seguridad_tornea.sh --reaplicar  (IPs bloqueadas, al arrancar)
# ============================================

source "$(dirname "$0")/config.sh"

LOG_ANTIVIRUS="/var/log/tornea/antivirus.log"
# Las reglas de DOCKER-USER se pierden al reiniciar: se guardan acá y se
# vuelven a cargar con --reaplicar (cron @reboot)
ARCHIVO_BLOQUEADAS="/etc/tornea/ips_bloqueadas"
CARPETAS_ESCANEO="$DIR_PROYECTO /home /tmp /var/tmp"

if [ "$EUID" -ne 0 ]; then
    echo "Error: este script debe ejecutarse como root o con sudo."
    echo "Uso: sudo ./seguridad_tornea.sh"
    exit 1
fi

mkdir -p /var/log/tornea /etc/tornea
touch "$ARCHIVO_BLOQUEADAS"

esta_instalado() {
    command -v "$1" > /dev/null 2>&1
}

confirmar() {
    read -r -p "$1 (s/n): " RESPUESTA
    [ "$RESPUESTA" = "s" ] || [ "$RESPUESTA" = "S" ]
}

# Acepta solo direcciones IPv4 (cuatro números de 0 a 255)
ip_valida() {
    [[ "$1" =~ ^[0-9]{1,3}(\.[0-9]{1,3}){3}$ ]] || return 1
    local IFS=.
    local PARTES=($1)
    for P in "${PARTES[@]}"; do
        [ "$P" -le 255 ] || return 1
    done
    return 0
}

estado_seguridad() {
    echo "== ESTADO DE SEGURIDAD =="
    echo ""
    echo "-- Firewall (firewalld) --"
    if systemctl is-active --quiet firewalld; then
        echo "Activo. Zona: $(firewall-cmd --get-default-zone 2>/dev/null)"
        echo "Servicios permitidos: $(firewall-cmd --zone=public --list-services 2>/dev/null)"
        echo "Puertos extra: $(firewall-cmd --zone=public --list-ports 2>/dev/null)"
    else
        echo "ALERTA: firewalld no esta activo"
    fi
    echo ""
    echo "-- SELinux --"
    if esta_instalado getenforce; then
        echo "Modo: $(getenforce)"
    else
        echo "No disponible"
    fi
    echo ""
    echo "-- SSH --"
    if esta_instalado sshd; then
        sshd -T 2>/dev/null | grep -Ei '^(permitrootlogin|maxauthtries|passwordauthentication) '
    fi
    echo ""
    echo "-- fail2ban --"
    if systemctl is-active --quiet fail2ban; then
        fail2ban-client status sshd 2>/dev/null | grep -E 'Currently failed|Currently banned|Banned IP'
    else
        echo "No esta activo (opcion 2 para instalarlo, 5 para configurarlo)"
    fi
    echo ""
    echo "-- Antivirus (ClamAV) --"
    if esta_instalado clamscan; then
        clamscan --version
        ULTIMO=$(grep -h "ESCANEO" "$LOG_ANTIVIRUS" 2>/dev/null | tail -1)
        echo "Ultimo escaneo: ${ULTIMO:-nunca}"
    else
        echo "No instalado (opcion 2)"
    fi
    echo ""
    echo "-- Usuarios con UID 0 (deberia ser solo root) --"
    awk -F: '$3 == 0 {print $1}' /etc/passwd
    echo ""
    echo "-- Puertos escuchando --"
    ss -tuln | awk 'NR == 1 || /LISTEN/'
    echo ""
    echo "-- IPs bloqueadas por el script --"
    if [ -s "$ARCHIVO_BLOQUEADAS" ]; then
        cat "$ARCHIVO_BLOQUEADAS"
    else
        echo "Ninguna"
    fi
}

instalar_herramientas() {
    echo "Se instalan firewalld, fail2ban y ClamAV (fail2ban y ClamAV vienen del repositorio EPEL)."
    confirmar "Continuar?" || return
    dnf install -y epel-release
    dnf install -y firewalld fail2ban clamav clamav-update
    # Descarga la base de firmas de virus (la primera vez tarda)
    freshclam
}

configurar_firewall() {
    echo "Se activa firewalld y se dejan abiertos solo ssh, http y https."
    confirmar "Continuar?" || return
    systemctl enable --now firewalld
    firewall-cmd --set-default-zone=public
    for SERVICIO in ssh http https; do
        firewall-cmd --permanent --zone=public --add-service="$SERVICIO" > /dev/null
    done
    # AlmaLinux trae abierto cockpit (panel web en el 9090) y no se usa.
    # dhcpv6-client se deja: solo recibe las respuestas cuando el servidor pide
    # su dirección IPv6, no abre ningún servicio
    firewall-cmd --permanent --zone=public --remove-service=cockpit > /dev/null 2>&1
    firewall-cmd --reload
    # Docker anota sus redes en la zona "docker" de firewalld solo al arrancar.
    # Si firewalld se activa con Docker corriendo, los contenedores no se pueden
    # hablar entre sí (web no llega a db: "No route to host"), así que se reinicia
    # Docker. Los contenedores vuelven solos (restart: unless-stopped)
    if systemctl is-active --quiet docker; then
        echo "Reiniciando Docker para que tome el firewall (la pagina se corta unos segundos)..."
        systemctl restart docker
    fi
    echo ""
    firewall-cmd --zone=public --list-all
    firewall-cmd --get-active-zones
}

ver_reglas_iptables() {
    echo "== REGLAS DE IPTABLES =="
    echo "-- Cadena INPUT (lo que entra al servidor) --"
    iptables -L INPUT -n -v --line-numbers
    echo ""
    echo "-- Cadena DOCKER-USER (se revisa antes de llegar a los contenedores) --"
    iptables -L DOCKER-USER -n -v --line-numbers 2>/dev/null || echo "No existe (Docker no esta corriendo)"
    echo ""
    echo "-- Puertos que Docker publica (tabla nat, cadena DOCKER) --"
    iptables -t nat -L DOCKER -n 2>/dev/null | grep DNAT || echo "Ninguno"
}

# Bloquea una IP para el servidor (firewalld) y para la página (DOCKER-USER)
aplicar_bloqueo() {
    firewall-cmd --permanent --add-rich-rule="rule family='ipv4' source address='$1' drop" > /dev/null
    if ! iptables -C DOCKER-USER -s "$1" -j DROP 2>/dev/null; then
        iptables -I DOCKER-USER -s "$1" -j DROP
    fi
}

bloquear_ip() {
    read -r -p "IP a bloquear: " IP
    if ! ip_valida "$IP"; then
        echo "La IP '$IP' no es valida."
        return
    fi
    if grep -qx "$IP" "$ARCHIVO_BLOQUEADAS"; then
        echo "La IP $IP ya estaba bloqueada."
        return
    fi
    aplicar_bloqueo "$IP"
    firewall-cmd --reload > /dev/null
    echo "$IP" >> "$ARCHIVO_BLOQUEADAS"
    echo "IP $IP bloqueada en firewalld y en la cadena DOCKER-USER."
}

desbloquear_ip() {
    read -r -p "IP a desbloquear: " IP
    if ! grep -qx "$IP" "$ARCHIVO_BLOQUEADAS"; then
        echo "La IP '$IP' no esta en la lista de bloqueadas."
        return
    fi
    firewall-cmd --permanent --remove-rich-rule="rule family='ipv4' source address='$IP' drop" > /dev/null
    firewall-cmd --reload > /dev/null
    iptables -D DOCKER-USER -s "$IP" -j DROP 2>/dev/null
    grep -vx "$IP" "$ARCHIVO_BLOQUEADAS" > "$ARCHIVO_BLOQUEADAS.tmp"
    mv "$ARCHIVO_BLOQUEADAS.tmp" "$ARCHIVO_BLOQUEADAS"
    echo "IP $IP desbloqueada."
}

reaplicar_bloqueos() {
    while read -r IP; do
        [ -n "$IP" ] && aplicar_bloqueo "$IP"
    done < "$ARCHIVO_BLOQUEADAS"
}

configurar_fail2ban() {
    if ! esta_instalado fail2ban-client; then
        echo "fail2ban no esta instalado (opcion 2)."
        return
    fi
    # 5 intentos fallidos en 10 minutos = 1 hora bloqueado
    cat > /etc/fail2ban/jail.d/tornea-sshd.local <<'EOF'
[sshd]
enabled  = true
backend  = systemd
maxretry = 5
findtime = 10m
bantime  = 1h
EOF
    systemctl enable --now fail2ban
    systemctl restart fail2ban
    sleep 2
    fail2ban-client status sshd
}

endurecer_ssh() {
    echo "Se prohibe entrar por SSH como root y se limita a 3 intentos por conexion."
    echo "Antes, comprobar que se puede entrar como terobyte y usar sudo."
    confirmar "Continuar?" || return
    # En sshd_config.d gana el primer archivo que define cada opción (por orden
    # alfabético). El instalador de AlmaLinux puede dejar un
    # 25-permitrootlogin.conf con "yes", por eso este empieza con 00
    cat > /etc/ssh/sshd_config.d/00-tornea.conf <<'EOF'
# Política de seguridad de Tornea
PermitRootLogin no
MaxAuthTries 3
LoginGraceTime 30
EOF
    # Si la configuración tiene un error, no se reinicia (se perdería el acceso)
    if sshd -t; then
        systemctl restart sshd
        echo "SSH actualizado."
        sshd -T | grep -Ei '^(permitrootlogin|maxauthtries|logingracetime) '
        if ! sshd -T | grep -qi '^permitrootlogin no'; then
            echo "ALERTA: root todavia puede entrar, revisar /etc/ssh/sshd_config"
        fi
    else
        rm -f /etc/ssh/sshd_config.d/00-tornea.conf
        echo "Error en la configuracion de SSH: no se aplico ningun cambio."
    fi
}

escaneo_antivirus() {
    if ! esta_instalado clamscan; then
        echo "ClamAV no esta instalado (opcion 2)." | tee -a "$LOG_ANTIVIRUS"
        return 1
    fi
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ====== ESCANEO ANTIVIRUS ======" >> "$LOG_ANTIVIRUS"
    freshclam --quiet
    # -r recorre subcarpetas, -i muestra solo los archivos infectados
    # clamscan devuelve 1 si encontró algo
    SALIDA=$(clamscan -r -i $CARPETAS_ESCANEO 2>&1)
    RESULTADO=$?
    echo "$SALIDA" | tee -a "$LOG_ANTIVIRUS"
    if [ "$RESULTADO" -eq 1 ]; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] ALERTA: se encontraron archivos infectados" | tee -a "$LOG_ANTIVIRUS"
    fi
    return $RESULTADO
}

actualizaciones_seguridad() {
    echo "== ACTUALIZACIONES DE SEGURIDAD PENDIENTES =="
    dnf -q updateinfo list --security
    confirmar "Instalarlas ahora?" || return
    dnf upgrade -y --security
}

case "$1" in
    --escaneo) escaneo_antivirus; exit $? ;;
    --reaplicar) reaplicar_bloqueos; exit 0 ;;
esac

while true; do
    echo ""
    echo "=== Seguridad del Servidor ==="
    echo "1. Ver estado de seguridad"
    echo "2. Instalar herramientas (firewalld, fail2ban, ClamAV)"
    echo "3. Configurar firewall (solo ssh, http y https)"
    echo "4. Ver reglas de iptables"
    echo "5. Configurar fail2ban para SSH"
    echo "6. Bloquear una IP"
    echo "7. Desbloquear una IP"
    echo "8. Endurecer SSH (sin login de root)"
    echo "9. Escanear con el antivirus"
    echo "10. Actualizaciones de seguridad"
    echo "0. Salir"
    # Si se termina la entrada (Ctrl+D), se sale en vez de repetir el menú
    read -r -p "Opcion: " OPCION || exit 0
    echo ""
    case $OPCION in
        1) estado_seguridad ;;
        2) instalar_herramientas ;;
        3) configurar_firewall ;;
        4) ver_reglas_iptables ;;
        5) configurar_fail2ban ;;
        6) bloquear_ip ;;
        7) desbloquear_ip ;;
        8) endurecer_ssh ;;
        9) escaneo_antivirus ;;
        10) actualizaciones_seguridad ;;
        0) exit 0 ;;
        *) echo "Opcion Invalida" ;;
    esac
done
