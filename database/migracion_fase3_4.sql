-- TecnoAula — Migración Fases 3 y 4 (cursos, estructura, avance, tareas, foros, chat)
-- Requiere haber ejecutado antes migracion_fase1_2.sql
-- Ejecutar una sola vez:  mysql -u root -p TecnoAula < database/migracion_fase3_4.sql

-- 1. Datos extra del curso
ALTER TABLE cursos
    ADD COLUMN categoria      VARCHAR(100) NULL AFTER descripcion,
    ADD COLUMN modalidad      ENUM('en_linea','presencial','mixta') NOT NULL DEFAULT 'en_linea' AFTER estado,
    ADD COLUMN fecha_inicio   DATE NULL AFTER modalidad,
    ADD COLUMN imagen_url     VARCHAR(255) NULL AFTER fecha_inicio,
    ADD COLUMN creado_en      TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN actualizado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    ADD COLUMN eliminado_en   TIMESTAMP NULL,
    MODIFY estado ENUM('borrador','publicado') NOT NULL DEFAULT 'borrador';

-- 2. Estructura: bloques -> temas -> materiales
CREATE TABLE bloques (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curso_id INT NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT NULL,
    orden INT NOT NULL DEFAULT 0,
    KEY idx_bloques_curso (curso_id, orden),
    CONSTRAINT fk_bloques_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE temas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bloque_id INT NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    contenido TEXT NULL,
    duracion_min INT NOT NULL DEFAULT 0,
    orden INT NOT NULL DEFAULT 0,
    KEY idx_temas_bloque (bloque_id, orden),
    CONSTRAINT fk_temas_bloque FOREIGN KEY (bloque_id) REFERENCES bloques(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE materiales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tema_id INT NOT NULL,
    tipo ENUM('video','documento','enlace') NOT NULL DEFAULT 'enlace',
    titulo VARCHAR(150) NOT NULL,
    url VARCHAR(500) NOT NULL,
    orden INT NOT NULL DEFAULT 0,
    KEY idx_materiales_tema (tema_id, orden),
    CONSTRAINT fk_materiales_tema FOREIGN KEY (tema_id) REFERENCES temas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Inscripciones y avance del alumno
CREATE TABLE inscripciones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curso_id INT NOT NULL,
    alumno_id INT NOT NULL,
    inscrito_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_inscripcion (curso_id, alumno_id),
    KEY idx_inscripciones_alumno (alumno_id),
    CONSTRAINT fk_inscripciones_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    CONSTRAINT fk_inscripciones_alumno FOREIGN KEY (alumno_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE progreso_temas (
    alumno_id INT NOT NULL,
    tema_id INT NOT NULL,
    completado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (alumno_id, tema_id),
    CONSTRAINT fk_progreso_alumno FOREIGN KEY (alumno_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_progreso_tema FOREIGN KEY (tema_id) REFERENCES temas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Evaluaciones (tareas), entregas y calificaciones
CREATE TABLE tareas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curso_id INT NOT NULL,
    bloque_id INT NULL,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT NULL,
    fecha_entrega DATETIME NULL,
    puntos INT NOT NULL DEFAULT 100,
    creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_tareas_curso (curso_id, fecha_entrega),
    CONSTRAINT fk_tareas_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    CONSTRAINT fk_tareas_bloque FOREIGN KEY (bloque_id) REFERENCES bloques(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE entregas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tarea_id INT NOT NULL,
    alumno_id INT NOT NULL,
    contenido TEXT NULL,
    url VARCHAR(500) NULL,
    entregado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    calificacion DECIMAL(6,2) NULL,
    comentario TEXT NULL,
    calificado_en DATETIME NULL,
    UNIQUE KEY uq_entrega (tarea_id, alumno_id),
    CONSTRAINT fk_entregas_tarea FOREIGN KEY (tarea_id) REFERENCES tareas(id) ON DELETE CASCADE,
    CONSTRAINT fk_entregas_alumno FOREIGN KEY (alumno_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Foros por curso
CREATE TABLE foros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    curso_id INT NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT NULL,
    creado_por INT NULL,
    creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_foros_curso (curso_id),
    CONSTRAINT fk_foros_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    CONSTRAINT fk_foros_autor FOREIGN KEY (creado_por) REFERENCES usuarios(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE foro_mensajes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    foro_id INT NOT NULL,
    usuario_id INT NOT NULL,
    contenido TEXT NOT NULL,
    creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_foro_mensajes (foro_id, creado_en),
    CONSTRAINT fk_foro_mensajes_foro FOREIGN KEY (foro_id) REFERENCES foros(id) ON DELETE CASCADE,
    CONSTRAINT fk_foro_mensajes_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Chat del curso (con marca de último mensaje leído por usuario)
CREATE TABLE chat_mensajes (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    curso_id INT NOT NULL,
    usuario_id INT NOT NULL,
    contenido TEXT NOT NULL,
    creado_en TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_chat_curso (curso_id, id),
    CONSTRAINT fk_chat_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE,
    CONSTRAINT fk_chat_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE chat_lecturas (
    usuario_id INT NOT NULL,
    curso_id INT NOT NULL,
    ultimo_id BIGINT NOT NULL DEFAULT 0,
    PRIMARY KEY (usuario_id, curso_id),
    CONSTRAINT fk_lecturas_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    CONSTRAINT fk_lecturas_curso FOREIGN KEY (curso_id) REFERENCES cursos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Configuración general de la plataforma
CREATE TABLE configuracion (
    clave VARCHAR(50) PRIMARY KEY,
    valor VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO configuracion (clave, valor) VALUES
('nombre_plataforma', 'Cursos Online'),
('registro_abierto', '1'),
('catalogo_publico', '1');
