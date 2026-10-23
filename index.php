<?php
session_start();
require_once 'conexion.php';

// Obtener algunos cursos publicados para mostrar en el catálogo
$stmt = $pdo->query("SELECT c.*, u.nombre as ponente_nombre FROM cursos c LEFT JOIN usuarios u ON c.ponente_id = u.id LIMIT 4");
// Nota: Si la tabla usuarios está bien escrita arriba, ajusta la consulta si es necesario:
$stmt = $pdo->query("SELECT c.*, u.nombre as ponente_nombre FROM cursos c LEFT JOIN usuarios u ON c.ponente_id = u.id LIMIT 4");
$cursos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TecnoAula — Tu plataforma de aprendizaje</title>
    <style>
        :root {
            --color-iris-bolt: #4255ff;
            --color-ink-charcoal: #282e3e;
            --color-slate-veil: #586380;
            --color-chalk-canvas: #f6f7fb;
            --color-paper-white: #ffffff;
            --color-mist-border: #d9dde8;
            --shadow-md: rgba(40, 46, 62, 0.1) 0px 4px 16px 0px;
            --shadow-sm: rgba(40, 46, 62, 0.1) 0px 2px 4px 0px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
        }

        body {
            background-color: var(--color-chalk-canvas);
            color: var(--color-ink-charcoal);
            line-height: 1.5;
        }

        /* Top Navigation Bar */
        nav {
            background: var(--color-paper-white);
            height: 56px;
            box-shadow: var(--shadow-md);
            position: sticky;
            top: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 40px;
        }

        .logo {
            font-size: 20px;
            font-weight: 700;
            color: var(--color-iris-bolt);
            text-decoration: none;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .btn-pill {
            background-color: var(--color-iris-bolt);
            color: var(--color-paper-white);
            padding: 10px 24px;
            border-radius: 200px;
            font-weight: 600;
            text-decoration: none;
            box-shadow: var(--shadow-sm);
            transition: opacity 0.2s;
        }

        .btn-pill:hover {
            opacity: 0.9;
        }

        /* Hero Section */
        .hero {
            max-width: 1200px;
            margin: 80px auto;
            text-align: center;
            padding: 0 20px;
        }

        .hero h1 {
            font-size: 44px;
            font-weight: 700;
            color: var(--color-ink-charcoal);
            margin-bottom: 16px;
        }

        .hero p {
            font-size: 18px;
            color: var(--color-slate-veil);
            margin-bottom: 32px;
        }

        /* Cursos Section */
        .container {
            max-width: 1200px;
            margin: 0 auto 80px auto;
            padding: 0 20px;
        }

        .section-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 24px;
        }

        .grid-cursos {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 24px;
        }

        .card {
            background: var(--color-paper-white);
            border: 1px solid var(--color-mist-border);
            border-radius: 8px;
            padding: 24px;
            box-shadow: var(--shadow-sm);
        }

        .card h3 {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--color-ink-charcoal);
        }

        .card p {
            font-size: 14px;
            color: var(--color-slate-veil);
            margin-bottom: 16px;
        }

        .card-footer {
            font-size: 12px;
            color: var(--color-slate-veil);
            border-top: 1px solid var(--color-mist-border);
            padding-top: 12px;
        }
    </style>
</head>
<body>

    <nav>
        <a href="index.php" class="logo">TecnoAula</a>
        <div class="nav-right">
            <?php if(isset($_SESSION['usuario_id'])): ?>
                <a href="dashboard.php" class="btn-pill">Mi Panel</a>
            <?php else: ?>
                <a href="login.php" class="btn-pill">Iniciar sesión</a>
            <?php endif; ?>
        </div>
    </nav>

    <div class="hero">
        <h1>Tu plataforma de aprendizaje en línea</h1>
        <p>Explora cursos impartidos por expertos, expande tus conocimientos y alcanza tus metas profesionales.</p>
        <a href="registro.php" class="btn-pill" style="font-size: 16px; padding: 12px 32px;">Regístrate gratis</a>
    </div>

    <div class="container">
        <h2 class="section-title">Cursos Destacados</h2>
        <div class="grid-cursos">
            <?php if(!empty($cursos)): ?>
                <?php foreach($cursos as $curso): ?>
                    <div class="card">
                        <h3><?= htmlspecialchars($curso['titulo']) ?></h3>
                        <p><?= htmlspecialchars(substr($curso['descripcion'], 0, 90)) ?>...</p>
                        <div class="card-footer">
                            Ponente: <?= htmlspecialchars($curso['ponente_nombre'] ?? 'Por asignar') ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="card">
                    <h3>Introducción a la Programación Web</h3>
                    <p>Aprende las bases de HTML, CSS y PHP desde cero con casos prácticos.</p>
                    <div class="card-footer">Ponente: TecnoAula Academy</div>
                </div>
                <div class="card">
                    <h3>Gestión de Bases de Datos MySQL</h3>
                    <p>Domina consultas SQL avanzadas, normalización y optimización en servidores Linux.</p>
                    <div class="card-footer">Ponente: Equipo Técnico</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

</body>
</html>