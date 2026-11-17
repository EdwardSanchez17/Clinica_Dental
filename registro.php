<?php
require_once 'config/conexion.php';

$errores = [];
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verificar_csrf($_POST['csrf_token'] ?? '')) {
        $errores[] = "Error de seguridad (token inválido). Recarga la página.";
    }

    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmar = $_POST['confirmar_password'] ?? '';

    if (strlen($nombre) < 2) {
        $errores[] = "El nombre debe tener al menos 2 caracteres.";
    }
    if (strlen($apellido) < 2) {
        $errores[] = "El apellido debe tener al menos 2 caracteres.";
    }
    if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $errores[] = "Ingresa un correo electrónico válido.";
    }
    if (strlen($password) < 8) {
        $errores[] = "La contraseña debe tener mínimo 8 caracteres.";
    }
    if ($password !== $confirmar) {
        $errores[] = "Las contraseñas no coinciden.";
    }

    if (empty($errores)) {
        $stmt = $pdo->prepare("SELECT id_usuario FROM usuarios WHERE correo = ?");
        $stmt->execute([$correo]);

        if ($stmt->fetch()) {
            $errores[] = "Este correo ya está registrado en la clínica.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $id_rol_cliente = 2; // Rol Usuario normal

            $sql = "INSERT INTO usuarios (id_rol, nombre, apellido, correo, telefono, contrasena_hash) 
                    VALUES (?, ?, ?, ?, ?, ?)";
            $insert = $pdo->prepare($sql);
            
            if ($insert->execute([$id_rol_cliente, $nombre, $apellido, $correo, $telefono, $hash])) {
                $exito = "Registro exitoso. Ya puedes iniciar sesión para agendar tu cita dental.";
            } else {
                $errores[] = "Ocurrió un error al crear la cuenta. Intenta de nuevo.";
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
    <title>Registro de Pacientes - Clínica Odontológica</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>
    <header>
        <nav>
            <h1>Clínica Dental Sonrisa Sana</h1>
            <ul>
                <li><a href="index.php">Inicio</a></li>
                <li><a href="login.php">Iniciar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <section class="contenedor-formulario">
            <h2>Crear Cuenta de Paciente</h2>

            <?php if (!empty($errores)): ?>
                <div class="alerta-error">
                    <ul>
                        <?php foreach ($errores as $error): ?>
                            <li><?= e($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($exito): ?>
                <div class="alerta-exito">
                    <p><?= e($exito) ?></p>
                    <a href="login.php">Ir a Iniciar Sesión</a>
                </div>
            <?php else: ?>
                <form method="POST" action="registro.php">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

                    <label for="nombre">Nombre</label>
                    <input type="text" id="nombre" name="nombre" value="<?= e($_POST['nombre'] ?? '') ?>" required>

                    <label for="apellido">Apellido</label>
                    <input type="text" id="apellido" name="apellido" value="<?= e($_POST['apellido'] ?? '') ?>" required>

                    <label for="correo">Correo electrónico</label>
                    <input type="email" id="correo" name="correo" value="<?= e($_POST['correo'] ?? '') ?>" required>

                    <label for="telefono">Teléfono</label>
                    <input type="tel" id="telefono" name="telefono" value="<?= e($_POST['telefono'] ?? '') ?>" required>

                    <label for="password">Contraseña (mínimo 8 caracteres)</label>
                    <input type="password" id="password" name="password" required>

                    <label for="confirmar_password">Confirmar contraseña</label>
                    <input type="password" id="confirmar_password" name="confirmar_password" required>

                    <button type="submit">Registrarme</button>
                </form>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <p>Desarrollado por Edward Sanchez, Edgardo Cordoba, David Rios y Jean Llanten.</p>
    </footer>
</body>
</html>
