<?php
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valido()) {
    cerrar_sesion();
    flash('exito', 'Cerraste sesión correctamente.');
}
redirigir('index.php');
