<?php
require_once 'config/conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit;
}

$id_cliente = (int)$_SESSION['id_usuario'];
$mensaje = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancelar_reserva'])) {
    if (!verificar_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido.";
    } else {
        $id_reserva = filter_input(INPUT_POST, 'id_reserva', FILTER_VALIDATE_INT);

        try {
            $pdo->beginTransaction();

            $stmtCheck = $pdo->prepare("SELECT id_reserva, id_horario, estado FROM reservas WHERE id_reserva = ? AND id_cliente = ? FOR UPDATE");
            $stmtCheck->execute([$id_reserva, $id_cliente]);
            $reserva = $stmtCheck->fetch();

            if (!$reserva) {
                throw new Exception("No se encontró la cita o no tienes permiso para cancelarla.");
            }
            if ($reserva['estado'] === 'CANCELADA') {
                throw new Exception("Esta cita ya fue cancelada previamente.");
            }

            $stmtUpdRes = $pdo->prepare("UPDATE reservas SET estado = 'CANCELADA' WHERE id_reserva = ?");
            $stmtUpdRes->execute([$id_reserva]);

            $stmtUpdHor = $pdo->prepare("UPDATE horarios SET cupos_disponibles = cupos_disponibles + 1 WHERE id_horario = ?");
            $stmtUpdHor->execute([$reserva['id_horario']]);

            $pdo->commit();
            $mensaje = "Cita cancelada correctamente. El cupo ha sido liberado.";
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e->getMessage();
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enviar_resena'])) {
    if (!verificar_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido.";
    } else {
        $id_reserva = filter_input(INPUT_POST, 'id_reserva', FILTER_VALIDATE_INT);
        $calificacion = filter_input(INPUT_POST, 'calificacion', FILTER_VALIDATE_INT);
        $comentario = trim($_POST['comentario'] ?? '');

        if (!$id_reserva || $calificacion < 1 || $calificacion > 5) {
            $error = "Selecciona una calificación válida entre 1 y 5 estrellas.";
        } elseif (strlen($comentario) < 5) {
            $error = "Escribe un comentario de al menos 5 caracteres.";
        } else {
            $stmtProp = $pdo->prepare("SELECT id_reserva FROM reservas WHERE id_reserva = ? AND id_cliente = ? AND estado != 'CANCELADA'");
            $stmtProp->execute([$id_reserva, $id_cliente]);

            if (!$stmtProp->fetch()) {
                $error = "No puedes calificar esta cita.";
            } else {
                $stmtExist = $pdo->prepare("SELECT id_resena FROM resenas WHERE id_reserva = ?");
                $stmtExist->execute([$id_reserva]);

                if ($stmtExist->fetch()) {
                    $error = "Ya enviaste una reseña para esta cita.";
                } else {
                    $stmtIns = $pdo->prepare("INSERT INTO resenas (id_reserva, id_cliente, calificacion, comentario) VALUES (?, ?, ?, ?)");
$stmtIns->execute([$id_reserva, $id_cliente, $calificacion, $comentario]);

                    $mensaje = "¡Gracias por compartir tu opinión sobre la clínica!";
                }
            }
        }
    }
}

$sql = "SELECT r.id_reserva, r.estado, r.notas, r.fecha_registro,
               s.nombre AS servicio_nombre, s.precio,
               h.fecha, h.hora_inicio, h.hora_fin,
               res.id_resena
        FROM reservas r
        INNER JOIN servicios s ON r.id_servicio = s.id_servicio
        INNER JOIN horarios h ON r.id_horario = h.id_horario
        LEFT JOIN resenas res ON r.id_reserva = res.id_reserva
        WHERE r.id_cliente = ?
        ORDER BY h.fecha DESC, h.hora_inicio DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$id_cliente]);
$mis_citas = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Citas - Clínica Dental</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>

    <header>
        <nav>
            <h1>Clínica Dental Sonrisa Sana</h1>
            <ul>
                <li><a href="index.php">Tratamientos</a></li>
                <li><a href="mis_reservas.php">Mis Citas</a></li>
                <li><a href="logout.php" class="btn-nav">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <h2>Mis Citas Odontológicas</h2>

        <?php if ($mensaje): ?>
            <div class="alerta-exito" style="margin-top: 1rem;"><p><?= e($mensaje) ?></p></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alerta-error" style="margin-top: 1rem;"><p><?= e($error) ?></p></div>
        <?php endif; ?>

        <?php if (empty($mis_citas)): ?>
            <p style="margin-top: 1rem;">Aún no tienes citas agendadas. <a href="index.php">Explora nuestros tratamientos</a>.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>Tratamiento</th>
                        <th>Fecha y Hora</th>
                        <th>Costo</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($mis_citas as $cita): ?>
                        <tr>
                            <td>
                                <strong><?= e($cita['servicio_nombre']) ?></strong>
                                <?php if (!empty($cita['notas'])): ?>
                                    <br><small style="color: var(--text-muted);">Nota: <?= e($cita['notas']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= e($cita['fecha']) ?><br>
                                <small><?= e(substr($cita['hora_inicio'], 0, 5)) ?> - <?= e(substr($cita['hora_fin'], 0, 5)) ?></small>
                            </td>
                            <td>$<?= number_format((float)$cita['precio'], 2) ?></td>
                            <td><strong><?= e($cita['estado']) ?></strong></td>
                            <td>
                                <?php if ($cita['estado'] !== 'CANCELADA'): ?>
                                    <form method="POST" action="mis_reservas.php" style="margin-bottom: 0.5rem;">
                                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="id_reserva" value="<?= (int)$cita['id_reserva'] ?>">
                                        <button type="submit" name="cancelar_reserva" style="background-color: #dc2626; padding: 0.4rem 0.8rem; font-size: 0.85rem;">
                                            Cancelar Cita
                                        </button>
                                    </form>

                                    <?php if (empty($cita['id_resena'])): ?>
                                        <form method="POST" action="mis_reservas.php" style="display: flex; flex-direction: column; gap: 0.3rem; margin-top: 0.5rem;">
                                            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                            <input type="hidden" name="id_reserva" value="<?= (int)$cita['id_reserva'] ?>">
                                            <select name="calificacion" required style="padding: 0.3rem;">
                                                <option value="5">★★★★★ (5)</option>
                                                <option value="4">★★★★☆ (4)</option>
                                                <option value="3">★★★☆☆ (3)</option>
                                                <option value="2">★★☆☆☆ (2)</option>
                                                <option value="1">★☆☆☆☆ (1)</option>
                                            </select>
                                            <input type="text" name="comentario" placeholder="Tu opinión del servicio..." required style="padding: 0.3rem;">
                                            <button type="submit" name="enviar_resena" style="background-color: var(--secondary); padding: 0.35rem 0.7rem; font-size: 0.85rem;">
                                                Calificar
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <small style="color: var(--success-text);">✓ Reseña enviada</small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: var(--text-muted);">Sin acciones</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>

    <footer>
        <p>Clínica Dental Sonrisa Sana — Desarrollado por Edward Sanchez, Edgardo Cordoba, David Rios y Jean Llanten.</p>
    </footer>

</body>
</html>
