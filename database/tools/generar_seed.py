"""
Genera database/seed.sql con datos de prueba para Tornea.

Uso (desde la raíz del proyecto):
    python database/tools/generar_seed.py

Es determinístico (semilla fija): correrlo dos veces da el mismo seed.sql.
No necesita librerías externas. Todas las cuentas tienen la contraseña
"tornea123" (el hash bcrypt de abajo es compatible con password_verify de PHP).

Reglas que respeta:
- IDs explícitos en orden, así las FK siempre apuntan a filas existentes.
- Un solo rol por usuario (el login actual hace JOIN con usuario_roles).
- Deportes de equipo -> participantes tipo 'equipo'; el resto -> 'individual'.
- Los equipos son permanentes: se arman una vez (líder + invitaciones aceptadas)
  y se inscriben en varios torneos, sin que un jugador quede en dos equipos
  del mismo torneo.
- Rondas según el tipo, armadas igual que en app/models/ronda.php: liga = todos
  contra todos, eliminación = llaves donde avanza el ganador, suizo =
  emparejamiento como la tabla sin repetir rival. Con cantidades incómodas hay
  "libres" (enfrentamientos sin visitante), como en la app.
- Solo los torneos en curso o finalizados tienen rondas y resultados, y la
  tabla de posiciones se calcula a partir de esos resultados.
- Los torneos 61 en adelante son casos de borde armados a propósito (ver
  CASOS_BORDE): liga impar, eliminación de 6 y de 5, suizo impar, publicado
  vencido sin el mínimo y desempate por diferencia de goles.
- Las fechas se arman con HOY fijo, y al final del seed.sql se corren al día
  en que se carga la base.
"""

import random
from datetime import date, datetime, timedelta
from pathlib import Path

random.seed(2026)

PASSWORD_HASH = "$2y$10$YsbA0N6WzeoXDblhXfk65.yjO2kz3D4nYno7.pi/p0ji9H2L4ncJC"  # tornea123
SALIDA = Path(__file__).resolve().parent.parent / "seed.sql"

ROL_ADMIN, ROL_ORGANIZADOR, ROL_PARTICIPANTE = 1, 2, 3
CANT_USUARIOS = 150
CANT_ORGANIZADORES = 12  # usuarios 2..13
CANT_TORNEOS = 60
# Los mismos que vienen en la configuración de tornea.sql
PUNTOS_VICTORIA, PUNTOS_EMPATE = 3, 1

NOMBRES = [
    "Sofía", "Mateo", "Valentina", "Santiago", "Martina", "Benjamín", "Lucía",
    "Joaquín", "Camila", "Tomás", "Emilia", "Agustín", "Florencia", "Facundo",
    "Micaela", "Nicolás", "Julieta", "Bruno", "Paula", "Diego", "Carolina",
    "Federico", "Agustina", "Gonzalo", "Victoria", "Matías", "Natalia", "Lautaro",
    "Romina", "Ignacio", "Abril", "Franco", "Milagros", "Rodrigo", "Josefina",
]
APELLIDOS = [
    "González", "Rodríguez", "Fernández", "López", "Martínez", "Pérez", "García",
    "Sánchez", "Romero", "Díaz", "Álvarez", "Torres", "Ruiz", "Suárez", "Silva",
    "Pereira", "Acosta", "Benítez", "Méndez", "Castro", "Olivera", "Núñez",
    "Cabrera", "Ferreira", "Sosa", "Rosa", "Viera", "Techera", "Correa", "Ramos",
]

# deporte -> (es_de_equipo, prefijos de nombre de torneo)
DEPORTES = {
    "Fútbol 5": (True, ["Copa", "Liga", "Torneo Apertura", "Torneo Clausura"]),
    "Fútbol 11": (True, ["Copa", "Liga Amateur", "Campeonato"]),
    "Básquet": (True, ["Liga", "Copa", "Torneo 3x3"]),
    "Vóley": (True, ["Copa", "Torneo", "Liga Mixta"]),
    "Handball": (True, ["Copa", "Torneo"]),
    "Ajedrez": (False, ["Abierto", "Torneo Blitz", "Rápido"]),
    "Tenis": (False, ["Abierto", "Copa", "Masters"]),
    "Pádel": (False, ["Torneo", "Copa", "Americano"]),
    "Ping Pong": (False, ["Torneo", "Copa"]),
    "eSports - FIFA": (False, ["Copa", "Liga Online", "Torneo"]),
    "eSports - Rocket League": (True, ["Copa", "Liga Online"]),
    "eSports - League of Legends": (True, ["Copa", "Torneo"]),
}
LUGARES = [
    "Barrio Sur", "Pocitos", "Malvín", "Cordón", "Centro", "Buceo", "Prado",
    "La Blanqueada", "Parque Rodó", "Carrasco", "Interliceal", "UTU", "Primavera",
    "Invierno", "de la Costa", "del Oeste", "Universitario", "de Verano",
]
NOMBRES_EQUIPO = [
    "Los Halcones", "Rayo Azul", "Deportivo Esquina", "Los Pibes", "Atlético Barrio",
    "Real Cordón", "Furia Roja", "Leones del Sur", "Tiburones", "Los Cracks",
    "Unión Norte", "Sporting Malvín", "Defensores", "Estrella Fugaz", "La Banda",
    "Titanes", "Los Invencibles", "Racing Prado", "Fénix", "Panteras",
    "Club Amigos", "Los Galácticos", "Huracán", "Cóndores", "Los Lobos",
    "Juventud Unida", "Pumas", "Dragones", "Vikingos", "Spartans",
]

HOY = date(2026, 10, 5)
AHORA = datetime(2026, 10, 5, 12, 0)

# Casos de borde: torneos armados a propósito, después de los 60 al azar (ids 61 en adelante),
# para que se vean los libres, las llaves incompletas y los desempates.
# Se agregan al final para no cambiar el sorteo de los torneos 1 a 60 (mismos
# deportes, inscriptos y resultados; sí cambian sus fechas y el orden del suizo).
CASOS_BORDE = [
    # Liga impar: en cada fecha uno descansa
    {"tipo": "liga", "estado": "en_curso", "cant": 7, "deporte": "Ping Pong",
     "nombre": "Liga del Prado Ping Pong 2026"},
    # Eliminación con 6: dos pasan directo a semifinales
    {"tipo": "eliminacion", "estado": "finalizado", "cant": 6, "deporte": "Fútbol 5",
     "nombre": "Copa Malvín Fútbol 5 2026"},
    # Eliminación con 5: tres pasan directo
    {"tipo": "eliminacion", "estado": "en_curso", "cant": 5, "deporte": "Tenis",
     "nombre": "Abierto del Buceo Tenis 2026"},
    # Suizo impar: en cada ronda el último de la tabla que no tuvo libre, descansa y suma
    {"tipo": "suizo", "estado": "finalizado", "cant": 7, "deporte": "Ajedrez",
     "nombre": "Abierto de Primavera Ajedrez 2026"},
    {"tipo": "suizo", "estado": "en_curso", "cant": 9, "deporte": "eSports - FIFA",
     "nombre": "Torneo Cordón FIFA 2026"},
    # Publicado con la fecha vencida y sin el mínimo de aprobados: no arranca solo
    # hasta que el organizador apruebe a uno de los pendientes
    {"tipo": "liga", "estado": "publicado", "cant": 1, "pendientes": 2, "inicio": -3,
     "deporte": "Pádel", "nombre": "Torneo del Oeste Pádel 2026"},
    # Liga con empate en puntos en la punta: Valentina y Bruno terminan con 5 puntos y
    # gana Valentina por diferencia de goles, aunque por nombre iría segunda.
    # Resultados fijos, en el orden del fixture (Valentina, Bruno, Tomás, Josefina).
    {"tipo": "liga", "estado": "finalizado", "cant": 4, "deporte": "eSports - FIFA",
     "nombre": "Liga Interliceal FIFA 2026", "usuarios": [17, 15, 45, 58], "scores": [
         (4, 0), (1, 0),   # Fecha 1: Valentina 4-0 Josefina, Bruno 1-0 Tomás
         (1, 1), (0, 0),   # Fecha 2: Tomás 1-1 Valentina, Bruno 0-0 Josefina
         (1, 1), (2, 1),   # Fecha 3: Valentina 1-1 Bruno, Tomás 2-1 Josefina
     ]},
]


# ---------------------------------------------------------------------------
# Utilidades SQL
# ---------------------------------------------------------------------------

def sql_valor(v):
    if v is None:
        return "NULL"
    if isinstance(v, bool):
        return "1" if v else "0"
    if isinstance(v, (int, float)):
        return str(v)
    if isinstance(v, datetime):
        return "'" + v.strftime("%Y-%m-%d %H:%M:%S") + "'"
    if isinstance(v, date):
        return "'" + v.isoformat() + "'"
    return "'" + str(v).replace("\\", "\\\\").replace("'", "''") + "'"


def insert(tabla, columnas, filas, por_bloque=100):
    if not filas:
        return ""
    partes = []
    for i in range(0, len(filas), por_bloque):
        bloque = filas[i:i + por_bloque]
        valores = ",\n".join("(" + ", ".join(sql_valor(v) for v in fila) + ")" for fila in bloque)
        partes.append(f"INSERT INTO {tabla} ({', '.join(columnas)}) VALUES\n{valores};\n")
    return "\n".join(partes)


def momento(d, hora_min=9, hora_max=21):
    return datetime(d.year, d.month, d.day, random.randint(hora_min, hora_max), random.choice([0, 15, 30, 45]))


def no_futuro(dt):
    """Las acciones registradas (auditoría, inscripciones) no pueden ser posteriores a hoy."""
    return min(dt, AHORA - timedelta(minutes=random.randint(5, 600)))


# ---------------------------------------------------------------------------
# Usuarios y roles
# ---------------------------------------------------------------------------

usuarios = []        # (id, nombre, apellido, email, password, fecha_nac, perfil_publico, created_at)
usuario_roles = []   # (usuario_id, rol_id)
auditoria = []       # (usuario_id, accion, tabla_afectada, registro_id, fecha)

emails_usados = set()


def normalizar(txt):
    tabla = str.maketrans("áéíóúñÁÉÍÓÚÑ", "aeiounAEIOUN")
    return txt.translate(tabla).lower().replace(" ", "")


for uid in range(1, CANT_USUARIOS + 1):
    if uid == 1:
        nombre, apellido, email = "Admin", "Tornea", "admin@tornea.test"
        rol = ROL_ADMIN
    else:
        nombre, apellido = random.choice(NOMBRES), random.choice(APELLIDOS)
        base = f"{normalizar(nombre)}.{normalizar(apellido)}"
        email = f"{base}@tornea.test"
        n = 2
        while email in emails_usados:
            email = f"{base}{n}@tornea.test"
            n += 1
        rol = ROL_ORGANIZADOR if uid <= CANT_ORGANIZADORES + 1 else ROL_PARTICIPANTE
    emails_usados.add(email)
    fecha_nac = date(random.randint(1975, 2008), random.randint(1, 12), random.randint(1, 28))
    creado = momento(date(2025, 6, 1) + timedelta(days=random.randint(0, 200)))
    usuarios.append((uid, nombre, apellido, email, PASSWORD_HASH, fecha_nac, random.random() < 0.85, creado))
    usuario_roles.append((uid, rol))
    auditoria.append((uid, "INSERT", "usuarios", uid, creado))

ORGANIZADORES = list(range(2, CANT_ORGANIZADORES + 2))
PARTICIPANTES = list(range(CANT_ORGANIZADORES + 2, CANT_USUARIOS + 1))
creado_usuario = {u[0]: u[7] for u in usuarios}


# ---------------------------------------------------------------------------
# Torneos
# ---------------------------------------------------------------------------

torneos = []               # (id, nombre, descripcion, deporte, tipo, modalidad, fecha_inicio, fecha_fin, estado, created_at)
torneo_organizadores = []  # (torneo_id, usuario_id)
equipos = []               # (id, nombre, lider_id, created_at)
equipo_miembros = []       # (equipo_id, usuario_id)
invitaciones = []          # (id, equipo_id, usuario_invitado_id, estado, created_at)
participantes = []         # (id, torneo_id, usuario_id, equipo_id, tipo, estado, created_at)
rondas = []                # (id, torneo_id, numero, estado)
enfrentamientos = []       # (id, ronda_id, local_id, visitante_id, estado)
resultados = []            # (id, enfrentamiento_id, score_local, score_visitante, registrado_por, created_at)
tabla_posiciones = []      # (id, torneo_id, participante_id, pj, pg, pp, puntos, gf, gc)

TIPOS = ["liga", "eliminacion", "suizo"]
# Reparto de estados: la mayoría con competencia para tener rondas/resultados
ESTADOS = (["finalizado"] * 22 + ["en_curso"] * 20 + ["publicado"] * 12 + ["borrador"] * 6)
random.shuffle(ESTADOS)

nombres_torneo_usados = set()


def score_partido(deporte, permite_empate):
    if deporte.startswith("Básquet"):
        a, b = random.randint(45, 95), random.randint(45, 95)
    elif deporte in ("Vóley", "Tenis", "Pádel"):
        a, b = random.choice([(2, 0), (2, 1), (0, 2), (1, 2)]) if deporte != "Vóley" else random.choice([(3, 0), (3, 1), (3, 2), (0, 3), (1, 3), (2, 3)])
    elif deporte == "Ajedrez":
        a, b = random.choice([(1, 0), (0, 1), (1, 1)])
    elif deporte == "Handball":
        a, b = random.randint(15, 35), random.randint(15, 35)
    elif deporte == "Ping Pong":
        a, b = random.choice([(3, 0), (3, 1), (3, 2), (0, 3), (1, 3), (2, 3)])
    elif "League of Legends" in deporte:
        a, b = random.choice([(1, 0), (0, 1)])
    else:  # fútbol y eSports de goles
        a, b = random.randint(0, 5), random.randint(0, 5)
    if not permite_empate:
        while a == b:
            if random.random() < 0.5:
                a += 1
            else:
                b += 1
    return a, b


def circulo(ids):
    """Fixture todos contra todos (método del círculo), igual que fixtureLiga() de la app.
    Con cantidad impar, al que le toca el None descansa: queda como (id, None)."""
    ids = list(ids)
    if len(ids) % 2:
        ids.append(None)
    n = len(ids)
    fechas = []
    for r in range(n - 1):
        partidos = []
        for i in range(n // 2):
            a, b = ids[i], ids[n - 1 - i]
            if a is None or b is None:
                partidos.append((a or b, None))
            else:
                partidos.append((a, b) if r % 2 == 0 else (b, a))
        fechas.append(partidos)
        ids = [ids[0]] + [ids[-1]] + ids[1:-1]
    return fechas


def primera_ronda_eliminacion(ids):
    """Como primeraRondaEliminacion() de la app: se completa hasta la potencia de 2
    siguiente con pases libres, repartidos a lo largo de la llave."""
    ids = list(ids)
    tamano = 1
    while tamano < len(ids):
        tamano *= 2
    cantidad_cruces = tamano // 2
    libres = tamano - len(ids)
    cruces = [None] * cantidad_cruces
    for k in range(libres):
        cruces[k * cantidad_cruces // libres] = (ids.pop(0), None)
    for i in range(cantidad_cruces):
        if cruces[i] is None:
            cruces[i] = (ids.pop(0), ids.pop(0))
    return cruces


def sin_tildes(txt):
    """Para ordenar por nombre como MySQL: sin tildes y sin mayúsculas."""
    return txt.translate(str.maketrans("áéíóúüñÁÉÍÓÚÜÑ", "aeiouunAEIOUUN")).lower()


def emparejar_suizo(ids, stats, nombres, jugados, primera):
    """Como emparejarSuizo() de la app. Se ordena como la tabla (puntos, diferencia,
    goles a favor, ganados y nombre) y cada uno enfrenta al siguiente que no fue su rival.
    Con cantidad impar, el libre va al de más abajo que todavía no lo tuvo.
    jugados[p] tiene los rivales de p; None quiere decir que ya tuvo libre."""
    def lugar(p):
        s = stats[p]
        return (-s["puntos"], -(s["gf"] - s["gc"]), -s["gf"], -s["pg"], sin_tildes(nombres[p]), p)

    # La primera ronda va en el orden de inscripción, que ya es al azar
    orden = list(ids) if primera else sorted(ids, key=lugar)

    def resolver(restantes):
        if not restantes:
            return []
        a = restantes[0]
        for b in restantes[1:]:
            if b not in jugados[a]:
                resto = [x for x in restantes[1:] if x != b]
                sol = resolver(resto)
                if sol is not None:
                    return [(a, b)] + sol
        return None

    if len(orden) % 2 == 0:
        cruces = resolver(orden)
    else:
        cruces = None
        # El libre se busca desde el último de la tabla hacia arriba
        for candidato in reversed(orden):
            if None in jugados[candidato]:
                continue  # ya tuvo libre
            cruces = resolver([p for p in orden if p != candidato])
            if cruces is not None:
                cruces.append((candidato, None))
                break
    # La app no repite rivales: si no hay forma, el seed tampoco tiene que inventarla
    assert cruces is not None, "el suizo no se puede emparejar sin repetir rival"
    return cruces


eq_id = inv_id = part_id = ronda_id = enf_id = res_id = tabla_id = 0

# ---------------------------------------------------------------------------
# Equipos permanentes: se arman en enero de 2026 y después se inscriben en
# varios torneos. Cada jugador está en tres equipos como mucho.
# ---------------------------------------------------------------------------

CANT_EQUIPOS = 60
# 30 nombres base y, para los clubes que tienen un segundo equipo, "... B"
nombres_pool = NOMBRES_EQUIPO + [f"{n} B" for n in NOMBRES_EQUIPO]
equipos_en = {u: 0 for u in PARTICIPANTES}   # en cuántos equipos está cada jugador
miembros_de = {}                              # equipo_id -> set de usuarios
lider_de = {}

for i in range(CANT_EQUIPOS):
    eq_id += 1
    libres = [u for u in PARTICIPANTES if equipos_en[u] < 3]
    random.shuffle(libres)
    lider = libres.pop()
    creado_eq = momento(date(2026, 1, 2) + timedelta(days=random.randint(0, 16)))
    equipos.append((eq_id, nombres_pool[i], lider, creado_eq))
    equipo_miembros.append((eq_id, lider))
    miembros_de[eq_id] = {lider}
    lider_de[eq_id] = lider
    equipos_en[lider] += 1
    auditoria.append((lider, "INSERT", "equipos", eq_id, creado_eq))

    # Invitaciones aceptadas (los integrantes) y algunas pendientes o rechazadas
    for estado_inv in ["aceptada"] * random.randint(3, 6) + random.choice([[], ["pendiente"], ["rechazada"], ["pendiente", "rechazada"]]):
        if not libres:
            break
        invitado = libres.pop()
        inv_id += 1
        cuando = creado_eq + timedelta(hours=random.randint(1, 72))
        invitaciones.append((inv_id, eq_id, invitado, estado_inv, cuando))
        auditoria.append((lider, "INSERT", "invitaciones", inv_id, cuando))
        if estado_inv == "aceptada":
            equipo_miembros.append((eq_id, invitado))
            miembros_de[eq_id].add(invitado)
            equipos_en[invitado] += 1
        if estado_inv != "pendiente":
            auditoria.append((invitado, "UPDATE", "invitaciones", inv_id, cuando + timedelta(hours=random.randint(1, 20))))

orden_equipos = list(miembros_de)

# Nombre de cada participante, como lo muestra la app (para ordenar por nombre)
nombre_usuario = {u[0]: f"{u[1]} {u[2]}" for u in usuarios}
nombre_equipo = {e[0]: e[1] for e in equipos}
nombre_participante = {}

# Los ids fijos de los casos de borde tienen que seguir siendo esas personas
# (si cambia el sorteo de usuarios, hay que elegir otros)
assert [nombre_usuario[u] for u in (17, 15, 45, 58)] == \
    ["Valentina Sosa", "Bruno Correa", "Tomás Sánchez", "Josefina Martínez"], "cambiaron los usuarios del caso 67"

for tid in range(1, CANT_TORNEOS + len(CASOS_BORDE) + 1):
    # Del 61 en adelante, los casos de borde (vacío para los torneos al azar)
    caso = CASOS_BORDE[tid - CANT_TORNEOS - 1] if tid > CANT_TORNEOS else {}

    deporte = caso.get("deporte") or random.choice(list(DEPORTES))
    de_equipo, prefijos = DEPORTES[deporte]
    tipo = caso.get("tipo") or TIPOS[(tid - 1) % 3]
    estado = caso.get("estado") or ESTADOS[tid - 1]

    # "Liga ..." solo para torneos tipo liga, para que el nombre no confunda
    prefijos = [x for x in prefijos if tipo == "liga" or not x.startswith("Liga")] or ["Torneo"]
    if caso:
        nombre = caso["nombre"]
        assert nombre not in nombres_torneo_usados, "nombre de caso de borde repetido"
    else:
        nombre = f"{random.choice(prefijos)} {random.choice(LUGARES)} {deporte.replace('eSports - ', '')} 2026"
        while nombre in nombres_torneo_usados:
            nombre = f"{random.choice(prefijos)} {random.choice(LUGARES)} {deporte.replace('eSports - ', '')} 2026"
    nombres_torneo_usados.add(nombre)

    if estado == "finalizado":
        inicio = date(2026, 3, 1) + timedelta(days=random.randint(0, 120))
    elif estado == "en_curso":
        inicio = HOY - timedelta(days=random.randint(10, 45))
    elif estado == "publicado":
        inicio = HOY + timedelta(days=random.randint(10, 60))
    else:
        inicio = HOY + timedelta(days=random.randint(30, 120)) if random.random() < 0.6 else None
    if "inicio" in caso:
        inicio = HOY + timedelta(days=caso["inicio"])
    fin = inicio + timedelta(days=random.randint(14, 70)) if inicio else None
    creado = no_futuro(momento((inicio or HOY) - timedelta(days=random.randint(20, 40))))

    formato = {"liga": "todos contra todos", "eliminacion": "eliminación directa", "suizo": "sistema suizo"}[tipo]
    modalidad = "por equipos" if de_equipo else "individual"
    descripcion = f"Torneo de {deporte} {modalidad} con formato {formato}. Abierto a todos los niveles."

    organizador = random.choice(ORGANIZADORES)
    torneos.append((tid, nombre, descripcion, deporte, tipo, "equipo" if de_equipo else "individual", inicio, fin, estado, creado))
    torneo_organizadores.append((tid, organizador))
    auditoria.append((organizador, "INSERT", "torneos", tid, creado))
    if random.random() < 0.3:
        co = random.choice([o for o in ORGANIZADORES if o != organizador])
        torneo_organizadores.append((tid, co))
        auditoria.append((organizador, "INSERT", "torneo_organizadores", tid, creado + timedelta(hours=1)))

    if estado == "borrador":
        continue  # todavía no abre inscripciones

    # Al azar: eliminación de 8, suizo de 8 o 10 y liga de 6 u 8.
    # Las cantidades incómodas (impares, llaves incompletas) están en los casos de borde.
    cant = {"liga": random.choice([6, 8]), "eliminacion": 8, "suizo": random.choice([8, 10])}[tipo]
    # En torneos publicados la inscripción sigue abierta: menos aprobados y algunos pendientes
    extra_pendientes = random.randint(1, 3) if estado == "publicado" else 0
    rechazados = random.randint(0, 2)
    if caso:
        cant, extra_pendientes, rechazados = caso["cant"], caso.get("pendientes", 0), 0

    inscripcion = creado + timedelta(days=2)
    candidatos = random.sample(PARTICIPANTES, k=len(PARTICIPANTES))
    if caso.get("usuarios"):
        # Se inscriben en este orden (pop saca del final)
        candidatos = list(reversed(caso["usuarios"]))
    aprobados = []

    # Torneo por equipos: equipos del pool sin jugadores en común entre sí
    if de_equipo:
        random.shuffle(orden_equipos)
        en_torneo, usados = [], set()
        for e in orden_equipos:
            if not (miembros_de[e] & usados):
                en_torneo.append(e)
                usados |= miembros_de[e]

    def nuevo_participante(estado_insc, usuario=None, equipo=None):
        global part_id
        part_id += 1
        cuando = no_futuro(inscripcion + timedelta(hours=random.randint(1, 240)))
        participantes.append((part_id, tid, usuario, equipo, "equipo" if equipo else "individual", estado_insc, cuando))
        nombre_participante[part_id] = nombre_equipo[equipo] if equipo else nombre_usuario[usuario]
        quien = usuario if usuario else lider_de[equipo]
        auditoria.append((quien, "INSERT", "participantes", part_id, cuando))
        if estado_insc != "pendiente":
            auditoria.append((organizador, "UPDATE", "participantes", part_id, no_futuro(cuando + timedelta(hours=random.randint(2, 48)))))
        return part_id

    estados_insc = ["aprobado"] * cant + ["pendiente"] * extra_pendientes + ["rechazado"] * rechazados
    if estado == "publicado" and not caso:
        # Inscripción abierta: parte de los aprobados todavía no fue revisada
        for i in range(random.randint(1, cant // 2)):
            estados_insc[i] = "pendiente"

    if de_equipo:
        # Si no alcanzan los equipos sin jugadores en común, se inscriben menos
        # (primero se recortan pendientes y rechazados, que van al final)
        assert len(en_torneo) >= cant, "no alcanzan los equipos para un torneo"
        estados_insc = estados_insc[:len(en_torneo)]

    for estado_insc in estados_insc:
        if de_equipo:
            pid = nuevo_participante(estado_insc, equipo=en_torneo.pop())
        else:
            pid = nuevo_participante(estado_insc, usuario=candidatos.pop())
        if estado_insc == "aprobado":
            aprobados.append(pid)

    if estado == "publicado":
        continue  # sin rondas todavía

    # ------------------------------------------------------------------
    # Rondas, enfrentamientos y resultados
    # ------------------------------------------------------------------
    stats = {p: {"pj": 0, "pg": 0, "pp": 0, "puntos": 0, "gf": 0, "gc": 0} for p in aprobados}
    permite_empate = tipo != "eliminacion"

    if tipo == "liga":
        fixture = circulo(aprobados)
        cant_rondas = len(fixture)
    else:
        # Eliminación: rondas hasta la final. Suizo: las que recomienda la app.
        # En los dos casos es log2 de la cantidad, redondeado para arriba (8 -> 3, 6 -> 3, 9 -> 4)
        cant_rondas = (len(aprobados) - 1).bit_length()

    # Una ronda por semana. En curso: las semanas ya pasadas están jugadas y la actual va a medias
    if estado == "finalizado":
        rondas_jugadas = cant_rondas
    else:
        dias = (HOY - inicio).days
        rondas_jugadas = max(1, min(dias // 7, cant_rondas - 1))
        # El inicio se acomoda a las rondas jugadas, así la ronda en curso es la de esta semana
        inicio = HOY - timedelta(days=7 * rondas_jugadas + dias % 7)

    fecha_ronda = inicio
    vivos = list(aprobados)
    random.shuffle(vivos)
    jugados = {p: set() for p in aprobados}
    scores_fijos = list(caso.get("scores", []))

    for numero in range(1, cant_rondas + 1):
        if tipo == "liga":
            partidos = fixture[numero - 1]
        elif tipo == "eliminacion":
            if numero > rondas_jugadas + 1:
                break  # la siguiente ronda de la llave todavía no se generó
            if numero == 1:
                partidos = primera_ronda_eliminacion(vivos)
            else:
                # Los ganadores se cruzan en el orden de la llave
                partidos = [(vivos[i], vivos[i + 1]) for i in range(0, len(vivos), 2)]
        else:
            if numero > rondas_jugadas + 1:
                break
            partidos = emparejar_suizo(aprobados, stats, nombre_participante, jugados, numero == 1)

        if numero <= rondas_jugadas:
            estado_ronda = "finalizada"
        elif numero == rondas_jugadas + 1:
            estado_ronda = "en_curso"
        else:
            estado_ronda = "pendiente"

        ronda_id += 1
        rondas.append((ronda_id, tid, numero, estado_ronda))
        auditoria.append((organizador, "INSERT", "rondas", ronda_id, no_futuro(momento(fecha_ronda - timedelta(days=1)))))

        ganadores = []
        reales = [c for c in partidos if c[1] is not None]
        for loc, vis in partidos:
            enf_id += 1
            if vis is None:
                # Libre: no se juega y queda finalizado desde el principio, como en la app
                enfrentamientos.append((enf_id, ronda_id, loc, None, "finalizado"))
                jugados[loc].add(None)
                if tipo == "eliminacion":
                    ganadores.append(loc)  # pasa directo
                elif tipo == "suizo":
                    # En el suizo el libre vale una victoria (sin goles)
                    stats[loc]["pj"] += 1; stats[loc]["pg"] += 1; stats[loc]["puntos"] += PUNTOS_VICTORIA
                continue  # en la liga solo descansa

            i = reales.index((loc, vis))
            jugar = estado_ronda == "finalizada" or (estado_ronda == "en_curso" and i < len(reales) // 2)
            if estado_ronda == "en_curso" and not jugar:
                estado_enf = "en_curso" if i == len(reales) // 2 else "pendiente"
            else:
                estado_enf = "finalizado" if jugar else "pendiente"
            enfrentamientos.append((enf_id, ronda_id, loc, vis, estado_enf))
            jugados[loc].add(vis)
            jugados[vis].add(loc)

            if not jugar:
                continue
            a, b = scores_fijos.pop(0) if scores_fijos else score_partido(deporte, permite_empate)
            res_id += 1
            cuando = no_futuro(momento(fecha_ronda, 18, 22))
            resultados.append((res_id, enf_id, a, b, organizador, cuando))
            auditoria.append((organizador, "INSERT", "resultados", res_id, cuando))
            for p in (loc, vis):
                stats[p]["pj"] += 1
            # Goles a favor y en contra, para desempatar en la tabla
            stats[loc]["gf"] += a; stats[loc]["gc"] += b
            stats[vis]["gf"] += b; stats[vis]["gc"] += a
            if a > b:
                stats[loc]["pg"] += 1; stats[vis]["pp"] += 1; stats[loc]["puntos"] += PUNTOS_VICTORIA
                ganadores.append(loc)
            elif b > a:
                stats[vis]["pg"] += 1; stats[loc]["pp"] += 1; stats[vis]["puntos"] += PUNTOS_VICTORIA
                ganadores.append(vis)
            else:
                stats[loc]["puntos"] += PUNTOS_EMPATE; stats[vis]["puntos"] += PUNTOS_EMPATE

        if tipo == "eliminacion" and estado_ronda == "finalizada":
            vivos = ganadores
        fecha_ronda += timedelta(days=7)

    # Fechas acordes a las rondas: una por semana, y el calendario deja dos semanas
    # de margen después de la última (así un torneo en curso no se vence enseguida)
    t = torneos[-1]
    torneos[-1] = t[:6] + (inicio, inicio + timedelta(days=7 * (cant_rondas - 1) + 6 + 14)) + t[8:]

    if tipo in ("liga", "suizo"):
        for p in aprobados:
            tabla_id += 1
            s = stats[p]
            tabla_posiciones.append((tabla_id, tid, p, s["pj"], s["pg"], s["pp"], s["puntos"], s["gf"], s["gc"]))

auditoria.sort(key=lambda a: a[4])
auditoria = [(i + 1,) + a for i, a in enumerate(auditoria)]


# ---------------------------------------------------------------------------
# Salida
# ---------------------------------------------------------------------------

def contar(nombre, filas):
    return f"--   {nombre:<22} {len(filas):>5}"


resumen = "\n".join([
    contar("usuarios", usuarios), contar("usuario_roles", usuario_roles),
    contar("torneos", torneos), contar("torneo_organizadores", torneo_organizadores),
    contar("equipos", equipos), contar("equipo_miembros", equipo_miembros),
    contar("invitaciones", invitaciones), contar("participantes", participantes),
    contar("rondas", rondas), contar("enfrentamientos", enfrentamientos),
    contar("resultados", resultados), contar("tabla_posiciones", tabla_posiciones),
    contar("auditoria", auditoria),
])

sql = f"""-- =============================================
-- Tornea — datos de prueba
-- Generado por database/tools/generar_seed.py (no editar a mano)
-- Cargar solo sobre una base vacía, recién creada con tornea.sql
--
-- Contraseña de todas las cuentas: tornea123
--   admin@tornea.test        -> admin
--   usuarios 2 a {CANT_ORGANIZADORES + 1}          -> organizador
--   el resto                 -> participante
--
-- Cantidad de registros:
{resumen}
-- =============================================

SET NAMES utf8mb4;
USE tornea;

{insert("usuarios", ["id", "nombre", "apellido", "email", "password", "fecha_nac", "perfil_publico", "created_at"], usuarios)}
{insert("usuario_roles", ["usuario_id", "rol_id"], usuario_roles)}
{insert("torneos", ["id", "nombre", "descripcion", "deporte", "tipo", "modalidad", "fecha_inicio", "fecha_fin", "estado", "created_at"], torneos)}
{insert("torneo_organizadores", ["torneo_id", "usuario_id"], torneo_organizadores)}
{insert("equipos", ["id", "nombre", "lider_id", "created_at"], equipos)}
{insert("equipo_miembros", ["equipo_id", "usuario_id"], equipo_miembros)}
{insert("invitaciones", ["id", "equipo_id", "usuario_invitado_id", "estado", "created_at"], invitaciones)}
{insert("participantes", ["id", "torneo_id", "usuario_id", "equipo_id", "tipo", "estado", "created_at"], participantes)}
{insert("rondas", ["id", "torneo_id", "numero", "estado"], rondas)}
{insert("enfrentamientos", ["id", "ronda_id", "participante_local_id", "participante_visitante_id", "estado"], enfrentamientos)}
{insert("resultados", ["id", "enfrentamiento_id", "score_local", "score_visitante", "registrado_por", "created_at"], resultados)}
{insert("tabla_posiciones", ["id", "torneo_id", "participante_id", "pj", "pg", "pp", "puntos", "gf", "gc"], tabla_posiciones)}
{insert("auditoria", ["id", "usuario_id", "accion", "tabla_afectada", "registro_id", "fecha"], auditoria)}
-- Los datos están armados como si hoy fuera {HOY.isoformat()}. Se corren todas las fechas
-- para que "hoy" sea el día en que se carga la base: así los torneos en curso siguen
-- en curso y los publicados siguen por empezar, sin importar cuándo se cargue.
-- (La fecha de nacimiento no se toca.)
SET @dias = DATEDIFF(CURDATE(), '{HOY.isoformat()}');
UPDATE torneos SET fecha_inicio = fecha_inicio + INTERVAL @dias DAY,
                   fecha_fin = fecha_fin + INTERVAL @dias DAY;
-- Lo que ya pasó no puede quedar en el futuro (si se carga antes del mediodía)
UPDATE usuarios SET created_at = LEAST(created_at + INTERVAL @dias DAY, NOW());
UPDATE torneos SET created_at = LEAST(created_at + INTERVAL @dias DAY, NOW());
UPDATE equipos SET created_at = LEAST(created_at + INTERVAL @dias DAY, NOW());
UPDATE invitaciones SET created_at = LEAST(created_at + INTERVAL @dias DAY, NOW());
UPDATE participantes SET created_at = LEAST(created_at + INTERVAL @dias DAY, NOW());
UPDATE resultados SET created_at = LEAST(created_at + INTERVAL @dias DAY, NOW());
UPDATE auditoria SET fecha = LEAST(fecha + INTERVAL @dias DAY, NOW());
"""

SALIDA.write_text(sql, encoding="utf-8", newline="\n")
print(f"Escrito {SALIDA}")
print(resumen.replace("--   ", "  "))
