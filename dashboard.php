<?php
require_once __DIR__ . '/includes/auth.php';

// Atajo genérico: lleva a cada usuario al panel de su rol principal
redirigir(ruta_panel(requiere_login()));
