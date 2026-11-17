<?php
require_once 'config/conexion.php';

if (!isset($_SESSION['id_usuario']) || (int)$_SESSION['id_rol'] !== 1) {
    header("Location: login.php");
    exit;
}

$mensaje = '';
$error = '';

// 1. Crear nuevo tratamiento odontológico
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_servicio'])) {
    if (!verificar_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido.";
    } else {
        $id_categoria = filter_input(INPUT_POST, 'id_categoria', FILTER_VALIDATE_INT);
        $nombre = trim($_POST['nombre'] ?? '');
        $descripcion = trim($_POST['descripcion'] ?? '');
        $precio = filter_input(INPUT_POST, 'precio', FILTER_VALIDATE_FLOAT);
        $duracion = filter_input(INPUT_POST, 'duracion_minutos', FILTER_VALIDATE_INT);

        if (!$id_categoria || strlen($nombre) < 3 || $precio <= 0 || $duracion <= 0) {
            $error = "Completa todos los campos del tratamiento con valores válidos.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO servicios (id_categoria, nombre, descripcion, precio, duracion_minutos) VALUES (?, ?, ?, ?, ?)");
            if ($stmt->execute([$id_categoria, $nombre, $descripcion, $precio, $duracion])) {
                $mensaje = "Tratamiento odontológico '{$nombre}' agregado correctamente.";
            } else {
                $error = "No se pudo guardar el tratamiento.";
            }
        }
    }
}

// 2. Agregar nuevo horario con cupos disponibles
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['crear_horario'])) {
    if (!verificar_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido.";
    } else {
        $id_servicio = filter_input(INPUT_POST, 'id_servicio', FILTER_VALIDATE_INT);
        $fecha = $_POST['fecha'] ?? '';
        $hora_inicio = $_POST['hora_inicio'] ?? '';
        $hora_fin = $_POST['hora_fin'] ?? '';
        $cupos = filter_input(INPUT_POST, 'cupos_totales', FILTER_VALIDATE_INT);

        if (!$id_servicio || empty($fecha) || empty($hora_inicio) || empty($hora_fin) || $cupos < 1) {
            $error = "Verifica los datos del horario y asegúrate de asignar al menos 1 cupo.";
        } elseif ($hora_fin <= $hora_inicio) {
            $error = "La hora de finalización debe ser posterior a la hora de inicio.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO horarios (id_servicio, fecha, hora_inicio, hora_fin, cupos_totales, cupos_disponibles) VALUES (?, ?, ?, ?, ?, ?)");
            if ($stmt->execute([$id_servicio, $fecha, $hora_inicio, $hora_fin, $cupos, $cupos])) {
                $mensaje = "Horario creado con {$cupos} cupo(s) disponible(s).";
            } else {
                $error = "Error al registrar el horario.";
            }
        }
    }
}

// 3. Cambiar estado de una reserva
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cambiar_estado'])) {
    if (!verificar_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Token de seguridad inválido.";
    } else {
        $id_reserva = filter_input(INPUT_POST, 'id_reserva', FILTER_VALIDATE_INT);
        $nuevo_estado = $_POST['nuevo_estado'] ?? '';
        $estados_validos = ['PENDIENTE', 'CONFIRMADA', 'REALIZADA', 'CANCELADA'];

        if ($id_reserva && in_array($nuevo_estado, $estados_validos, true)) {
            try {
                $pdo->beginTransaction();

                $stmtRes = $pdo->prepare("SELECT id_horario, estado FROM reservas WHERE id_reserva = ? FOR UPDATE");
                $stmtRes->execute([$id_reserva]);
                $reservaActual = $stmtRes->fetch();

                if ($reservaActual) {
                    if ($nuevo_estado === 'CANCELADA' && $reservaActual['estado'] !== 'CANCELADA') {
                        $stmtCupo = $pdo->prepare("UPDATE horarios SET cupos_disponibles = cupos_disponibles + 1 WHERE id_horario = ?");
                        $stmtCupo->execute([$reservaActual['id_horario']]);
                    }
                    if ($nuevo_estado !== 'CANCELADA' && $reservaActual['estado'] === 'CANCELADA') {
                        $stmtCupo = $pdo->prepare("UPDATE horarios SET cupos_disponibles = GREATEST(0, cupos_disponibles - 1) WHERE id_horario = ?");
                        $stmtCupo->execute([$reservaActual['id_horario']]);
                    }

                    $stmtUpd = $pdo->prepare("UPDATE reservas SET estado = ? WHERE id_reserva = ?");
                    $stmtUpd->execute([$nuevo_estado, $id_reserva]);
                    $mensaje = "Estado de la cita #{$id_reserva} actualizado a '{$nuevo_estado}'.";
                }

                $pdo->commit();
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "No se pudo actualizar el estado de la cita.";
            }
        }
    }
}

$categorias = $pdo->query("SELECT * FROM categorias ORDER BY nombre ASC")->fetchAll();
$servicios = $pdo->query("SELECT id_servicio, nombre FROM servicios ORDER BY nombre ASC")->fetchAll();

$sqlReservas = "SELECT r.id_reserva, r.estado, r.notas,
                       CONCAT(u.nombre, ' ', u.apellido) AS paciente, u.correo, u.telefono,
                       s.nombre AS tratamiento,
                       h.fecha, h.hora_inicio, h.hora_fin
                FROM reservas r
                INNER JOIN usuarios u ON r.id_cliente = u.id_usuario
                INNER JOIN servicios s ON r.id_servicio = s.id_servicio
                INNER JOIN horarios h ON r.id_horario = h.id_horario
                ORDER BY h.fecha DESC, h.hora_inicio ASC";
$todas_reservas = $pdo->query($sqlReservas)->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - Clínica Dental</title>
    <link rel="stylesheet" href="css/estilos.css">
</head>
<body>

    <header>
        <nav>
            <h1>Panel Admin — Clínica Dental</h1>
            <ul>
                <li><a href="index.php">Ver Sitio Público</a></li>
                <li><a href="admin_panel.php">Administración</a></li>
                <li><a href="logout.php" class="btn-nav">Cerrar Sesión</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <h2>Gestión de la Clínica Odontológica</h2>

        <?php if ($mensaje): ?>
            <div class="alerta-exito" style="margin-top: 1rem;"><p><?= e($mensaje) ?></p></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alerta-error" style="margin-top: 1rem;"><p><?= e($error) ?></p></div>
        <?php endif; ?>

        <div class="grid-servicios" style="margin-top: 1.5rem; margin-bottom: 2.5rem;">
            <section class="contenedor-formulario" style="margin: 0; max-width: 100%;">
                <h3>Nuevo Tratamiento</h3>
                <form method="POST" action="admin_panel.php">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

                    <label for="id_categoria">Especialidad</label>
                    <select name="id_categoria" id="id_categoria" required>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?= (int)$cat['id_categoria'] ?>"><?= e($cat['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="nombre">Nombre del tratamiento</label>
                    <input type="text" name="nombre" id="nombre" placeholder="Ej. Endodoncia Molar" required>

                    <label for="descripcion">Descripción</label>
                    <textarea name="descripcion" id="descripcion" rows="2" required></textarea>

                    <label for="precio">Precio ($)</label>
                    <input type="number" step="0.01" min="1" name="precio" id="precio" required>

                    <label for="duracion_minutos">Duración (minutos)</label>
                    <input type="number" min="10" step="5" name="duracion_minutos" id="duracion_minutos" value="30" required>

                    <button type="submit" name="crear_servicio">Guardar Tratamiento</button>
                </form>
            </section>
            <section class="contenedor-formulario" style="margin: 0; max-width: 100%;">
                <h3>Abrir Horario Disponible</h3>
                <form method="POST" action="admin_panel.php">
                    <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

                    <label for="id_servicio">Tratamiento</label>
                    <select name="id_servicio" id="id_servicio" required>
                        <?php foreach ($servicios as $serv): ?>
                            <option value="<?= (int)$serv['id_servicio'] ?>"><?= e($serv['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="fecha">Fecha de atención</label>
                    <input type="date" name="fecha" id="fecha" min="<?= date('Y-m-d') ?>" required>

                    <label for="hora_inicio">Hora de inicio</label>
                    <input type="time" name="hora_inicio" id="hora_inicio" required>

                    <label for="hora_fin">Hora de fin</label>
                    <input type="time" name="hora_fin" id="hora_fin" required>

                    <label for="cupos_totales">Cupos disponibles</label>
                    <input type="number" min="1" max="20" name="cupos_totales" id="cupos_totales" value="1" required>

                    <button type="submit" name="crear_horario">Publicar Horario</button>
                </form>
            </section>
        </div>

        <section>
            <h3>Agenda General de Pacientes (<?= count($todas_reservas) ?>)</h3>
            <?php if (empty($todas_reservas)): ?>
                <p style="margin-top: 0.5rem;">Aún no hay citas registradas en el sistema.</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Paciente</th>
                            <th>Tratamiento</th>
                            <th>Fecha y Hora</th>
                            <th>Estado Actual</th>
                            <th>Actualizar Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($todas_reservas as $res): ?>
                            <tr>
                                <td>#<?= (int)$res['id_reserva'] ?></td>
                                <td>
                                    <strong><?= e($res['paciente']) ?></strong><br>
                                    <small><?= e($res['correo']) ?> | <?= e($res['telefono']) ?></small>
                                </td>
                                <td><?= e($res['tratamiento']) ?></td>
                                <td>
                                    <?= e($res['fecha']) ?><br>
                                    <small><?= e(substr($res['hora_inicio'], 0, 5)) ?> - <?= e(substr($res['hora_fin'], 0, 5)) ?></small>
                                </td>
                                <td><strong><?= e($res['estado']) ?></strong></td>
                                <td>
                                    <form method="POST" action="admin_panel.php" style="display: flex; gap: 0.4rem;">
                                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="id_reserva" value="<?= (int)$res['id_reserva'] ?>">
                                        <select name="nuevo_estado" style="padding: 0.35rem;">
                                            <option value="PENDIENTE" <?= $res['estado'] === 'PENDIENTE' ? 'selected' : '' ?>>PENDIENTE</option>
                                            <option value="CONFIRMADA" <?= $res['estado'] === 'CONFIRMADA' ? 'selected' : '' ?>>CONFIRMADA</option>
                                            <option value="REALIZADA" <?= $res['estado'] === 'REALIZADA' ? 'selected' : '' ?>>REALIZADA</option>
                                            <option value="CANCELADA" <?= $res['estado'] === 'CANCELADA' ? 'selected' : '' ?>>CANCELADA</option>
                                        </select>
                                        <button type="submit" name="cambiar_estado" style="padding: 0.35rem 0.7rem; font-size: 0.85rem;">Guardar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <p>Clínica Dental Sonrisa Sana — Desarrollado por Edward Sanchez, Edgardo Cordoba, David Rios y Jean Llanten.</p>
    </footer>

</body>
</html>
