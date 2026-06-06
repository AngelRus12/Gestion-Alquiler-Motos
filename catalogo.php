<?php
/**
 * catalogo.php
 * Lista de motos disponibles para alquilar y filtro dinámico.
 * - Usa paginación y consultas preparadas para evitar inyecciones SQL.
 * - Mejora la experiencia de usuario con filtros por marca, modelo, tipo y rango de precio.
 */
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'loginbd.php';
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

mysqli_set_charset($conexion, "utf8");

// Función para detectar dispositivos móviles
function isMobile() {
    return preg_match("/(android|avantgo|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino)/i", $_SERVER["HTTP_USER_AGENT"]);
}

$is_mobile = isMobile();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="logo.png" type="image/png">
    <link rel="apple-touch-icon" href="logo.png">
    <title>Catálogo - ARUSLAT</title>
    <?php if ($is_mobile): ?>
        <link rel="stylesheet" href="estilos_mobile.css">
    <?php else: ?>
        <link rel="stylesheet" href="estilos.css">
    <?php endif; ?>
</head>
<body>
    <header class="navbar">
        <div class="container">
            <h1><a href="index.php">ARUSLAT</a></h1>
            <nav>
                <a href="index.php">Inicio</a>
                <a href="catalogo.php">Catálogo</a>
                <?php if (isset($_SESSION['usuario_id'])): ?>
                    <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                        <a href="admin_dashboard.php" class="nav-destacado">Panel Admin</a>
                    <?php endif; ?>
                    <a href="perfil_usuario.php">Mi Perfil</a>
                    <a href="logout.php">Cerrar Sesión</a>
                <?php else: ?>
                    <a href="login.php">Login</a>
                    <a href="registro.php">Registro</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <h2 class="titulo-seccion">Nuestro Catálogo de Motos</h2>

            <!-- Filtros de búsqueda avanzada (selects dinámicos y prepared statements) -->
            <?php
            // Obtener valores distintos para selects
            $marcas_res = mysqli_query($conexion, "SELECT DISTINCT marca FROM motos WHERE marca IS NOT NULL AND marca <> '' ORDER BY marca");
            $marcas = mysqli_fetch_all($marcas_res, MYSQLI_ASSOC);

            // Si hay marca seleccionada, limitar el listado de modelos a esa marca.
            $selected_marca = isset($_GET['marca']) ? trim($_GET['marca']) : '';
            $selected_modelo = isset($_GET['modelo']) ? trim($_GET['modelo']) : '';
            $selected_tipo = isset($_GET['tipo']) ? trim($_GET['tipo']) : '';

            if ($selected_marca !== '') {
                $stmt_modelos = mysqli_prepare($conexion, "SELECT DISTINCT modelo FROM motos WHERE marca = ? AND modelo IS NOT NULL AND modelo <> '' ORDER BY modelo");
                mysqli_stmt_bind_param($stmt_modelos, 's', $selected_marca);
                mysqli_stmt_execute($stmt_modelos);
                $modelos_res = mysqli_stmt_get_result($stmt_modelos);
                $modelos = mysqli_fetch_all($modelos_res, MYSQLI_ASSOC);
                mysqli_stmt_close($stmt_modelos);

                // Si el modelo seleccionado no pertenece a la marca actual, ignorarlo.
                if ($selected_modelo !== '') {
                    $stmt_valida_modelo = mysqli_prepare($conexion, "SELECT COUNT(*) AS total FROM motos WHERE marca = ? AND modelo = ?");
                    mysqli_stmt_bind_param($stmt_valida_modelo, 'ss', $selected_marca, $selected_modelo);
                    mysqli_stmt_execute($stmt_valida_modelo);
                    $valid_result = mysqli_stmt_get_result($stmt_valida_modelo);
                    $valid_row = mysqli_fetch_assoc($valid_result);
                    mysqli_stmt_close($stmt_valida_modelo);

                    if (intval($valid_row['total']) === 0) {
                        $selected_modelo = '';
                    }
                }
            } else {
                $modelos_res = mysqli_query($conexion, "SELECT DISTINCT modelo FROM motos WHERE modelo IS NOT NULL AND modelo <> '' ORDER BY modelo");
                $modelos = mysqli_fetch_all($modelos_res, MYSQLI_ASSOC);
            }

            $tipos_res = mysqli_query($conexion, "SELECT DISTINCT tipo FROM motos WHERE tipo IS NOT NULL AND tipo <> '' ORDER BY tipo");
            $tipos = mysqli_fetch_all($tipos_res, MYSQLI_ASSOC);

            // Mostrar formulario de filtros (selects dinámicos)
            ?>
            <form method="get" action="catalogo.php" class="formulario-filtros espaciado-arriba-20 catalogo-filtros-form">
                <div class="form-grid-3-col">
                    <div class="form-group">
                        <label>Marca</label>
                        <select name="marca" onchange="document.querySelector('select[name=\'modelo\']').value=''; this.form.submit();">
                            <option value="">-- Todas --</option>
                            <?php foreach ($marcas as $m): ?>
                                <option value="<?php echo htmlspecialchars($m['marca']); ?>" <?php if($selected_marca !== '' && $selected_marca == $m['marca']) echo 'selected'; ?>><?php echo htmlspecialchars($m['marca']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Modelo</label>
                        <select name="modelo">
                            <option value="">-- Todos --</option>
                            <?php foreach ($modelos as $mo): ?>
                                <option value="<?php echo htmlspecialchars($mo['modelo']); ?>" <?php if($selected_modelo !== '' && $selected_modelo == $mo['modelo']) echo 'selected'; ?>><?php echo htmlspecialchars($mo['modelo']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tipo</label>
                        <select name="tipo">
                            <option value="">-- Cualquiera --</option>
                            <?php foreach ($tipos as $t): ?>
                                <option value="<?php echo htmlspecialchars($t['tipo']); ?>" <?php if($selected_tipo !== '' && $selected_tipo == $t['tipo']) echo 'selected'; ?>><?php echo htmlspecialchars($t['tipo']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-grid-3-col espaciado-arriba-20">
                    <div class="form-group">
                        <label>Precio día (mín - máx)</label>
                        <div class="field-pair"><input type="number" name="precio_min" step="1" placeholder="0" value="<?php echo isset($_GET['precio_min'])?intval($_GET['precio_min']):''; ?>"><input type="number" name="precio_max" step="1" placeholder="999" value="<?php echo isset($_GET['precio_max'])?intval($_GET['precio_max']):''; ?>"></div>
                    </div>
                    <div class="form-group">
                        <label>Cilindrada (mín - máx)</label>
                        <div class="field-pair"><input type="number" name="cil_min" step="1" placeholder="125" value="<?php echo isset($_GET['cil_min'])?intval($_GET['cil_min']):''; ?>"><input type="number" name="cil_max" step="1" placeholder="1000" value="<?php echo isset($_GET['cil_max'])?intval($_GET['cil_max']):''; ?>"></div>
                    </div>
                    <div class="form-group">
                        <label>Año (mín - máx)</label>
                        <div class="field-pair"><input type="number" name="ano_min" step="1" placeholder="2000" value="<?php echo isset($_GET['ano_min'])?intval($_GET['ano_min']):''; ?>"><input type="number" name="ano_max" step="1" placeholder="2026" value="<?php echo isset($_GET['ano_max'])?intval($_GET['ano_max']):''; ?>"></div>
                    </div>
                </div>
                <div class="form-submit-group">
                    <button type="submit" class="btn">Aplicar filtros</button>
                    <a href="catalogo.php" class="btn boton-secundario ml-10">Limpiar</a>
                </div>
            </form>
            <?php

            // Parámetros de paginación
            $per_page = 12;
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
            $offset = ($page - 1) * $per_page;

            // Construir WHERE y parámetros para prepared statements con los valores ya validados.
            $where_clauses = array("disponible = 1");
            $params = array();
            $types = '';

            if ($selected_marca !== '') { $where_clauses[] = "marca = ?"; $types .= 's'; $params[] = $selected_marca; }
            if ($selected_modelo !== '') { $where_clauses[] = "modelo = ?"; $types .= 's'; $params[] = $selected_modelo; }
            if ($selected_tipo !== '') { $where_clauses[] = "tipo = ?"; $types .= 's'; $params[] = $selected_tipo; }
            if (isset($_GET['precio_min']) && $_GET['precio_min'] !== '') { $where_clauses[] = "precio_dia >= ?"; $types .= 'i'; $params[] = intval($_GET['precio_min']); }
            if (isset($_GET['precio_max']) && $_GET['precio_max'] !== '') { $where_clauses[] = "precio_dia <= ?"; $types .= 'i'; $params[] = intval($_GET['precio_max']); }
            if (isset($_GET['cil_min']) && $_GET['cil_min'] !== '') { $where_clauses[] = "cilindrada >= ?"; $types .= 'i'; $params[] = intval($_GET['cil_min']); }
            if (isset($_GET['cil_max']) && $_GET['cil_max'] !== '') { $where_clauses[] = "cilindrada <= ?"; $types .= 'i'; $params[] = intval($_GET['cil_max']); }
            if (isset($_GET['ano_min']) && $_GET['ano_min'] !== '') { $where_clauses[] = "ano >= ?"; $types .= 'i'; $params[] = intval($_GET['ano_min']); }
            if (isset($_GET['ano_max']) && $_GET['ano_max'] !== '') { $where_clauses[] = "ano <= ?"; $types .= 'i'; $params[] = intval($_GET['ano_max']); }

            $where_sql = '';
            if (count($where_clauses) > 0) { $where_sql = ' WHERE ' . implode(' AND ', $where_clauses); }

            // Contar resultados totales
            $count_sql = "SELECT COUNT(*) as total FROM motos " . $where_sql;
            $count_stmt = mysqli_prepare($conexion, $count_sql);
            if ($types !== '') {
                // bind params by reference
                $bind_names = array();
                $bind_names[] = $types;
                for ($i=0; $i<count($params); $i++) { $bind_names[] = & $params[$i]; }
                call_user_func_array(array($count_stmt, 'bind_param'), $bind_names);
            }
            mysqli_stmt_execute($count_stmt);
            $count_res = mysqli_stmt_get_result($count_stmt);
            $count_row = mysqli_fetch_assoc($count_res);
            $total = intval($count_row['total']);
            mysqli_stmt_close($count_stmt);

            $total_pages = max(1, ceil($total / $per_page));

            // Consulta principal con LIMIT
            $sql = "SELECT * FROM motos " . $where_sql . " ORDER BY marca, modelo LIMIT ? OFFSET ?";
            $stmt = mysqli_prepare($conexion, $sql);
            // bind params including pagination integers
            $params_with_limit = $params; // Los parámetros de los filtros
            $types_with_limit = $types . 'ii';
            $params_with_limit[] = $per_page;
            $params_with_limit[] = $offset;
            if ($types_with_limit !== '') {
                $bind_names = array();
                $bind_names[] = $types_with_limit;
                for ($i=0; $i<count($params_with_limit); $i++) { $bind_names[] = & $params_with_limit[$i]; }
                call_user_func_array(array($stmt, 'bind_param'), $bind_names);
            }
            mysqli_stmt_execute($stmt);
            $resultado = mysqli_stmt_get_result($stmt);
            
            // Mostrar resultados
            echo '<div class="grid">';
            $filas_mostradas = 0;
            while ($fila = mysqli_fetch_assoc($resultado)) {
                $filas_mostradas++;
                    // Estructura de la tarjeta actualizada para coincidir con .card y .card-body
                    echo '<div class="card">';
                    if (!empty($fila['imagen'])) {
                        echo '<img src="data:image/jpeg;base64,' . base64_encode($fila['imagen']) . '" alt="Moto">';
                    } else {
                        echo '<img src="imgs/default.jpg" alt="Moto">';
                    }
                    echo '<div class="card-body">'; // Inicio del cuerpo de la tarjeta
                    echo '<h3>' . htmlspecialchars($fila['marca'] . ' ' . $fila['modelo']) . '</h3>'; // Título
                    echo '<p class="card-descripcion">' . htmlspecialchars($fila['descripcion']) . '</p>'; // Descripción
                    echo '<p class="precio">' . htmlspecialchars($fila['precio_dia']) . ' €/día</p>';

                    echo '<a href="detalle_moto?id=' . intval($fila['id']) . '" class="btn">Reservar Ahora</a>';
                    echo '</div></div>';
                }
            if ($filas_mostradas === 0) {
                echo '<div class="sin-resultados"><p>No se encontraron motos con los filtros seleccionados.</p></div>';
            }
            echo '</div>';
            mysqli_close($conexion);
            ?>

            <!-- Paginación -->
            <?php if ($total > $per_page): ?>
            <div class="container espaciado-arriba-20 text-center">
                <nav class="paginacion" aria-label="Paginación">
                    <?php if ($page > 1): ?>
                        <a class="btn boton-pequeño" href="?<?php
                            $qs = $_GET; $qs['page'] = $page-1; echo http_build_query($qs);
                        ?>">&laquo; Anterior</a>
                    <?php endif; ?>
                    <span class="page-label">Página <?php echo $page; ?> / <?php echo $total_pages; ?></span>
                    <?php if ($page < $total_pages): ?>
                        <a class="btn boton-pequeño" href="?<?php
                            $qs = $_GET; $qs['page'] = $page+1; echo http_build_query($qs);
                        ?>">Siguiente &raquo;</a>
                    <?php endif; ?>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </main>
    <footer>
        <p>© 2026 ARUSLAT - Alquiler de Motos</p>
        <p>Proyecto TFG - Ángel Rus Latorre - ASIR</p>
    </footer>

    <?php if (isset($_SESSION['usuario_id'])): ?>
    <script src="logout_session.js"></script>
    <?php endif; ?>

</body>
</html>