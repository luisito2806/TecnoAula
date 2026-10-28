<?php
require_once __DIR__ . '/../includes/layout.php';

$usuario = requiere_gestion_usuarios();

// Datos que necesita assets/js/usuarios.js
$config = [
    'api' => url('api/usuarios.php'),
    'csrf' => csrf_token(),
    'yo' => ['id' => $usuario['id'], 'superadmin' => tiene_rol($usuario, 'superadmin')],
    'roles' => ROLES,
    'permisos' => PERMISOS,
    'iconos' => ICONOS,
];

$tarjetas = [
    ['total', 'Total de usuarios', 'users', 'metrica-azul'],
    ['alumnos', 'Alumnos activos', 'user', 'metrica-naranja'],
    ['docentes', 'Docentes', 'graduation-cap', 'metrica-violeta'],
    ['directores', 'Directores', 'users', 'metrica-turquesa'],
];

panel_inicio($usuario, 'Gestión de usuarios', 'usuarios');
?>
<nav class="breadcrumb" aria-label="Ruta">
    <a href="<?= url(ruta_panel($usuario)) ?>"><?= icono('home', 15) ?> Inicio</a>
    <?= icono('chevron-right', 14) ?>
    <span>Usuarios</span>
</nav>

<div class="encabezado-pagina">
    <div>
        <h1>Gestión de <span class="texto-primario">usuarios y roles</span></h1>
        <p class="texto-secundario">Administra los usuarios de la plataforma, asigna roles y gestiona sus permisos.</p>
    </div>
</div>

<section class="metricas">
    <?php foreach ($tarjetas as [$clave, $titulo, $ico, $color]): ?>
        <article class="tarjeta metrica" data-metrica="<?= $clave ?>">
            <span class="metrica-icono <?= $color ?>"><?= icono($ico, 22) ?></span>
            <div>
                <p class="metrica-titulo"><?= e($titulo) ?></p>
                <p class="metrica-valor"><span class="skeleton skeleton-texto"></span></p>
                <p class="metrica-tendencia"></p>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<section class="tarjeta tabla-tarjeta">
    <div class="tabla-encabezado">
        <h2>Usuarios de la plataforma</h2>
        <div class="tabla-controles">
            <label class="input-icono buscador">
                <?= icono('search', 18) ?>
                <input type="search" id="filtro-buscar" placeholder="Buscar por nombre o correo…" aria-label="Buscar por nombre o correo">
            </label>
            <select id="filtro-rol" class="select" aria-label="Filtrar por rol">
                <option value="">Todos los roles</option>
                <?php foreach (ROLES as $clave => $rol): ?>
                    <option value="<?= $clave ?>"><?= e($rol['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
            <select id="filtro-estado" class="select" aria-label="Filtrar por estado">
                <option value="">Todos los estados</option>
                <option value="activo">Activo</option>
                <option value="inactivo">Inactivo</option>
            </select>
            <button type="button" class="btn btn-contorno" id="btn-mas-filtros" aria-expanded="false"><?= icono('filter', 16) ?> Más filtros</button>
            <button type="button" class="btn btn-primario" id="btn-nuevo"><?= icono('plus', 18) ?> Nuevo usuario</button>
        </div>
        <div class="mas-filtros" id="mas-filtros" hidden>
            <label>Fecha de alta desde <input type="date" id="filtro-desde" class="input"></label>
            <label>hasta <input type="date" id="filtro-hasta" class="input"></label>
            <button type="button" class="enlace" id="btn-limpiar-filtros">Limpiar filtros</button>
        </div>
    </div>

    <div class="tabla-scroll">
        <table class="tabla">
            <thead>
                <tr>
                    <th><button type="button" class="ordenar" data-orden="nombre">Nombre <?= icono('arrow-up-down', 14) ?></button></th>
                    <th>Email</th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th><button type="button" class="ordenar activo" data-orden="creado_en">Fecha alta <?= icono('arrow-up-down', 14) ?></button></th>
                    <th class="col-acciones">Acciones</th>
                </tr>
            </thead>
            <tbody id="tabla-usuarios"></tbody>
        </table>
    </div>

    <div class="tabla-pie">
        <label class="por-pagina">Mostrar
            <select id="por-pagina" class="select select-chico">
                <option>10</option><option>25</option><option>50</option>
            </select>
            por página
        </label>
        <span id="resumen-paginacion" class="texto-secundario"></span>
        <nav class="paginacion" id="paginacion" aria-label="Paginación"></nav>
    </div>
</section>

<!-- Drawer "Editar usuario" -->
<div class="capa" id="capa-drawer" hidden></div>
<aside class="drawer" id="drawer" aria-hidden="true" role="dialog" aria-labelledby="drawer-nombre">
    <header class="drawer-encabezado">
        <h2>Editar usuario</h2>
        <button type="button" class="btn-icono" data-cerrar-drawer aria-label="Cerrar"><?= icono('x', 20) ?></button>
    </header>
    <div class="drawer-usuario" id="drawer-usuario"></div>
    <div class="pestanas" role="tablist">
        <button type="button" role="tab" data-pestana="info">Información</button>
        <button type="button" role="tab" data-pestana="roles">Roles y permisos</button>
        <button type="button" role="tab" data-pestana="actividad">Actividad</button>
    </div>
    <div class="drawer-cuerpo" id="drawer-cuerpo"></div>
    <footer class="drawer-pie">
        <button type="button" class="btn btn-contorno" data-cerrar-drawer>Cancelar</button>
        <button type="button" class="btn btn-primario" id="btn-guardar-drawer"><span class="spinner"></span>Guardar cambios</button>
    </footer>
</aside>

<!-- Modal reutilizable (nuevo usuario / confirmaciones) -->
<div class="modal-fondo" id="modal" hidden>
    <div class="modal tarjeta" role="dialog" aria-modal="true" aria-labelledby="modal-titulo">
        <header class="modal-encabezado">
            <h2 id="modal-titulo"></h2>
            <button type="button" class="btn-icono" data-cerrar-modal aria-label="Cerrar"><?= icono('x', 20) ?></button>
        </header>
        <div class="modal-cuerpo" id="modal-cuerpo"></div>
        <footer class="modal-pie" id="modal-pie"></footer>
    </div>
</div>

<script>window.APP = <?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;</script>
<?php panel_fin(['assets/js/usuarios.js']); ?>
