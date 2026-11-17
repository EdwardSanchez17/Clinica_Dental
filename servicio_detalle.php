<?php
require_once 'config/conexion.php';

$id_servicio = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id_servicio) {
    header("Location: index.php");
    exit;
}

$error = '';
$exito = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reservar'])) {
    if (!isset($_SESSION['id_usuario'])) {
        header("Location: login.php");
        exit;
    }

    if (!verificar_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido. Recarga la página.";
    } else {
        $id_horario = filter_input(INPUT_POST, 'id_horario', FILTER_VALIDATE_INT);
        $notas = trim($_POST['notas'] ?? '');
        $id_cliente = (int)$_SESSION['id_usuario'];

        if (!$id_horario) {
            $error = "Selecciona un horario válido.";
        } else {
            try {
                $pdo->beginTransaction();

                $stmtHor = $pdo->prepare("SELECT id_horario, cupos_disponibles FROM horarios WHERE id_horario = ? AND id_servicio = ? FOR UPDATE");
                $stmtHor->execute([$id_horario, $id_servicio]);
                $horario = $stmtHor->fetch();

                if (!$horario || (int)$horario['cupos_disponibles'] <= 0) {
                    throw new Exception("Lo sentimos, este horario ya no tiene cupos disponibles.");
                }

                $stmtDup = $pdo->prepare("SELECT id_reserva FROM reservas WHERE id_cliente = ? AND id_horario = ? AND estado != 'CANCELADA'");
                $stmtDup->execute([$id_cliente, $id_horario]);
                if ($stmtDup->fetch()) {
                    throw new Exception("Ya tienes una cita agendada en este mismo horario.");
                }

                $stmtIns = $pdo->prepare("INSERT INTO reservas (id_cliente, id_servicio, id_horario, estado, notas) VALUES (?, ?, ?, 'CONFIRMADA', ?)");
                $stmtIns->execute([$id_cliente, $id_servicio, $id_horario, $notas]);

                $stmtUpd = $pdo->prepare("UPDATE horarios SET cupos_disponibles = cupos_disponibles - 1 WHERE id_horario = ?");
                $stmtUpd->execute([$id_horario]);

                $pdo->commit();
                $exito = "¡Cita odontológica confirmada con éxito! Puedes gestionarla en 'Mis Citas'.";
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = $e->getMessage();
            }
        }
    }
}

$stmtServ = $pdo->prepare("SELECT s.*, c.nombre AS categoria_nombre 
                           FROM servicios s 
                           INNER JOIN categorias c ON s.id_categoria = c.id_categoria 
                           WHERE s.id_servicio = ?");
$stmtServ->execute([$id_servicio]);
$servicio = $stmtServ->fetch();

if (!$servicio) {
    header("Location: index.php");
    exit;
}

$stmtHorarios = $pdo->prepare("SELECT * FROM horarios 
                               WHERE id_servicio = ? AND cupos_disponibles > 0 
                               ORDER BY fecha ASC, hora_inicio ASC");
$stmtHorarios->execute([$id_servicio]);
$horarios = $stmtHorarios->fetchAll();

$stmtResenas = $pdo->prepare("SELECT r.calificacion, r.comentario, r.fecha_resena, CONCAT(u.nombre, ' ', u.apellido) AS paciente
                              FROM resenas r
                              INNER JOIN reservas res ON r.id_reserva = res.id_reserva
                              INNER JOIN usuarios u ON res.id_cliente = u.id_usuario
                              WHERE res.id_servicio = ?
                              ORDER BY r.fecha_resena DESC");
$stmtResenas->execute([$id_servicio]);
$resenas = $stmtResenas->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($servicio['nombre']) ?> - Clínica Dental</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>

    <header>
        <nav>
            <h1>Clínica Dental Sonrisa Sana</h1>
            <ul>
                <li><a href="index.php">Tratamientos</a></li>
                <?php if (isset($_SESSION['id_usuario'])): ?>
                    <?php if ((int)$_SESSION['id_rol'] === 1): ?>
                        <li><a href="admin_panel.php">Panel Admin</a></li>
                    <?php else: ?>
                        <li><a href="mis_reservas.php">Mis Citas</a></li>
                    <?php endif; ?>
                    <li><a href="logout.php" class="btn-nav">Cerrar Sesión</a></li>
                <?php else: ?>
                    <li><a href="login.php">Iniciar Sesión</a></li>
                    <li><a href="registro.php" class="btn-nav">Registrarse</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main>
        <section class="tarjeta-servicio" style="margin-bottom: 2rem;">
            <span class="badge-categoria"><?= e($servicio['categoria_nombre']) ?></span>
            <h2><?= e($servicio['nombre']) ?></h2>
            <p><?= e($servicio['descripcion']) ?></p>
            <div class="detalles-precio">
                <span class="precio">Costo: $<?= number_format((float)$servicio['precio'], 2) ?></span>
                <span>Duración estimada: <?= (int)$servicio['duracion_minutos'] ?> minutos</span>
            </div>
        </section>

        <section class="contenedor-formulario" style="max-width: 650px; margin-bottom: 2.5rem;">
            <h2>Agendar Cita</h2>

            <?php if ($error): ?>
                <div class="alerta-error"><p><?= e($error) ?></p></div>
            <?php endif; ?>

            <?php if ($exito): ?>
                <div class="alerta-exito">
                    <p><?= e($exito) ?></p>
                    <a href="mis_reservas.php">Ver mis citas agendadas</a>
                </div>
            <?php endif; ?>

            <?php if (!isset($_SESSION['id_usuario'])): ?>
                <p>Para reservar este tratamiento debes <a href="login.php">iniciar sesión</a> o <a href="registro.php">crear una cuenta</a>.</p>
            <?php elseif (empty($horarios)): ?>
                <div class="alerta-error">
                    <p>No hay horarios disponibles por el momento para este tratamiento.</p>
                </div>
            <?php else: ?>
                <form method="POST" action="servicio_detalle.php?id=<?= (int)$id_servicio ?>">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

                    <label for="id_horario">Selecciona fecha y hora disponible</label>
                    <select name="id_horario" id="id_horario" required>
                        <?php foreach ($horarios as $h): ?>
                            <option value="<?= (int)$h['id_horario'] ?>">
                                Fecha: <?= e($h['fecha']) ?> | Horario: <?= e(substr($h['hora_inicio'], 0, 5)) ?> - <?= e(substr($h['hora_fin'], 0, 5)) ?> (Cupos: <?= (int)$h['cupos_disponibles'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="notas">Motivo de consulta o alergias (opcional)</label>
                    <textarea name="notas" id="notas" rows="3" placeholder="Ej. Sensibilidad dental o primera revisión..."></textarea>

                    <button type="submit" name="reservar">Confirmar Cita Odontológica</button>
                </form>
            <?php endif; ?>
        </section>

        <section>
            <h3>Opiniones de Pacientes (<?= count($resenas) ?>)</h3>
            <?php if (empty($resenas)): ?>
                <p style="color: var(--text-muted); margin-top: 0.5rem;">Aún no hay reseñas para este tratamiento.</p>
            <?php else: ?>
                <div class="grid-servicios" style="margin-top: 1rem;">
                    <?php foreach ($resenas as $res): ?>
                        <article class="tarjeta-servicio">
                            <div>
                                <strong><?= e($res['paciente']) ?></strong>
                                <span style="color: #d97706; font-weight: bold; margin-left: 0.5rem;">
                                    <?= str_repeat('★', (int)$res['calificacion']) ?><?= str_repeat('☆', 5 - (int)$res['calificacion']) ?>
                                </span>
                                <p style="margin-top: 0.5rem;"><?= e($res['comentario']) ?></p>
                            </div>
                            <small style="color: var(--text-muted);"><?= e($res['fecha_resena']) ?></small>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <p>Clínica Dental Sonrisa Sana — Desarrollado por Edward Sanchez, Edgardo Cordoba, David Rios y Jean Llanten.</p>
    </footer>

</body>
</html>
