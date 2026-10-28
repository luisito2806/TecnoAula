-- TecnoAula — Migración Fases 1 y 2 (login, usuarios, roles y permisos)
-- Parte del esquema actual: usuarios(id, nombre, email, password, rol, estado, creado_en) y cursos.
-- Ejecutar una sola vez:  mysql -u root -p TecnoAula < database/migracion_fase1_2.sql

-- 1. Columnas nuevas en usuarios
ALTER TABLE usuarios
    ADD COLUMN avatar_url     VARCHAR(255) NULL AFTER password,
    ADD COLUMN cargo          VARCHAR(100) NULL AFTER avatar_url,
    ADD COLUMN actualizado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER creado_en,
    ADD COLUMN ultimo_acceso  TIMESTAMP NULL AFTER actualizado_en,
    ADD COLUMN eliminado_en   TIMESTAMP NULL AFTER ultimo_acceso,
    MODIFY estado ENUM('pendiente','aprobado','rechazado','activo','inactivo') NOT NULL DEFAULT 'activo';

-- 2. El estado pasa a ser activo / inactivo (aprobado => activo; pendiente y rechazado => inactivo)
UPDATE usuarios SET estado = IF(estado IN ('aprobado','activo'), 'activo', 'inactivo');

ALTER TABLE usuarios
    MODIFY estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
    ADD INDEX idx_usuarios_rol (rol),
    ADD INDEX idx_usuarios_creado (creado_en);

-- 3. Roles adicionales (el rol principal sigue en usuarios.rol)
CREATE TABLE usuario_roles (
    usuario_id INT NOT NULL,
    rol ENUM('superadmin','director','ponente','estudiante') NOT NULL,
    PRIMARY KEY (usuario_id, rol),
    CONSTRAINT fk_usuario_roles_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Permisos adicionales por usuario
CREATE TABLE usuario_permisos (
    usuario_id INT NOT NULL,
    permiso ENUM('crear_cursos','ver_reportes','gestionar_usuarios') NOT NULL,
    PRIMARY KEY (usuario_id, permiso),
    CONSTRAINT fk_usuario_permisos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Recuperación de contraseña (se guarda solo el hash del token)
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expira_en DATETIME NOT NULL,
    usado_en DATETIME NULL,
    creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_password_resets_token (token_hash),
    CONSTRAINT fk_password_resets_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Sesiones "Recordarme" (cookie de larga duración)
CREATE TABLE recordar_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    selector CHAR(24) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expira_en DATETIME NOT NULL,
    UNIQUE KEY uq_recordar_selector (selector),
    CONSTRAINT fk_recordar_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Intentos fallidos de login (límite de intentos por email / IP)
CREATE TABLE login_intentos (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    ip VARCHAR(45) NOT NULL,
    creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_login_intentos_email (email, creado_en),
    KEY idx_login_intentos_ip (ip, creado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Historial de actividad (pestaña "Actividad")
CREATE TABLE actividad_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    actor_id INT NULL,
    accion VARCHAR(50) NOT NULL,
    detalle VARCHAR(255) NULL,
    creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_actividad_usuario (usuario_id, creado_en),
    CONSTRAINT fk_actividad_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_actividad_actor FOREIGN KEY (actor_id) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
