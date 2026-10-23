<?php
session_start();
require_once 'conexion.php';

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $rol = $_POST['rol'] ?? 'estudiante';

    // Validación de roles permitidos en el registro público
    $roles_permitidos = ['estudiante', 'director', 'ponente'];

    if (empty($nombre) || empty($email) || empty($password) || empty($confirm_password)) {
        $error = 'Por favor, completa todos los campos obligatorios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Ingresa un correo electrónico válido.';
    } elseif ($password !== $confirm_password) {
        $error = 'Las contraseñas no coinciden.';
    } elseif (strlen($password) < 6) {
        $error = 'La contraseña debe tener al menos 6 caracteres.';
    } elseif (!in_array($rol, $roles_permitidos)) {
        $error = 'El rol seleccionado no es válido.';
    } else {
        // Verificar si el correo ya existe
        $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error = 'El correo electrónico ya está registrado en TecnoAula.';
        } else {
            // Encriptar la contraseña
            $password_hashed = password_hash($password, PASSWORD_BCRYPT);
            
            // Estado por defecto: 'pendiente' de aprobación por Director/Superadmin
            $estado = 'pendiente';

            $stmt_insert = $pdo->prepare("INSERT INTO usuarios (nombre, email, password, rol, estado) VALUES (?, ?, ?, ?, ?)");
            if ($stmt_insert->execute([$nombre, $email, $password_hashed, $rol, $estado])) {
                $exito = '¡Registro completado con éxito! Tu cuenta está pendiente de aprobación por la administración.';
            } else {
                $error = 'Ocurrió un error al registrar tu cuenta. Inténtalo de nuevo.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear cuenta — TecnoAula</title>
    <style>
        :root {
            --color-iris-bolt: #4255ff;
            --color-ink-charcoal: #282e3e;
            --color-slate-veil: #586380;
            --color-chalk-canvas: #f6f7fb;
            --color-paper-white: #ffffff;
            --color-mist-border: #d9dde8;
            --shadow-md: rgba(40, 46, 62, 0.1) 0px 4px 16px 0px;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', system-ui, -apple-system, sans-serif; }
        body { background-color: var(--color-chalk-canvas); color: var(--color-ink-charcoal); display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px 0; }

        .auth-card {
            background: var(--color-paper-white);
            border: 1px solid var(--color-mist-border);
            border-radius: 8px;
            padding: 32px;
            width: 100%;
            max-width: 440px;
            box-shadow: var(--shadow-md);
        }

        .auth-card h2 { font-size: 24px; font-weight: 600; margin-bottom: 8px; text-align: center; }
        .auth-card p.subtitle { font-size: 14px; color: var(--color-slate-veil); text-align: center; margin-bottom: 24px; }

        .form-group { margin-bottom: 16px; }
        .form-group label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 6px; color: var(--color-ink-charcoal); }

        .input-container { position: relative; display: flex; align-items: center; }
        .form-group input[type="text"],
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group select {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--color-mist-border);
            border-radius: 4px;
            font-size: 14px;
            outline: none;
            background: var(--color-paper-white);
        }

        .form-group input:focus, .form-group select:focus { border-color: var(--color-iris-bolt); }

        .toggle-password {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--color-slate-veil);
        }

        .btn-submit {
            width: 100%;
            background-color: var(--color-iris-bolt);
            color: var(--color-paper-white);
            padding: 12px;
            border: none;
            border-radius: 200px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            margin-top: 8px;
            box-shadow: var(--shadow-md);
            transition: opacity 0.2s;
        }
        .btn-submit:hover { opacity: 0.9; }

        .alert-error {
            background-color: #fde8e8;
            color: #9b1c1c;
            padding: 10px;
            border-radius: 4px;
            font-size: 14px;
            margin-bottom: 16px;
            border: 1px solid #f8b4b4;
        }

        .alert-exito {
            background-color: #def7ec;
            color: #03543f;
            padding: 10px;
            border-radius: 4px;
            font-size: 14px;
            margin-bottom: 16px;
            border: 1px solid #84e1bc;
        }

        .footer-link {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: var(--color-slate-veil);
        }
        .footer-link a { color: var(--color-iris-bolt); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>

    <div class="auth-card">
        <h2>Crea tu cuenta</h2>
        <p class="subtitle">Únete a TecnoAula y empieza a aprender</p>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if (!empty($exito)): ?>
            <div class="alert-exito"><?= htmlspecialchars($exito) ?></div>
        <?php endif; ?>

        <form action="registro.php" method="POST">
            <div class="form-group">
                <label for="nombre">Nombre completo</label>
                <input type="text" id="nombre" name="nombre" required placeholder="Ej. Juan Pérez" value="<?= htmlspecialchars($_POST['nombre'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="email">Correo electrónico</label>
                <input type="email" id="email" name="email" required placeholder="tu@correo.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="rol">Solicitar rol como</label>
                <select id="rol" name="rol" required>
                    <option value="estudiante" <?= (($_POST['rol'] ?? '') === 'estudiante') ? 'selected' : '' ?>>Estudiante</option>
                    <option value="ponente" <?= (($_POST['rol'] ?? '') === 'ponente') ? 'selected' : '' ?>>Ponente / Prof.</option>
                    <option value="director" <?= (($_POST['rol'] ?? '') === 'director') ? 'selected' : '' ?>>Director</option>
                </select>
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <div class="input-container">
                    <input type="password" id="password" name="password" required placeholder="Mínimo 6 caracteres">
                    <button type="button" class="toggle-password" onclick="togglePassword('password')">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirmar contraseña</label>
                <div class="input-container">
                    <input type="password" id="confirm_password" name="confirm_password" required placeholder="Repite tu contraseña">
                    <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-submit">Registrarme</button>
        </form>

        <div class="footer-link">
            ¿Ya tienes una cuenta? <a href="login.php">Inicia sesión</a>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const input = document.getElementById(fieldId);
            if (input.type === 'password') {
                input.type = 'text';
            } else {
                input.type = 'password';
            }
        }
    </script>
</body>
</html>