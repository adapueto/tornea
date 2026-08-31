-- =============================================
-- Tornea
-- =============================================

CREATE DATABASE IF NOT EXISTS tornea CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tornea;

-- =============================================
-- Usuarios y acceso
-- =============================================

CREATE TABLE roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  apellido VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  fecha_nac DATE,
  perfil_publico TINYINT(1) DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE usuario_roles (
  usuario_id INT NOT NULL,
  rol_id INT NOT NULL,
  PRIMARY KEY (usuario_id, rol_id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
  FOREIGN KEY (rol_id) REFERENCES roles(id) ON DELETE CASCADE
);

-- =============================================
-- Torneos
-- =============================================

CREATE TABLE torneos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  descripcion TEXT,
  deporte VARCHAR(100),
  tipo ENUM('liga','eliminacion','suizo') NOT NULL,
  fecha_inicio DATE,
  fecha_fin DATE,
  estado ENUM('borrador','publicado','en_curso','finalizado') DEFAULT 'borrador',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE torneo_organizadores (
  torneo_id INT NOT NULL,
  usuario_id INT NOT NULL,
  PRIMARY KEY (torneo_id, usuario_id),
  FOREIGN KEY (torneo_id) REFERENCES torneos(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- =============================================
-- Equipos
-- =============================================

CREATE TABLE equipos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  torneo_id INT NOT NULL,
  lider_id INT NOT NULL,
  FOREIGN KEY (torneo_id) REFERENCES torneos(id) ON DELETE CASCADE,
  FOREIGN KEY (lider_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE equipo_miembros (
  equipo_id INT NOT NULL,
  usuario_id INT NOT NULL,
  PRIMARY KEY (equipo_id, usuario_id),
  FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE invitaciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  equipo_id INT NOT NULL,
  usuario_invitado_id INT NOT NULL,
  estado ENUM('pendiente','aceptada','rechazada') DEFAULT 'pendiente',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_invitado_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

-- =============================================
-- Participantes
-- =============================================

CREATE TABLE participantes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  torneo_id INT NOT NULL,
  usuario_id INT,
  equipo_id INT,
  tipo ENUM('individual','equipo') NOT NULL,
  estado ENUM('pendiente','aprobado','rechazado') DEFAULT 'pendiente',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (torneo_id) REFERENCES torneos(id) ON DELETE CASCADE,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL,
  FOREIGN KEY (equipo_id) REFERENCES equipos(id) ON DELETE SET NULL
);

-- =============================================
-- Competencia
-- =============================================

CREATE TABLE rondas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  torneo_id INT NOT NULL,
  numero INT NOT NULL,
  estado ENUM('pendiente','en_curso','finalizada') DEFAULT 'pendiente',
  FOREIGN KEY (torneo_id) REFERENCES torneos(id) ON DELETE CASCADE
);

CREATE TABLE enfrentamientos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ronda_id INT NOT NULL,
  participante_local_id INT,
  participante_visitante_id INT,
  estado ENUM('pendiente','en_curso','finalizado') DEFAULT 'pendiente',
  FOREIGN KEY (ronda_id) REFERENCES rondas(id) ON DELETE CASCADE,
  FOREIGN KEY (participante_local_id) REFERENCES participantes(id) ON DELETE SET NULL,
  FOREIGN KEY (participante_visitante_id) REFERENCES participantes(id) ON DELETE SET NULL
);

CREATE TABLE resultados (
  id INT AUTO_INCREMENT PRIMARY KEY,
  enfrentamiento_id INT NOT NULL UNIQUE,
  score_local INT DEFAULT 0,
  score_visitante INT DEFAULT 0,
  registrado_por INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (enfrentamiento_id) REFERENCES enfrentamientos(id) ON DELETE CASCADE,
  FOREIGN KEY (registrado_por) REFERENCES usuarios(id) ON DELETE SET NULL
);

CREATE TABLE tabla_posiciones (
  id INT AUTO_INCREMENT PRIMARY KEY,
  torneo_id INT NOT NULL,
  participante_id INT NOT NULL,
  pj INT DEFAULT 0,
  pg INT DEFAULT 0,
  pp INT DEFAULT 0,
  puntos INT DEFAULT 0,
  FOREIGN KEY (torneo_id) REFERENCES torneos(id) ON DELETE CASCADE,
  FOREIGN KEY (participante_id) REFERENCES participantes(id) ON DELETE CASCADE
);

-- =============================================
-- Auditoría
-- =============================================

CREATE TABLE auditoria (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT,
  accion VARCHAR(50) NOT NULL,
  tabla_afectada VARCHAR(100) NOT NULL,
  registro_id INT,
  fecha TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE SET NULL
);

-- =============================================
-- Datos iniciales
-- =============================================

INSERT INTO roles (nombre) VALUES ('admin'), ('organizador'), ('participante');