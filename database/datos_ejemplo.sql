-- TecnoAula — Usuarios de ejemplo (opcional, para probar el panel de administración)
-- Contraseña de todos: TecnoAula2026
-- Ejecutar después de la migración:  mysql -u root -p TecnoAula < database/datos_ejemplo.sql

SET @pass = '$2y$12$6tiVMCGblQuOmN.9EQKxWeIGa7yPZh1Nh8cXrPDqCMW1j2Mj.G2kO';

INSERT INTO usuarios (nombre, email, password, cargo, rol, estado, creado_en) VALUES
('Laura Méndez',     'laura.mendez@tecnoaula.com',   @pass, 'Director Académico',        'director',   'activo',   NOW() - INTERVAL 200 DAY),
('Ricardo Salas',    'ricardo.salas@tecnoaula.com',  @pass, 'Director de Posgrado',      'director',   'activo',   NOW() - INTERVAL 150 DAY),
('Ana Torres',       'ana.torres@tecnoaula.com',     @pass, 'Ingeniería en Sistemas',    'ponente',    'activo',   NOW() - INTERVAL 120 DAY),
('Jorge Ramírez',    'jorge.ramirez@tecnoaula.com',  @pass, 'Matemáticas Aplicadas',     'ponente',    'activo',   NOW() - INTERVAL 90 DAY),
('Sofía Herrera',    'sofia.herrera@tecnoaula.com',  @pass, 'Diseño Gráfico',            'ponente',    'inactivo', NOW() - INTERVAL 60 DAY),
('Diego Castillo',   'diego.castillo@tecnoaula.com', @pass, 'Ingeniería en Sistemas',    'estudiante', 'activo',   NOW() - INTERVAL 80 DAY),
('Valeria Núñez',    'valeria.nunez@tecnoaula.com',  @pass, 'Administración',            'estudiante', 'activo',   NOW() - INTERVAL 45 DAY),
('Miguel Ortega',    'miguel.ortega@tecnoaula.com',  @pass, 'Ingeniería Industrial',     'estudiante', 'activo',   NOW() - INTERVAL 40 DAY),
('Fernanda Ríos',    'fernanda.rios@tecnoaula.com',  @pass, 'Contaduría',                'estudiante', 'inactivo', NOW() - INTERVAL 35 DAY),
('Andrés Vega',      'andres.vega@tecnoaula.com',    @pass, 'Ingeniería en Sistemas',    'estudiante', 'activo',   NOW() - INTERVAL 20 DAY),
('Camila Flores',    'camila.flores@tecnoaula.com',  @pass, 'Psicología',                'estudiante', 'activo',   NOW() - INTERVAL 10 DAY),
('Emilio Paredes',   'emilio.paredes@tecnoaula.com', @pass, 'Arquitectura',              'estudiante', 'activo',   NOW() - INTERVAL 3 DAY);

-- Ana Torres también es Director y puede ver reportes
INSERT INTO usuario_roles (usuario_id, rol)
SELECT id, 'director' FROM usuarios WHERE email = 'ana.torres@tecnoaula.com';

INSERT INTO usuario_permisos (usuario_id, permiso)
SELECT id, 'crear_cursos' FROM usuarios WHERE rol = 'ponente'
UNION ALL
SELECT id, 'ver_reportes' FROM usuarios WHERE rol = 'director' OR email = 'ana.torres@tecnoaula.com';
