<?php
require_once __DIR__ . '/../includes/cursos.php';

$usuario = requiere_gestion_usuarios();

$principal = $pdo->query("SELECT rol, COUNT(*) FROM usuarios WHERE eliminado_en IS NULL GROUP BY rol")->fetchAll(PDO::FETCH_KEY_PAIR);
$adicional = $pdo->query("SELECT r.rol, COUNT(*) FROM usuario_roles r JOIN usuarios u ON u.id = r.usuario_id AND u.eliminado_en IS NULL GROUP BY r.rol")->fetchAll(PDO::FETCH_KEY_PAIR);
$permisos = $pdo->query("SELECT p.permiso, COUNT(*) FROM usuario_permisos p JOIN usuarios u ON u.id = p.usuario_id AND u.eliminado_en IS NULL GROUP BY p.permiso")->fetchAll(PDO::FETCH_KEY_PAIR);

// Qué puede hacer cada rol (referencia; los permisos adicionales amplían esto por usuario)
$accesos = [
    'superadmin' => ['Dashboard', 'Usuarios', 'Roles', 'Cursos', 'Reportes', 'Configuración'],
    'director' => ['Alumnos', 'Asignar cursos', 'Reportes'],
    'ponente' => ['Mis cursos', 'Alumnos', 'Reportes', 'Mensajes', 'Perfil'],
    'estudiante' => ['Mis cursos', 'Calendario', 'Tareas', 'Foros', 'Mensajes', 'Calificaciones', 'Perfil'],
];
$descripcion_permisos = [
    'crear_cursos' => 'Permite crear y administrar cursos propios aunque el rol no sea Docente.',
    'ver_reportes' => 'Da acceso a los reportes de todos los cursos.',
    'gestionar_usuarios' => 'Da acceso a la gestión de usuarios (sin poder tocar a los Super Admin).',
];

panel_inicio($usuario, 'Roles', 'roles');
encabezado('Roles y permisos', 'Catálogo de roles de la plataforma. Asigna roles desde la gestión de usuarios.', ['Roles' => null],
    '<a class="btn btn-primario" href="' . url('admin/usuarios.php') . '">' . icono('user-cog', 17) . ' Asignar roles</a>');
?>
<div class="grid-cursos" style="margin-bottom: 24px">
    <?php foreach (ROLES as $clave => $rol): ?>
        <section class="tarjeta cuerpo-tarjeta" style="display: grid; gap: 12px; align-content: start">
            <span class="rol-circulo"><?= icono($rol['icono'], 18) ?></span>
            <div><h3><?= $rol['nombre'] ?></h3><p class="texto-secundario" style="font-size: 14px"><?= e($rol['descripcion']) ?></p></div>
            <dl class="kv">
                <div><dt>Rol principal</dt><dd><?= (int) ($principal[$clave] ?? 0) ?></dd></div>
                <div><dt>Rol adicional</dt><dd><?= (int) ($adicional[$clave] ?? 0) ?></dd></div>
            </dl>
            <div class="materiales"><?php foreach ($accesos[$clave] as $a): ?><span class="chip"><span><?= $a ?></span></span><?php endforeach; ?></div>
            <a class="enlace" href="<?= url('admin/usuarios.php?rol=' . $clave) ?>">Ver usuarios <?= icono('arrow-right', 14) ?></a>
        </section>
    <?php endforeach; ?>
</div>
<section class="tarjeta">
    <div class="encabezado-tarjeta"><h2>Permisos adicionales</h2><span class="texto-tenue">Se otorgan por usuario, sin importar su rol</span></div>
    <ul class="lista">
        <?php foreach (PERMISOS as $clave => $texto): ?>
            <li><span class="lista-icono"><?= icono('key', 16) ?></span><div class="lista-cuerpo"><strong><?= $texto ?></strong><small><?= $descripcion_permisos[$clave] ?></small></div>
                <span class="badge"><?= (int) ($permisos[$clave] ?? 0) ?> usuarios</span></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php panel_fin(); ?>
