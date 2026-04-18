<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'loginbd.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

mysqli_set_charset($conexion, "utf8");

$u_id = $_SESSION['usuario_id'];

// Usar consultas preparadas para seguridad y eficiencia
$sql_usuario = "SELECT *, antiguedad_usuario(?) as dias_antiguedad, total_gastadoo(?) as total_invertido FROM usuarios WHERE id = ?";
$stmt_usuario = mysqli_prepare($conexion, $sql_usuario);
mysqli_stmt_bind_param($stmt_usuario, "iii", $u_id, $u_id, $u_id);
mysqli_stmt_execute($stmt_usuario);
$res_stats = mysqli_stmt_get_result($stmt_usuario);
$usuario = mysqli_fetch_assoc($res_stats);
mysqli_stmt_close($stmt_usuario);


$sql_alquileres = "SELECT * FROM alquileres WHERE usuario_id = ? ORDER BY fecha_reserva DESC";
$stmt_alquileres = mysqli_prepare($conexion, $sql_alquileres);
mysqli_stmt_bind_param($stmt_alquileres, "i", $u_id);
mysqli_stmt_execute($stmt_alquileres);
$alquileres = mysqli_stmt_get_result($stmt_alquileres);

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
    <title>Mi Perfil - ARUSLAT</title>
    <?php if ($is_mobile): ?>
        <link rel="stylesheet" href="estilos_mobile.css">
    <?php else: ?>
        <link rel="stylesheet" href="estilos.css">
    <?php endif; ?>
    <style>
        .stats-badge {
            display: inline-block;
            background: #e03e00;
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.9em;
            margin-right: 10px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <header class="navbar">
        <div class="container">
            <h1>ARUSLAT</h1>
            <nav>
                <a href="index">Inicio</a>
                <a href="catalogo">Catálogo</a>
                
                <?php if (isset($_SESSION['usuario_id'])) { 
                    if (!isset($_SESSION['rol'])) {
                        $_SESSION['rol'] = $usuario['rol'];
                    }

                    if ($_SESSION['rol'] === 'admin') { ?>
                        <a href="admin_dashboard" style="color: #e03e00; font-weight: bold;">Panel Admin</a>
                    <?php } ?>

                    <a href="perfil_usuario">Mi Perfil</a> 
                    <a href="logout">Cerrar Sesión</a>
                <?php } else { ?>
                    <a href="login">Login</a>
                    <a href="registro">Registro</a>
                <?php } ?>
            </nav>
        </div>
    </header>

    <main class="main-content">
        <div class="container">
            <h2 style="margin-top: 40px; text-align: center;">Mi Perfil</h2>
            
            <?php
            // Mostrar mensajes de pago
            if (isset($_GET['pago'])) {
                $mensaje = '';
                $clase_alerta = 'alerta-exito';
                $icono_alerta = '✔️';
                
                switch ($_GET['pago']) {
                    case 'confirmado':
                        $mensaje = '¡Pago confirmado exitosamente! Tu alquiler está reservado.';
                        $clase_alerta = 'alerta-exito';
                        $icono_alerta = '✔️';
                        break;
                    case 'rechazado':
                        $mensaje = 'El pago fue rechazado. Por favor, intenta nuevamente o contacta a soporte.';
                        $clase_alerta = 'alerta-error';
                        $icono_alerta = '❌';
                        break;
                    case 'pendiente':
                        $mensaje = 'Tu pago está siendo procesado. Te notificaremos cuando se confirme.';
                        $clase_alerta = 'alerta';
                        $icono_alerta = '⏳';
                        break;
                    case 'cancelado':
                        $mensaje = 'El pago fue cancelado. Tu reserva ha sido anulada.';
                        $clase_alerta = 'alerta';
                        $icono_alerta = 'ℹ️';
                        break;
                    case 'error':
                        $mensaje = 'Ocurrió un error con el pago. Por favor, contacta a soporte.';
                        $clase_alerta = 'alerta-error';
                        $icono_alerta = '⚠️';
                        break;
                }
                
                if ($mensaje) {
                    echo '<div class="alerta ' . $clase_alerta . '">';
                    echo '<span class="alerta-icono">' . $icono_alerta . '</span> ' . $mensaje;
                    echo '</div>';
                }
            }
            
            // Mostrar mensaje de reserva exitosa
            if (isset($_GET['reserva']) && $_GET['reserva'] == 'ok') {
                echo '<div class="alerta-exito">';
                echo '<span class="alerta-icono">✔️</span> ¡Reserva realizada exitosamente! El pago se realizará en tienda.';
                echo '</div>';
            }
            ?>
            
            <div class="perfil-container">
                <h3>Datos Personales</h3>
                <?php if ($usuario) { ?>
                    <p><strong>Nombre:</strong> <?php echo $usuario['nombre'] . " " . $usuario['apellidos']; ?></p>
                    <p><strong>Email:</strong> <?php echo $usuario['email']; ?></p>
                    <p><strong>Teléfono:</strong> <?php echo $usuario['telefono']; ?></p>
                    <p><strong>Dirección:</strong> <?php echo $usuario['direccion']; ?></p>
                    
                    <div class="stats-bloque">
                        <span class="stats-badge">📅 <?php echo $usuario['dias_antiguedad']; ?> días con nosotros</span>
                        <span class="stats-badge">💰 <?php echo $usuario['total_invertido']; ?> € gastados</span>
                    </div>

                <?php } else { ?>
                    <p>Error: No se pudieron cargar los datos del usuario.</p>
                <?php } ?>
            </div>

            <div class="perfil-container">
                <h3>Historial de Alquileres</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Moto</th>
                            <th>Inicio</th>
                            <th>Fin</th>
                            <th style="text-align: center;">Días</th> 
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>   
                        <?php 
                        if (mysqli_num_rows($alquileres) > 0) {
                            while ($alq = mysqli_fetch_assoc($alquileres)) { 
                                // Por cada alquiler, consultamos los datos de la moto
                                $sql_moto = "SELECT marca, modelo FROM motos WHERE id = ?";
                                $stmt_moto = mysqli_prepare($conexion, $sql_moto);
                                mysqli_stmt_bind_param($stmt_moto, "i", $alq['moto_id']);
                                mysqli_stmt_execute($stmt_moto);
                                $res_moto = mysqli_stmt_get_result($stmt_moto);
                                $moto = mysqli_fetch_assoc($res_moto);
                        ?>
                        <tr>
                            <td><?php echo ($moto) ? $moto['marca'] . " " . $moto['modelo'] : "Moto eliminada"; ?></td>
                            <td><?php echo date("d/m/Y", strtotime($alq['fecha_inicio'])); ?></td>
                            <td><?php echo date("d/m/Y", strtotime($alq['fecha_fin'])); ?></td>
                            <td style="text-align: center;"><?php echo $alq['dias_alquiler']; ?></td> 
                            <td class="precio"><?php echo $alq['precio_total']; ?>€</td>
                            <td style="text-align: center;">
                                <span class="etiqueta <?php  
                                    if($alq['estado'] == 'en_curso') { echo 'en-curso'; } // azul
                                    elseif($alq['estado'] == 'finalizado') { echo 'etiqueta-error'; } // rojo
                                    elseif($alq['estado'] == 'confirmado') { echo 'etiqueta-exito'; } // verde
                                    elseif($alq['estado'] == 'cancelado') { echo 'etiqueta-error'; } // rojo
                                    else { echo 'etiqueta-aviso'; } // amarillo para 'pendiente'
                                ?>">
                                    <?php echo str_replace('_', ' ', strtoupper($alq['estado'])); ?>
                                </span>
                            </td>
                            <td style="text-align: center;">
                                <a href="detalle_alquiler.php?id=<?php echo $alq['id']; ?>" class="boton boton-pequeño">Ver Detalles</a>
                            </td>
                        </tr>
                        <?php 
                                mysqli_stmt_close($stmt_moto);
                            } 
                        } else { ?>
                            <tr><td colspan='7' style='text-align:center;'>No tienes alquileres registrados.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
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
<?php mysqli_close($conexion); ?>