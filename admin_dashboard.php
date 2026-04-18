<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'loginbd.php';

if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}

mysqli_set_charset($conexion, "utf8");

$mensaje_html = "";
if (isset($_GET['msg'])) {
    if ($_GET['msg'] == 'eliminado') {
        $mensaje_html = "<div class='alerta alerta-exito'>Registro eliminado correctamente.</div>";
    }
    if ($_GET['msg'] == 'actualizado') {
        $mensaje_html = "<div class='alerta alerta-exito'>Operación realizada con éxito.</div>";
    }
}
if (isset($_GET['error'])) {
    if ($_GET['error'] == 'autodelecion') {
        $mensaje_html = "<div class='alerta alerta-error'>No puedes eliminar tu propia cuenta.</div>";
    } else {
        $mensaje_html = "<div class='alerta alerta-error'>Hubo un error al procesar la solicitud.</div>";
    }
}

// Consultas base
$resultado_usuarios = mysqli_query($conexion, "SELECT id, nombre, apellidos, email, rol, estado FROM usuarios");
$resultado_motos = mysqli_query($conexion, "SELECT id, marca, modelo, precio_dia, disponible, tipo, imagen FROM motos");

// Nuevas consultas para el resumen de estadísticas
$total_users_res = mysqli_query($conexion, "SELECT COUNT(*) as total FROM usuarios");
$total_motos_res = mysqli_query($conexion, "SELECT COUNT(*) as total FROM motos");
$res_pendientes_res = mysqli_query($conexion, "SELECT COUNT(*) as total FROM alquileres WHERE estado = 'pendiente'");

$total_users = mysqli_fetch_assoc($total_users_res)['total'];
$total_motos = mysqli_fetch_assoc($total_motos_res)['total'];
$total_pendientes = mysqli_fetch_assoc($res_pendientes_res)['total'];

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
    <title>Panel Administrativo - ARUSLAT</title>
    <?php if ($is_mobile): ?>
        <link rel="stylesheet" href="estilos_mobile.css">
    <?php else: ?>
        <link rel="stylesheet" href="estilos.css">
    <?php endif; ?>
    <?php if (!$is_mobile): ?>
    <style>
        .info-alquilada {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 4px;
        }
    </style>
    <?php endif; ?>
</head>
<body>
    
    <header class="barra-navegacion">
        <div class="contenedor">
            <h1>ARUSLAT Admin</h1>
            <nav>
                <a href="index">Ir a la Web</a>
                <a href="perfil_usuario">Mi Perfil</a>
                <a href="logout">Cerrar Sesión</a>
            </nav>
        </div>
    </header>

    <main class="contenido-principal">
        <div class="contenedor">
            
            <div class="espaciado-arriba">
                <?php echo $mensaje_html; ?>
            </div>

            <h2 class="titulo-seccion" style="text-align: left; margin-bottom: 20px;">Resumen Overview</h2>

            <div class="admin-stats-grid">
                <div class="stat-card">
                    <div class="info">
                        <h4>Usuarios Totales</h4>
                        <p class="number"><?php echo $total_users; ?></p>
                    </div>
                    <div class="stat-icon">👤</div>
                </div>
                <div class="stat-card">
                    <div class="info">
                        <h4>Catálogo de Motos</h4>
                        <p class="number"><?php echo $total_motos; ?></p>
                    </div>
                    <div class="stat-icon">🏍️</div>
                </div>
                <div class="stat-card">
                    <div class="info">
                        <h4>Pagos Pendientes</h4>
                        <p class="number"><?php echo $total_pendientes; ?></p>
                    </div>
                    <div class="stat-icon">💰</div>
                </div>
            </div>

            <section>
                <div class="seccion-titulo-admin">
                    <h2>Gestión de Usuarios</h2>
                    <a href="nuevo_usuario.php" class="boton">+ Añadir Usuario</a>
                </div>
                
                <div class="contenedor-tabla">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre Completo</th>
                                <th>Email</th>
                                <th>Rol</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = mysqli_fetch_assoc($resultado_usuarios)) { ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo $user['nombre'] . " " . $user['apellidos']; ?></td>
                                <td><?php echo $user['email']; ?></td>
                                <td><span class="etiqueta etiqueta-azul"><?php echo strtoupper($user['rol']); ?></span></td>
                                <td>
                                    <?php 
                                    $clase_estado = 'etiqueta-error';
                                    if ($user['estado'] == 'activo') {
                                        $clase_estado = 'etiqueta-exito';
                                    }
                                    ?>
                                    <span class="etiqueta <?php echo $clase_estado; ?>">
                                        <?php echo strtoupper($user['estado']); ?>
                                    </span>
                                </td>
                                <td style="white-space: nowrap;">
                                    <a href="editar_usuario.php?id=<?php echo $user['id']; ?>" class="boton boton-secundario boton-pequeño">Editar</a>
                                    <a href="eliminar_usuario.php?id=<?php echo $user['id']; ?>" 
                                       class="boton boton-secundario boton-pequeño" style="background-color: #4d1c1c; color: #f44336;">Eliminar</a>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <hr class="separador-admin">

            <section>
                <div class="seccion-titulo-admin">
                    <h2>Gestión del Catálogo de Motos</h2>
                    <a href="nueva_moto.php" class="boton">+ Añadir Moto</a>
                </div>

                <div class="contenedor-tabla">
                    <table>
                        <thead>
                            <tr>
                                <th>Imagen</th>
                                <th>Marca y Modelo</th>
                                <th>Tipo</th>
                                <th>Precio/Día</th>
                                <th>Estado</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($moto = mysqli_fetch_assoc($resultado_motos)) { ?>
                            <tr>
                                <td>
                                    <?php 
                                    $imagen_src = 'imgs/default.jpg';
                                    if (!empty($moto['imagen'])) {
                                        $imagen_src = 'data:image/jpeg;base64,' . base64_encode($moto['imagen']);
                                    }
                                    ?>
                                    <img src="<?php echo $imagen_src; ?>" alt="Moto" class="miniatura-admin">
                                </td>
                                <td><strong style="color:white;"><?php echo $moto['marca'] . " " . $moto['modelo']; ?></strong></td>
                                <td><?php echo strtoupper($moto['tipo']); ?></td>
                                <td class="precio"><?php echo $moto['precio_dia']; ?>€</td>
                                <td>
                                    <?php if ($moto['disponible'] == 1 || $moto['disponible'] == 'si') { ?>
                                        <span class="etiqueta etiqueta-exito">Disponible</span>
                                    <?php } else { ?>
                                        <div class="info-alquilada">
                                            <span class="etiqueta etiqueta-aviso">Alquilada</span>
                                            <?php
                                            // Consulta para encontrar quién la tiene alquilada
                                            $sql_quien_alquila = "SELECT u.nombre, u.apellidos, a.id as alquiler_id
                                                                  FROM alquileres a
                                                                  JOIN usuarios u ON a.usuario_id = u.id
                                                                  WHERE a.moto_id = ? AND a.estado IN ('confirmado', 'en_curso')
                                                                  LIMIT 1";
                                            $stmt_quien = mysqli_prepare($conexion, $sql_quien_alquila);
                                            mysqli_stmt_bind_param($stmt_quien, "i", $moto['id']);
                                            mysqli_stmt_execute($stmt_quien);
                                            $res_quien = mysqli_stmt_get_result($stmt_quien);
                                            if ($quien_alquila = mysqli_fetch_assoc($res_quien)) {
                                                echo '<small style="font-size: 12px; color: var(--texto-gris); padding-left: 2px;">👤 <a href="detalle_alquiler.php?id=' . $quien_alquila['alquiler_id'] . '" style="color: var(--naranja-principal); text-decoration: underline;">' . htmlspecialchars($quien_alquila['nombre'] . ' ' . $quien_alquila['apellidos']) . '</a></small>';
                                            }
                                            mysqli_stmt_close($stmt_quien);
                                            ?>
                                        </div>
                                    <?php } ?>
                                </td>
                                <td style="white-space: nowrap;">
                                    <a href="editar_moto.php?id=<?php echo $moto['id']; ?>" class="boton boton-secundario boton-pequeño">Editar</a>
                                    <a href="eliminar_moto.php?id=<?php echo $moto['id']; ?>" 
                                       class="boton boton-secundario boton-pequeño" style="background-color: #4d1c1c; color: #f44336;">Eliminar</a>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <hr class="separador-admin">

            <section>
                <div class="seccion-titulo-admin">
                    <h2>Confirmar Pagos en Tienda (Pendientes)</h2>
                </div>

                <div class="contenedor-tabla">
                    <table>
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Moto</th>
                                <th>Fechas</th>
                                <th>Total</th>
                                <th>Acción</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Re-ejecutamos la consulta para la tabla
                            $res_pendientes_tabla = mysqli_query($conexion, "SELECT * FROM alquileres WHERE estado = 'pendiente' ORDER BY fecha_reserva DESC");

                            if (mysqli_num_rows($res_pendientes_tabla) > 0) {
                                while ($alquiler = mysqli_fetch_assoc($res_pendientes_tabla)) { 
                                    $u_id_alquiler = $alquiler['usuario_id'];
                                    $m_id_alquiler = $alquiler['moto_id'];
                                    
                                    $res_u = mysqli_query($conexion, "SELECT nombre, apellidos FROM usuarios WHERE id = $u_id_alquiler");
                                    $user_data = mysqli_fetch_assoc($res_u);

                                    $res_m = mysqli_query($conexion, "SELECT marca, modelo FROM motos WHERE id = $m_id_alquiler");
                                    $moto_data = mysqli_fetch_assoc($res_m);
                            ?>
                            <tr>
                                <td><?php echo $user_data['nombre'] . " " . $user_data['apellidos']; ?></td>
                                <td><?php echo $moto_data['marca'] . " " . $moto_data['modelo']; ?></td>
                                <td>
                                    <?php echo date("d/m/Y", strtotime($alquiler['fecha_inicio'])); ?> - 
                                    <?php echo date("d/m/Y", strtotime($alquiler['fecha_fin'])); ?>
                                </td>
                                <td class="precio"><strong><?php echo $alquiler['precio_total']; ?>€</strong></td>
                                <td>
                                    <form action="confirmar_pago_tienda.php" method="GET" style="display:inline; background:none; padding:0; border:none; box-shadow:none;">
                                        <input type="hidden" name="id" value="<?php echo $alquiler['id']; ?>">
                                        <button type="submit" class="boton btn-sm etiqueta-exito" style="border: none; cursor: pointer; padding: 10px 15px;">
                                            Confirmar Pago
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php 
                                } 
                            } else { ?>
                                <tr><td colspan="5" style="text-align:center; color: var(--texto-gris);">No hay pagos pendientes de confirmación.</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <hr class="separador-admin">

            <section>
                <div class="seccion-titulo-admin">
                    <h2>Gestión de Alquileres</h2>
                </div>

                <div class="contenedor-tabla">
                    <table>
                        <thead>
                            <tr>
                                <th>ID Alquiler</th>
                                <th>Cliente</th>
                                <th>Moto</th>
                                <th>Fechas</th>
                                <th>Total</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // Consulta para obtener todos los alquileres con datos de usuario y moto
                            $sql_alquileres_full = "
                                SELECT a.id, a.fecha_inicio, a.fecha_fin, a.precio_total, a.estado,
                                       u.nombre, u.apellidos,
                                       m.marca, m.modelo
                                FROM alquileres a
                                JOIN usuarios u ON a.usuario_id = u.id
                                JOIN motos m ON a.moto_id = m.id
                                ORDER BY a.fecha_reserva DESC
                            ";
                            $res_alquileres_full = mysqli_query($conexion, $sql_alquileres_full);

                            if (mysqli_num_rows($res_alquileres_full) > 0) {
                                while ($alquiler_full = mysqli_fetch_assoc($res_alquileres_full)) { 
                            ?>
                            <tr>
                                <td>#<?php echo $alquiler_full['id']; ?></td>
                                <td>
                                    <a href="detalle_alquiler.php?id=<?php echo $alquiler_full['id']; ?>" style="color: var(--naranja-principal); text-decoration: underline;">
                                        <?php echo $alquiler_full['nombre'] . " " . $alquiler_full['apellidos']; ?>
                                    </a>
                                </td>
                                <td><?php echo $alquiler_full['marca'] . " " . $alquiler_full['modelo']; ?></td>
                                <td>
                                    <?php echo date("d/m/Y", strtotime($alquiler_full['fecha_inicio'])); ?> - 
                                    <?php echo date("d/m/Y", strtotime($alquiler_full['fecha_fin'])); ?>
                                </td>
                                <td class="precio"><strong><?php echo $alquiler_full['precio_total']; ?>€</strong></td>
                                <td><span class="etiqueta estado-alquiler <?php echo $alquiler_full['estado']; ?>"><?php echo str_replace('_', ' ', strtoupper($alquiler_full['estado'])); ?></span></td>
                            </tr>
                            <?php 
                                } 
                            } else { ?>
                                <tr><td colspan="6" style="text-align:center; color: var(--texto-gris);">No hay alquileres registrados en el sistema.</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>
    </main>

    <footer class="pie-pagina">
        <p>&copy; 2026 ARUSLAT - Alquiler de Motos</p>
        <p>Proyecto TFG - Ángel Rus Latorre - ASIR</p>
    </footer>

    <?php if (isset($_SESSION['usuario_id'])): ?>
    <script src="logout_session.js"></script>
    <?php endif; ?>

</body>
</html>
<?php mysqli_close($conexion); ?>