<?php
require_once 'config/conexion.php';

$error = '';
$max_intentos = 3;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido. Recarga la página.";
    } else {
        $correo = trim($_POST['correo'] ?? '');
        $password = $_POST['password'] ?? '';

        $stmt = $pdo->prepare("SELECT id_usuario, id_rol, nombre, apellido, contrasena_hash, estado, intentos_fallidos, bloqueado_hasta 
                               FROM usuarios WHERE correo = ?");
        $stmt->execute([$correo]);
        $usuario = $stmt->fetch();

        if (!$usuario) {
            $error = "Correo o contraseña incorrectos.";
        } elseif ($usuario['estado'] !== 'ACTIVO') {
            $error = "Tu cuenta se encuentra inactiva. Contacta al administrador.";
        } elseif (!empty($usuario['bloqueado_hasta']) && strtotime($usuario['bloqueado_hasta']) > time()) {
            $restante = ceil((strtotime($usuario['bloqueado_hasta']) - time()) / 60);
            $error = "Cuenta bloqueada temporalmente por intentos fallidos. Intenta en {$restante} minuto(s).";
        } elseif (password_verify($password, $usuario['contrasena_hash'])) {
            // Limpiar intentos fallidos y desbloquear en BD
            $upd = $pdo->prepare("UPDATE usuarios SET intentos_fallidos = 0, bloqueado_hasta = NULL WHERE id_usuario = ?");
            $upd->execute([$usuario['id_usuario']]);

            session_regenerate_id(true);
            $_SESSION['id_usuario'] = $usuario['id_usuario'];
            $_SESSION['id_rol'] = $usuario['id_rol'];
            $_SESSION['nombre'] = $usuario['nombre'] . ' ' . $usuario['apellido'];

            if ((int)$usuario['id_rol'] === 1) {
                header("Location: admin_panel.php");
            } else {
                header("Location: index.php");
            }
            exit;
        } else {
            // Incrementar intentos fallidos en BD
            $nuevos_intentos = (int)$usuario['intentos_fallidos'] + 1;

            if ($nuevos_intentos >= $max_intentos) {
                $bloqueo = date('Y-m-d H:i:s', time() + 300); // Bloqueo por 5 minutos
                $upd = $pdo->prepare("UPDATE usuarios SET intentos_fallidos = ?, bloqueado_hasta = ? WHERE id_usuario = ?");
                $upd->execute([$nuevos_intentos, $bloqueo, $usuario['id_usuario']]);
                $error = "Has superado los 3 intentos permitidos. Cuenta bloqueada por 5 minutos.";
            } else {
                $upd = $pdo->prepare("UPDATE usuarios SET intentos_fallidos = ? WHERE id_usuario = ?");
                $upd->execute([$nuevos_intentos, $usuario['id_usuario']]);
                $quedan = $max_intentos - $nuevos_intentos;
                $error = "Correo o contraseña incorrectos. Te quedan {$quedan} intento(s).";
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
    <title>Iniciar Sesión - Clínica Odontológica</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
    <header>
        <nav>
            <h1>Clínica Dental Sonrisa Sana</h1>
            <ul>
                <li><a href="index.php">Inicio</a></li>
                <li><a href="registro.php">Registrarse</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <section class="contenedor-formulario">
            <h2>Iniciar Sesión</h2>

            <?php if ($error): ?>
                <div class="alerta-error">
                    <p><?= e($error) ?></p>
                </div>
            <?php endif; ?>

            <form method="POST" action="login.php">
                <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

                <label for="correo">Correo electrónico</label>
                <input type="email" id="correo" name="correo" required>

                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>

                <button type="submit">Entrar</button>
            </form>
            <p>¿Aún no tienes cuenta? <a href="registro.php">Regístrate aquí</a>.</p>
        </section>
    </main>

    <footer>
        <p>Desarrollado por Edward Sanchez, Edgardo Cordoba, David Rios y Jean Llanten.</p>
    </footer>
</body>
</html>
