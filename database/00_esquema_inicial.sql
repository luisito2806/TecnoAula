-- TecnoAula — Esquema inicial (tablas base: usuarios y cursos)
-- Es el punto de partida de las migraciones. Orden de instalación en README.md.
-- Ejecutar una sola vez, en una base vacía:
--   mysql -u root -p -e "CREATE DATABASE TecnoAula CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
--   mysql -u root -p TecnoAula < database/00_esquema_inicial.sql

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    rol ENUM('superadmin','director','ponente','estudiante') NOT NULL DEFAULT 'estudiante',
    -- migracion_fase1_2.sql convierte estos estados a activo / inactivo
    estado ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'aprobado',
    creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuarios_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE cursos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT NULL,
    ponente_id INT NULL,
    -- migracion_fase3_4.sql lo cambia a borrador / publicado y agrega más columnas
    estado ENUM('borrador','publicado') NOT NULL DEFAULT 'borrador',
    KEY idx_cursos_ponente (ponente_id),
    CONSTRAINT fk_cursos_ponente FOREIGN KEY (ponente_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
