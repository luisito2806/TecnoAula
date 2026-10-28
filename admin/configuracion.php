<?php
require_once __DIR__ . '/../includes/cursos.php';

$usuario = requiere_rol('superadmin');

if (accion_post() === 'guardar') {
    $nombre = texto_post('nombre_plataforma', 60);
    if ($nombre === '') {
        error_formulario('Escribe el nombre de la plataforma.');
    }
    $guardar = $pdo->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
    $guardar->execute(['nombre_plataforma', $nombre]);
    $guardar->execute(['registro_abierto', empty($_POST['registro_abierto']) ? '0' : '1']);
    $guardar->execute(['catalogo_publico', empty($_POST['catalogo_publico']) ? '0' : '1']);
    registrar_actividad($usuario['id'], 'configuracion', 'Actualizó la configuración de la plataforma');
    hecho('Configuración guardada');
}

$tablas = $pdo->query("SELECT
    (SELECT COUNT(*) FROM usuarios) AS usuarios, (SELECT COUNT(*) FROM cursos) AS cursos,
    (SELECT COUNT(*) FROM temas) AS temas, (SELECT COUNT(*) FROM entregas) AS entregas,
    (SELECT COUNT(*) FROM foro_mensajes) + (SELECT COUNT(*) FROM chat_mensajes) AS mensajes")->fetch();

panel_inicio($usuario, 'Configuración', 'configuracion');
encabezado('Configuración', 'Ajustes generales de la plataforma.', ['Configuración' => null]);
?>
<div class="columnas">
    <form method="post" class="tarjeta">
        <?= campo_csrf() ?><input type="hidden" name="accion" value="guardar">
        <div class="encabezado-tarjeta"><h2>General</h2></div>
        <div class="cuerpo-tarjeta">
            <div class="campo"><label for="cfg-nombre">Nombre de la plataforma</label><input class="input" id="cfg-nombre" name="nombre_plataforma" maxlength="60" required value="<?= e(config('nombre_plataforma', 'Cursos Online')) ?>">
                <small class="ayuda">Aparece en el logotipo, la portada y el título de las pestañas.</small></div>
            <label class="toggle-fila">
                <span><strong>Registro público abierto</strong><small>Cualquiera puede crear una cuenta de alumno desde la portada.</small></span>
                <span class="toggle"><input type="checkbox" name="registro_abierto" value="1"<?= config('registro_abierto', '1') === '1' ? ' checked' : '' ?>><span></span></span>
            </label>
            <label class="toggle-fila">
                <span><strong>Mostrar catálogo en la portada</strong><small>Lista los cursos publicados a los visitantes.</small></span>
                <span class="toggle"><input type="checkbox" name="catalogo_publico" value="1"<?= config('catalogo_publico', '1') === '1' ? ' checked' : '' ?>><span></span></span>
            </label>
            <button class="btn btn-primario" style="margin-top: 16px"><span class="spinner"></span>Guardar configuración</button>
        </div>
    </form>
    <aside>
        <section class="tarjeta cuerpo-tarjeta">
            <h3 style="margin-bottom: 12px">Sistema</h3>
            <dl class="kv">
                <div><dt>Modo desarrollo</dt><dd><?= MODO_DESARROLLO ? 'Activado' : 'Desactivado' ?></dd></div>
                <div><dt>PHP</dt><dd><?= PHP_VERSION ?></dd></div>
                <div><dt>Usuarios</dt><dd><?= (int) $tablas['usuarios'] ?></dd></div>
                <div><dt>Cursos</dt><dd><?= (int) $tablas['cursos'] ?></dd></div>
                <div><dt>Temas</dt><dd><?= (int) $tablas['temas'] ?></dd></div>
                <div><dt>Entregas</dt><dd><?= (int) $tablas['entregas'] ?></dd></div>
                <div><dt>Mensajes</dt><dd><?= (int) $tablas['mensajes'] ?></dd></div>
            </dl>
            <?php if (MODO_DESARROLLO): ?><p class="ayuda" style="margin-top: 16px">En modo desarrollo los enlaces de recuperación se muestran en pantalla. Desactívalo en includes/auth.php antes de publicar.</p><?php endif; ?>
        </section>
    </aside>
</div>
<?php panel_fin(); ?>
