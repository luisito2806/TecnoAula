<?php
require_once __DIR__ . '/layout.php';
$usuario = usuario_actual();
panel_inicio($usuario, 'Acceso denegado', '');
?>
<div class="estado-vacio tarjeta">
    <span class="estado-vacio-icono"><?= icono('shield', 32) ?></span>
    <h1>Acceso denegado</h1>
    <p>No tienes permisos para ver esta sección.</p>
    <a href="<?= url(ruta_panel($usuario)) ?>" class="btn btn-primario"><?= icono('arrow-left', 18) ?> Ir a mi panel</a>
</div>
<?php panel_fin(); ?>
