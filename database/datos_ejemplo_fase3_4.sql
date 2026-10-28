-- TecnoAula — Cursos de ejemplo (opcional). Usa los usuarios de datos_ejemplo.sql
-- Ejecutar después de migracion_fase3_4.sql:  mysql -u root -p TecnoAula < database/datos_ejemplo_fase3_4.sql

SET @ana     = (SELECT id FROM usuarios WHERE email = 'ana.torres@tecnoaula.com');
SET @jorge   = (SELECT id FROM usuarios WHERE email = 'jorge.ramirez@tecnoaula.com');
SET @diego   = (SELECT id FROM usuarios WHERE email = 'diego.castillo@tecnoaula.com');
SET @valeria = (SELECT id FROM usuarios WHERE email = 'valeria.nunez@tecnoaula.com');
SET @miguel  = (SELECT id FROM usuarios WHERE email = 'miguel.ortega@tecnoaula.com');
SET @andres  = (SELECT id FROM usuarios WHERE email = 'andres.vega@tecnoaula.com');
SET @camila  = (SELECT id FROM usuarios WHERE email = 'camila.flores@tecnoaula.com');
SET @emilio  = (SELECT id FROM usuarios WHERE email = 'emilio.paredes@tecnoaula.com');

-- ===================== Curso 1: Programación Web con PHP =====================
INSERT INTO cursos (titulo, descripcion, categoria, ponente_id, estado, modalidad, fecha_inicio)
VALUES ('Programación Web con PHP', 'Construye sitios dinámicos desde cero: HTML, formularios, PHP, sesiones y conexión a MySQL con casos prácticos.',
        'Desarrollo web', @ana, 'publicado', 'en_linea', CURDATE() - INTERVAL 21 DAY);
SET @c1 = LAST_INSERT_ID();

INSERT INTO bloques (curso_id, titulo, descripcion, orden) VALUES (@c1, 'Fundamentos de la web', 'Cómo viaja una página desde el servidor hasta tu navegador.', 1);
SET @b = LAST_INSERT_ID();
INSERT INTO temas (bloque_id, titulo, contenido, duracion_min, orden) VALUES
(@b, '¿Cómo funciona la web?', 'Cliente, servidor, peticiones HTTP y respuestas. Veremos qué pasa cuando escribes una dirección en el navegador.', 20, 1),
(@b, 'HTML esencial', 'Estructura de un documento, etiquetas semánticas, enlaces, imágenes y formularios.', 35, 2),
(@b, 'CSS para principiantes', 'Selectores, caja, flexbox y diseño responsivo básico.', 40, 3);
INSERT INTO materiales (tema_id, tipo, titulo, url, orden)
SELECT t.id, 'documento', 'Guía de HTTP (MDN)', 'https://developer.mozilla.org/es/docs/Web/HTTP/Overview', 1 FROM temas t WHERE t.bloque_id = @b AND t.orden = 1
UNION ALL SELECT t.id, 'enlace', 'Referencia de etiquetas HTML', 'https://developer.mozilla.org/es/docs/Web/HTML/Element', 1 FROM temas t WHERE t.bloque_id = @b AND t.orden = 2
UNION ALL SELECT t.id, 'documento', 'Guía de Flexbox', 'https://developer.mozilla.org/es/docs/Learn/CSS/CSS_layout/Flexbox', 1 FROM temas t WHERE t.bloque_id = @b AND t.orden = 3;

INSERT INTO bloques (curso_id, titulo, descripcion, orden) VALUES (@c1, 'PHP desde cero', 'Sintaxis, variables, control de flujo y funciones.', 2);
SET @b2 = LAST_INSERT_ID();
INSERT INTO temas (bloque_id, titulo, contenido, duracion_min, orden) VALUES
(@b2, 'Variables y tipos', 'Cadenas, números, arreglos y cómo PHP convierte entre tipos.', 30, 1),
(@b2, 'Condicionales y ciclos', 'if, switch, for, foreach y while con ejemplos.', 35, 2),
(@b2, 'Formularios y $_POST', 'Recibir datos del usuario, validarlos y mostrarlos de forma segura.', 45, 3);
INSERT INTO materiales (tema_id, tipo, titulo, url, orden)
SELECT t.id, 'documento', 'Manual oficial de PHP', 'https://www.php.net/manual/es/langref.php', 1 FROM temas t WHERE t.bloque_id = @b2 AND t.orden = 1;

INSERT INTO bloques (curso_id, titulo, descripcion, orden) VALUES (@c1, 'PHP y bases de datos', 'Conecta tu aplicación con MySQL usando PDO.', 3);
SET @b3 = LAST_INSERT_ID();
INSERT INTO temas (bloque_id, titulo, contenido, duracion_min, orden) VALUES
(@b3, 'Conexión con PDO', 'Crear la conexión, manejar errores y consultas preparadas.', 40, 1),
(@b3, 'CRUD completo', 'Crear, leer, actualizar y eliminar registros desde formularios.', 60, 2);

INSERT INTO tareas (curso_id, bloque_id, titulo, descripcion, fecha_entrega, puntos) VALUES
(@c1, @b, 'Página personal en HTML', 'Crea una página con tu presentación usando etiquetas semánticas y una hoja de estilos.', NOW() - INTERVAL 5 DAY, 100),
(@c1, @b2, 'Formulario de contacto', 'Programa un formulario que valide nombre, correo y mensaje con PHP.', NOW() + INTERVAL 4 DAY, 100),
(@c1, @b3, 'Agenda con MySQL', 'Desarrolla una agenda de contactos con alta, edición y baja usando PDO.', NOW() + INTERVAL 18 DAY, 100);
SET @t1 = (SELECT id FROM tareas WHERE curso_id = @c1 AND titulo = 'Página personal en HTML');
SET @t2 = (SELECT id FROM tareas WHERE curso_id = @c1 AND titulo = 'Formulario de contacto');

-- ===================== Curso 2: Bases de datos con MySQL =====================
INSERT INTO cursos (titulo, descripcion, categoria, ponente_id, estado, modalidad, fecha_inicio)
VALUES ('Bases de datos con MySQL', 'Diseña modelos relacionales, normaliza tablas y escribe consultas SQL para reportes reales.',
        'Bases de datos', @ana, 'publicado', 'mixta', CURDATE() - INTERVAL 10 DAY);
SET @c2 = LAST_INSERT_ID();
INSERT INTO bloques (curso_id, titulo, descripcion, orden) VALUES (@c2, 'Modelo relacional', 'Entidades, relaciones y llaves.', 1);
SET @b = LAST_INSERT_ID();
INSERT INTO temas (bloque_id, titulo, contenido, duracion_min, orden) VALUES
(@b, 'Tablas, filas y columnas', 'Qué es una base de datos relacional y cuándo usarla.', 25, 1),
(@b, 'Llaves primarias y foráneas', 'Cómo se relacionan las tablas y por qué importa la integridad.', 30, 2),
(@b, 'Normalización', 'Primera, segunda y tercera forma normal con ejemplos.', 40, 3);
INSERT INTO bloques (curso_id, titulo, descripcion, orden) VALUES (@c2, 'Consultas SQL', 'SELECT, JOIN y funciones de agregación.', 2);
SET @b = LAST_INSERT_ID();
INSERT INTO temas (bloque_id, titulo, contenido, duracion_min, orden) VALUES
(@b, 'SELECT y filtros', 'WHERE, ORDER BY, LIMIT y operadores.', 30, 1),
(@b, 'JOIN entre tablas', 'INNER, LEFT y RIGHT JOIN con casos prácticos.', 45, 2);
INSERT INTO tareas (curso_id, bloque_id, titulo, descripcion, fecha_entrega, puntos) VALUES
(@c2, NULL, 'Diagrama entidad-relación', 'Diseña el modelo de datos de una biblioteca escolar.', NOW() + INTERVAL 7 DAY, 50);

-- ===================== Curso 3: Cálculo diferencial =====================
INSERT INTO cursos (titulo, descripcion, categoria, ponente_id, estado, modalidad, fecha_inicio)
VALUES ('Cálculo diferencial', 'Límites, derivadas y sus aplicaciones explicadas paso a paso con ejercicios resueltos.',
        'Matemáticas', @jorge, 'publicado', 'presencial', CURDATE() - INTERVAL 30 DAY);
SET @c3 = LAST_INSERT_ID();
INSERT INTO bloques (curso_id, titulo, descripcion, orden) VALUES (@c3, 'Límites', 'La idea de acercarse a un valor.', 1);
SET @b = LAST_INSERT_ID();
INSERT INTO temas (bloque_id, titulo, contenido, duracion_min, orden) VALUES
(@b, 'Concepto de límite', 'Definición intuitiva y notación.', 30, 1),
(@b, 'Límites laterales e infinitos', 'Comportamiento de funciones cerca de puntos críticos.', 35, 2);
INSERT INTO bloques (curso_id, titulo, descripcion, orden) VALUES (@c3, 'Derivadas', 'Razón de cambio instantánea.', 2);
SET @b = LAST_INSERT_ID();
INSERT INTO temas (bloque_id, titulo, contenido, duracion_min, orden) VALUES
(@b, 'Definición de derivada', 'La derivada como límite del cociente de diferencias.', 40, 1),
(@b, 'Reglas de derivación', 'Potencia, producto, cociente y regla de la cadena.', 50, 2);
INSERT INTO tareas (curso_id, bloque_id, titulo, descripcion, fecha_entrega, puntos) VALUES
(@c3, NULL, 'Ejercicios de límites', 'Resuelve la lista de 10 ejercicios y sube tus procedimientos.', NOW() - INTERVAL 2 DAY, 100);
SET @t3 = LAST_INSERT_ID();

-- ===================== Curso 4: borrador =====================
INSERT INTO cursos (titulo, descripcion, categoria, ponente_id, estado, modalidad, fecha_inicio)
VALUES ('Diseño de experiencia de usuario', 'Investigación con usuarios, prototipos y pruebas de usabilidad.', 'Diseño', @ana, 'borrador', 'en_linea', CURDATE() + INTERVAL 30 DAY);
SET @c4 = LAST_INSERT_ID();
INSERT INTO bloques (curso_id, titulo, orden) VALUES (@c4, 'Introducción al UX', 1);

-- ===================== Inscripciones =====================
INSERT INTO inscripciones (curso_id, alumno_id) VALUES
(@c1, @diego), (@c1, @valeria), (@c1, @miguel), (@c1, @andres), (@c1, @camila),
(@c2, @diego), (@c2, @andres), (@c2, @emilio),
(@c3, @valeria), (@c3, @miguel), (@c3, @camila);

-- ===================== Avance =====================
-- Diego terminó el bloque 1 y parte del 2 del curso de PHP
INSERT INTO progreso_temas (alumno_id, tema_id)
SELECT @diego, t.id FROM temas t JOIN bloques b ON b.id = t.bloque_id WHERE b.curso_id = @c1 AND (b.orden = 1 OR (b.orden = 2 AND t.orden <= 2));
INSERT INTO progreso_temas (alumno_id, tema_id)
SELECT @valeria, t.id FROM temas t JOIN bloques b ON b.id = t.bloque_id WHERE b.curso_id = @c1 AND b.orden = 1;
INSERT INTO progreso_temas (alumno_id, tema_id)
SELECT @miguel, t.id FROM temas t JOIN bloques b ON b.id = t.bloque_id WHERE b.curso_id = @c1 AND b.orden = 1 AND t.orden = 1;
INSERT INTO progreso_temas (alumno_id, tema_id)
SELECT @diego, t.id FROM temas t JOIN bloques b ON b.id = t.bloque_id WHERE b.curso_id = @c2 AND b.orden = 1;
INSERT INTO progreso_temas (alumno_id, tema_id)
SELECT @valeria, t.id FROM temas t JOIN bloques b ON b.id = t.bloque_id WHERE b.curso_id = @c3;

-- ===================== Entregas =====================
INSERT INTO entregas (tarea_id, alumno_id, contenido, url, entregado_en, calificacion, comentario, calificado_en) VALUES
(@t1, @diego,   'Adjunto el enlace a mi página publicada.', 'https://example.com/diego', NOW() - INTERVAL 6 DAY, 95, 'Muy buen uso de etiquetas semánticas.', NOW() - INTERVAL 3 DAY),
(@t1, @valeria, 'Mi página personal con sección de proyectos.', 'https://example.com/valeria', NOW() - INTERVAL 6 DAY, 88, 'Cuida el contraste de colores.', NOW() - INTERVAL 3 DAY),
(@t1, @miguel,  'Entrega de la página.', NULL, NOW() - INTERVAL 5 DAY, NULL, NULL, NULL),
(@t2, @diego,   'Formulario con validación del lado del servidor.', 'https://example.com/diego-form', NOW() - INTERVAL 1 DAY, NULL, NULL, NULL),
(@t3, @valeria, 'Procedimientos de los 10 ejercicios.', NULL, NOW() - INTERVAL 3 DAY, 100, 'Excelente.', NOW() - INTERVAL 1 DAY);

-- ===================== Foros =====================
INSERT INTO foros (curso_id, titulo, descripcion, creado_por) VALUES
(@c1, 'Dudas generales', 'Pregunta aquí cualquier duda sobre el curso.', @ana);
SET @f1 = LAST_INSERT_ID();
INSERT INTO foro_mensajes (foro_id, usuario_id, contenido, creado_en) VALUES
(@f1, @miguel, '¿La tarea del formulario se puede entregar con un enlace a GitHub?', NOW() - INTERVAL 2 DAY),
(@f1, @ana, 'Sí, Miguel. Pega el enlace en el campo de URL de la entrega.', NOW() - INTERVAL 2 DAY + INTERVAL 3 HOUR),
(@f1, @diego, 'Gracias, yo tenía la misma duda.', NOW() - INTERVAL 1 DAY);
INSERT INTO foros (curso_id, titulo, descripcion, creado_por) VALUES
(@c3, 'Ejercicios resueltos', 'Comparte tus procedimientos y compáralos con tus compañeros.', @jorge);

-- ===================== Chat =====================
INSERT INTO chat_mensajes (curso_id, usuario_id, contenido, creado_en) VALUES
(@c1, @ana, '¡Bienvenidos al curso! Recuerden revisar el bloque 1 esta semana.', NOW() - INTERVAL 20 DAY),
(@c1, @valeria, '¿La sesión en vivo será el jueves?', NOW() - INTERVAL 1 DAY),
(@c1, @ana, 'Así es, jueves a las 18:00.', NOW() - INTERVAL 20 HOUR);
