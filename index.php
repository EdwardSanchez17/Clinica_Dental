<?php
require_once 'config/conexion.php';

// 1. Obtener todas las categorías para el filtro desplegable
$stmtCat = $pdo->query("SELECT id_categoria, nombre FROM categorias ORDER BY nombre ASC");
$categorias = $stmtCat->fetchAll();

// 2. Parámetros de búsqueda, filtro y paginación
$busqueda = trim($_GET['buscar'] ?? '');
$categoria_id = filter_input(INPUT_GET, 'categoria', FILTER_VALIDATE_INT) ?: 0;
$pagina = filter_input(INPUT_GET, 'pagina', FILTER_VALIDATE_INT) ?: 1;
if ($pagina < 1) $pagina = 1;

$por_pagina = 4; // Mostramos 4 por página para visualizar la paginación con los 6 servicios
$offset = ($pagina - 1) * $por_pagina;

// 3. Construir condiciones WHERE dinámicas y seguras
$condiciones = [];
$parametros = [];

if ($busqueda !== '') {
    $condiciones[] = "(s.nombre LIKE ? OR s.descripcion LIKE ?)";
    $parametros[] = "%{$busqueda}%";
    $parametros[] = "%{$busqueda}%";
}

if ($categoria_id > 0) {
    $condiciones[] = "s.id_categoria = ?";
    $parametros[] = $categoria_id;
}

$whereSQL = !empty($condiciones) ? "WHERE " . implode(" AND ", $condiciones) : "";

// 4. Contar total de resultados para calcular páginas
$sqlTotal = "SELECT COUNT(*) FROM servicios s {$whereSQL}";
$stmtTotal = $pdo->prepare($sqlTotal);
$stmtTotal->execute($parametros);
$total_registros = (int)$stmtTotal->fetchColumn();
$total_paginas = max(1, (int)ceil($total_registros / $por_pagina));

// 5. Consultar servicios de la página actual
$sqlServicios = "SELECT s.id_servicio, s.nombre, s.descripcion, s.precio, s.duracion_minutos, c.nombre AS categoria_nombre
                 FROM servicios s
                 INNER JOIN categorias c ON s.id_categoria = c.id_categoria
                 {$whereSQL}
                 ORDER BY s.id_servicio ASC
                 LIMIT {$por_pagina} OFFSET {$offset}";
$stmtServicios = $pdo->prepare($sqlServicios);
$stmtServicios->execute($parametros);
$servicios = $stmtServicios->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clínica Dental Sonrisa Sana - Catálogo de Tratamientos</title>
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
                    <li><span>Hola, <strong><?= e($_SESSION['nombre']) ?></strong></span></li>
                    <li><a href="logout.php" class="btn-nav">Cerrar Sesión</a></li>
                <?php else: ?>
                    <li><a href="login.php">Iniciar Sesión</a></li>
                    <li><a href="registro.php" class="btn-nav">Registrarse</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </header>

    <main>
        <section class="hero">
            <h2>Tu salud bucal en manos de especialistas</h2>
            <p>Explora nuestros tratamientos odontológicos, consulta horarios disponibles y agenda tu cita en línea.</p>
        </section>

        <!-- Barra de búsqueda y filtro por especialidad -->
        <section class="barra-filtros">
            <form method="GET" action="index.php">
                <input type="text" name="buscar" placeholder="Buscar tratamiento (ej. limpieza, brackets, blanqueamiento)..." value="<?= e($busqueda) ?>">

                <select name="categoria">
                    <option value="0">Todas las especialidades</option>
                    <?php foreach ($categorias as $cat): ?>
                        <option value="<?= (int)$cat['id_categoria'] ?>" <?= $categoria_id === (int)$cat['id_categoria'] ? 'selected' : '' ?>>
                            <?= e($cat['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit">Buscar</button>
                <a href="index.php" class="btn-limpiar">Limpiar</a>
            </form>
        </section>

        <!-- Listado de tratamientos -->
        <section>
            <?php if (empty($servicios)): ?>
                <div class="alerta-error">
                    <p>No se encontraron tratamientos odontológicos con los filtros seleccionados.</p>
                </div>
            <?php else: ?>
                <div class="grid-servicios">
                    <?php foreach ($servicios as $servicio): ?>
                        <article class="tarjeta-servicio">
                            <div>
                                <span class="badge-categoria"><?= e($servicio['categoria_nombre']) ?></span>
                                <h3><?= e($servicio['nombre']) ?></h3>
                                <p><?= e($servicio['descripcion']) ?></p>
                            </div>
                            <div>
                                <div class="detalles-precio">
                                    <span class="precio">$<?= number_format((float)$servicio['precio'], 2) ?></span>
                                    <span>Duración: <?= (int)$servicio['duracion_minutos'] ?> min</span>
                                </div>
                                <a href="servicio_detalle.php?id=<?= (int)$servicio['id_servicio'] ?>" class="btn-primario" style="width: 100%;">
                                    Ver horarios y reservar
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>

                <!-- Controles de Paginación -->
                <?php if ($total_paginas > 1): ?>
                    <div class="paginacion">
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <?php
                            $queryParams = http_build_query([
                                'buscar' => $busqueda,
                                'categoria' => $categoria_id,
                                'pagina' => $i
                            ]);
                            ?>
                            <a href="index.php?<?= e($queryParams) ?>" class="<?= $i === $pagina ? 'activa' : '' ?>">
                                <?= $i ?>
                            </a>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>

    <footer>
        <p>Clínica Dental Sonrisa Sana — Desarrollado por Edward Sanchez, Edgardo Cordoba, David Rios y Jean Llanten.</p>
    </footer>

</body>
</html>
