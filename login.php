<?php
session_start();
require_once 'conexion.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Por favor, completa todos los campos.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario && password_verify($password, $usuario['password'])) {
            // Verificar si su cuenta ha sido aprobada (excepto superadmin)
            if ($usuario['rol'] !== 'superadmin' && $usuario['estado'] !== 'aprobado') {
                $error = 'Tu cuenta se encuentra pendiente de aprobación por la administración.';
            } else {
                $_SESSION['usuario_id'] = $usuario['id'];
                $_SESSION['nombre'] = $usuario['nombre'];
                $_SESSION['rol'] = $usuario['rol'];
                
                header('Location: dashboard.php');
                exit;
            }
        } else {
            $error = 'Correo electrónico o contraseña incorrectos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar sesión — TecnoAula</title>
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

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background-color: var(--color-chalk-canvas); color: var(--color-ink-charcoal); display: flex; justify-content: center; align-items: center; min-height: 100vh; }

        .auth-card {
            background: var(--color-paper-white);
            border: 1px solid var(--color-mist-border);
            border-radius: 8px;
            padding: 32px;
            width: 100%;
            max-width: 400px;
            box-shadow: var(--shadow-md);
        }

        .auth-card h2 { font-size: 24px; font-weight: 600; margin-bottom: 24px; text-align: center; }

        .form-group { margin-bottom: 16px; position: relative; }
        .form-group label { display: block; font-size: 14px; font-weight: 600; margin-bottom: 6px; color: var(--color-ink-charcoal); }
        
        .input-container { position: relative; display: flex; align-items: center; }
        .form-group input[type="email"],
        .form-group input[type="password"],
        .form-group input[type="text"] {
            width: 100%;
            padding: 10px 14px;
            border: 1px solid var(--color-mist-border);
            border-radius: 4px;
            font-size: 14px;
            outline: none;
            background: var(--color-paper-white);
        }
        .form-group input:focus { border-color: var(--color-iris-bolt); }

        /* Botón de visibilidad de contraseña */
        .toggle-password {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            cursor: pointer;
            color: var(--color-slate-veil);
        }

        .form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 14px;
            margin-bottom: 24px;
        }

        .form-options a { color: var(--color-iris-bolt); text-decoration: none; }
        .form-options a:hover { text-decoration: underline; }

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
            box-shadow: var(--shadow-md);
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
        <h2>Iniciar sesión</h2>

        <?php if (!empty($error)): ?>
            <div class="alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="login.php" method="POST">
            <div class="form-group">
                <label for="email">Correo electrónico</label>
                <input type="email" id="email" name="email" required placeholder="tu@correo.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <div class="input-container">
                    <input type="password" id="password" name="password" required placeholder="••••••••">
                    <button type="button" class="toggle-password" onclick="togglePasswordVisibility()">
                        <svg id="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                    </button>
                </div>
            </div>

            <div class="form-options">
                <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                    <input type="checkbox" name="remember"> Recordarme
                </label>
                <a href="#">¿Olvidaste tu contraseña?</a>
            </div>

            <button type="submit" class="btn-submit">Iniciar sesión</button>
        </form>

        <div class="footer-link">
            ¿No tienes cuenta? <a href="registro.php">Regístrate</a>
        </div>
    </div>

    <script>
        function togglePasswordVisibility() {
            const passwordInput = document.getElementById('password');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
            } else {
                passwordInput.type = 'password';
            }
        }
    </script>
</body>
</html>