<?php
/**
 * perfil_usuario.php
 * Panel personal del usuario autenticado.
 * - Muestra datos personales, historial de alquileres y mensajes de estado.
 * - Emplea funciones centralizadas como actualizar_sistema_completo() para mantener la consistencia.
 */
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'loginbd.php';

// Incluir funciones y actualizar el sistema
require_once 'funciones.php';

// ¡SEGURIDAD! Generamos un token CSRF para proteger los formularios de esta página.
generar_csrf_token();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

// Se llama a la función que actualiza el estado de todo el sistema antes de mostrar la página.
// Esto asegura que el usuario vea siempre la información más reciente (ej. un alquiler que acaba de finalizar).
actualizar_sistema_completo($conexion);

mysqli_set_charset($conexion, "utf8");

$u_id = $_SESSION['usuario_id'];

// Se obtienen los datos del usuario y sus estadísticas (antigüedad, total gastado)
// utilizando las funciones personalizadas creadas en `funciones.php`.
// Usar consultas preparadas para seguridad y eficiencia
$sql_usuario = "SELECT *, antiguedad_usuario(?) as dias_antiguedad, total_gastadoo(?) as total_invertido FROM usuarios WHERE id = ?";
$stmt_usuario = mysqli_prepare($conexion, $sql_usuario);
mysqli_stmt_bind_param($stmt_usuario, "iii", $u_id, $u_id, $u_id);
mysqli_stmt_execute($stmt_usuario);
$res_stats = mysqli_stmt_get_result($stmt_usuario);
$usuario = mysqli_fetch_assoc($res_stats);
mysqli_stmt_close($stmt_usuario);

// Se obtienen todos los alquileres del usuario para mostrarlos en la tabla.
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
</head>
<body>
    <header class="navbar">
        <div class="container">
            <h1><a href="index.php">ARUSLAT</a></h1>
            <nav>
                <a href="index">Inicio</a>
                <a href="catalogo">Catálogo</a>
                
                <?php if (isset($_SESSION['usuario_id'])) { 
                    if (!isset($_SESSION['rol'])) {
                        $_SESSION['rol'] = $usuario['rol'];
                    }

                    if ($_SESSION['rol'] === 'admin') { ?>
                        <a href="admin_dashboard" class="nav-destacado">Panel Admin</a>
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
            <h2 class="titulo-pagina">Mi Perfil</h2>
            
            <?php
            // Aquí compruebo si en la URL viene un parámetro 'pago'.
            if (isset($_GET['pago'])) {
                $mensaje = '';
                $clase_alerta = 'alerta-exito';
                $icono_alerta = '✔️';
                
                // Según el valor del parámetro ('confirmado', 'rechazado', etc.),
                // preparo un mensaje y un estilo diferente para la alerta.
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
                
                // Si he preparado un mensaje, lo muestro en un 'div' con el estilo correspondiente.
                if ($mensaje) {
                    echo '<div class="alerta ' . $clase_alerta . '">';
                    echo '<span class="alerta-icono">' . $icono_alerta . '</span> ' . $mensaje;
                    echo '</div>';
                }
            }
            
            // Este es un mensaje más simple para cuando se paga en tienda.
            if (isset($_GET['reserva']) && $_GET['reserva'] == 'ok') {
                echo '<div class="alerta-exito">';
                echo '<span class="alerta-icono">✔️</span> ¡Reserva realizada exitosamente! El pago se realizará en tienda.';
                echo '</div>';
            }

            // Lógica similar para los mensajes de cambio de contraseña.
            // Si el cambio fue bueno, muestro un mensaje de éxito.
            if (isset($_GET['password_change'])) {
                echo '<div class="alerta alerta-exito">✔️ Tu contraseña ha sido actualizada correctamente.</div>';
            }
            if (isset($_GET['password_change_error'])) {
                $error_msg = 'Ocurrió un error al cambiar la contraseña.';
                switch ($_GET['password_change_error']) {
                    case 'current_mismatch':
                        $error_msg = 'La contraseña actual que introdujiste es incorrecta.';
                        break;
                    case 'new_mismatch':
                        $error_msg = 'La nueva contraseña y su confirmación no coinciden.';
                        break;
                    case 'format':
                        $error_msg = 'La nueva contraseña debe tener al menos 8 caracteres, una letra y un número.';
                        break;
                    case 'empty':
                        $error_msg = 'Debes rellenar todos los campos para cambiar la contraseña.';
                        break;
                }
                echo '<div class="alerta alerta-error">❌ ' . $error_msg . '</div>';
            }

            // Y lo mismo para los mensajes de cancelación de reserva.
            if (isset($_GET['cancelacion']) && $_GET['cancelacion'] == 'exitosa') {
                echo '<div class="alerta alerta-exito">✔️ Tu reserva ha sido cancelada correctamente.</div>';
            }
            if (isset($_GET['error'])) {
                $error_msg = '';
                if ($_GET['error'] == 'cancelacion_fallida') {
                    $error_msg = 'No se pudo cancelar la reserva. Por favor, inténtalo de nuevo o contacta a soporte.';
                } elseif ($_GET['error'] == 'no_id') {
                    $error_msg = 'No se especificó ninguna reserva para cancelar.';
                }
                if ($error_msg) {
                    echo '<div class="alerta alerta-error">❌ ' . $error_msg . '</div>';
                }
            }
            ?>
            
            <div class="perfil-container">
                <h3>Datos Personales</h3>
                <?php if ($usuario) { ?>
                    <p><strong>Nombre:</strong> <?php echo htmlspecialchars($usuario['nombre'] . " " . $usuario['apellidos']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($usuario['email']); ?></p>
                    <p><strong>Teléfono:</strong> <?php echo htmlspecialchars($usuario['telefono']); ?></p>
                    <p><strong>Dirección:</strong> <?php echo htmlspecialchars($usuario['direccion']); ?></p>
                    
                    <div class="stats-bloque">
                        <span class="stats-badge">📅 <?php echo htmlspecialchars($usuario['dias_antiguedad']); ?> días con nosotros</span>
                        <span class="stats-badge">💰 <?php echo htmlspecialchars($usuario['total_invertido']); ?> € gastados</span>
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
                            <th class="text-center">Días</th> 
                            <th>Total</th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>   
                        <?php 
                        if (mysqli_num_rows($alquileres) > 0) {
                            // Recorro la lista de alquileres del usuario.
                            while ($alq = mysqli_fetch_assoc($alquileres)) {
                                // Por cada alquiler, necesito saber el nombre de la moto.
                                // Así que hago una consulta a la tabla 'motos' usando el 'moto_id' del alquiler.
                                $sql_moto = "SELECT marca, modelo FROM motos WHERE id = ?";
                                $stmt_moto = mysqli_prepare($conexion, $sql_moto);
                                mysqli_stmt_bind_param($stmt_moto, "i", $alq['moto_id']);
                                mysqli_stmt_execute($stmt_moto);
                                $res_moto = mysqli_stmt_get_result($stmt_moto);
                                $moto = mysqli_fetch_assoc($res_moto);
                                $moto_nombre = ($moto) 
                                    ? htmlspecialchars($moto['marca'] . " " . $moto['modelo']) 
                                    : "Moto eliminada";
                        ?>
                        <tr>
                            <td><?php echo $moto_nombre; ?></td>
                            <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($alq['fecha_inicio']))); ?></td>
                            <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($alq['fecha_fin']))); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($alq['dias_alquiler']); ?></td> 
                            <td class="precio"><?php echo htmlspecialchars($alq['precio_total']); ?>€</td>
                            <td class="text-center">
                                <span class="etiqueta <?php
                                    // Aquí pongo una clase CSS diferente según el estado del alquiler para que tenga un color distinto.
                                    if($alq['estado'] == 'en_curso') { echo 'en-curso'; } // azul
                                    elseif($alq['estado'] == 'finalizado') { echo 'etiqueta-error'; } // rojo
                                    elseif($alq['estado'] == 'confirmado') { echo 'etiqueta-exito'; } // verde
                                    elseif($alq['estado'] == 'cancelado') { echo 'etiqueta-error'; } // rojo
                                    else { echo 'etiqueta-aviso'; } // amarillo para 'pendiente'
                                ?>">
                                    <?php echo htmlspecialchars(str_replace('_', ' ', strtoupper($alq['estado']))); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="detalle_alquiler.php?id=<?php echo htmlspecialchars($alq['id']); ?>" class="boton boton-pequeño">Ver Detalles</a><?php
                                // El botón para cancelar solo debe aparecer si la reserva todavía está 'pendiente'.
                                if ($alq['estado'] == 'pendiente') {
                                    echo ' <a href="cancelar_reserva.php?id=' . htmlspecialchars($alq['id']) . '" class="boton boton-pequeño" onclick="return confirm(\'¿Estás seguro de que quieres cancelar esta reserva?\');">Cancelar</a>';
                                } ?>
                            </td>
                        </tr>
                        <?php 
                                mysqli_stmt_close($stmt_moto);
                            } 
                        } else { ?>
                            <tr><td colspan='7' class='text-center'>No tienes alquileres registrados.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <div class="perfil-container">
                <h3>Cambiar Contraseña</h3>
                <form action="procesar_cambio_password.php" method="POST" class="form-grid-3-col">
                    <!-- Campo oculto con el token CSRF para proteger contra ataques -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="form-group">
                        <label for="current_password">Contraseña Actual</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">Nueva Contraseña</label>
                        <input type="password" id="new_password" name="new_password" required placeholder="Mínimo 8 caracteres, 1 letra, 1 número">
                    </div>
                    <div class="form-group">
                        <label for="confirm_new_password">Confirmar Nueva Contraseña</label>
                        <input type="password" id="confirm_new_password" name="confirm_new_password" required>
                    </div>
                    <div class="form-submit-group">
                        <button type="submit" class="btn">Actualizar Contraseña</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <footer>
        <p>© 2026 ARUSLAT - Alquiler de Motos</p>
        <p>Proyecto TFG - Ángel Rus Latorre - ASIR</p>
    </footer>

</body>
</html>
<?php mysqli_close($conexion); ?>
                                // Si la moto existe, se muestra su nombre. Si no (porque fue eliminada), se muestra un texto alternativo.
                                $moto_nombre = ($moto) 
                                    ? htmlspecialchars($moto['marca'] . " " . $moto['modelo']) 
                                    : "Moto eliminada";
                        ?>
                        <tr>
                            <td><?php echo $moto_nombre; ?></td>
                            <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($alq['fecha_inicio']))); ?></td>
                            <td><?php echo htmlspecialchars(date("d/m/Y", strtotime($alq['fecha_fin']))); ?></td>
                            <td class="text-center"><?php echo htmlspecialchars($alq['dias_alquiler']); ?></td> 
                            <td class="precio"><?php echo htmlspecialchars($alq['precio_total']); ?>€</td>
                            <td class="text-center">
                                <span class="etiqueta <?php
                                    if($alq['estado'] == 'en_curso') { echo 'en-curso'; } // azul
                                    elseif($alq['estado'] == 'finalizado') { echo 'etiqueta-error'; } // rojo
                                    elseif($alq['estado'] == 'confirmado') { echo 'etiqueta-exito'; } // verde
                                    elseif($alq['estado'] == 'cancelado') { echo 'etiqueta-error'; } // rojo
                                    else { echo 'etiqueta-aviso'; } // amarillo para 'pendiente'
                                ?>">
                                    <?php echo htmlspecialchars(str_replace('_', ' ', strtoupper($alq['estado']))); ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <a href="detalle_alquiler.php?id=<?php echo htmlspecialchars($alq['id']); ?>" class="boton boton-pequeño">Ver Detalles</a><?php
                                // Solo mostrar el botón de cancelar si la reserva está pendiente
                                if ($alq['estado'] == 'pendiente') {
                                    // Añadimos el botón de cancelar en la misma celda de acciones, con un espacio.
                                    echo ' <a href="cancelar_reserva.php?id=' . htmlspecialchars($alq['id']) . '" class="boton boton-pequeño" onclick="return confirm(\'¿Estás seguro de que quieres cancelar esta reserva?\');">Cancelar</a>';
                                } ?>
                            </td>
                        </tr>
                        <?php 
                                mysqli_stmt_close($stmt_moto);
                            } 
                        } else { ?>
                            <tr><td colspan='7' class='text-center'>No tienes alquileres registrados.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>

            <div class="perfil-container">
                <h3>Cambiar Contraseña</h3>
                <form action="procesar_cambio_password.php" method="POST" class="form-grid-3-col">
                    <!-- Campo oculto con el token CSRF para proteger contra ataques -->
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                    <div class="form-group">
                        <label for="current_password">Contraseña Actual</label>
                        <input type="password" id="current_password" name="current_password" required>
                    </div>
                    <div class="form-group">
                        <label for="new_password">Nueva Contraseña</label>
                        <input type="password" id="new_password" name="new_password" required placeholder="Mínimo 8 caracteres, 1 letra, 1 número">
                    </div>
                    <div class="form-group">
                        <label for="confirm_new_password">Confirmar Nueva Contraseña</label>
                        <input type="password" id="confirm_new_password" name="confirm_new_password" required>
                    </div>
                    <div class="form-submit-group">
                        <button type="submit" class="btn">Actualizar Contraseña</button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <footer>
        <p>© 2026 ARUSLAT - Alquiler de Motos</p>
        <p>Proyecto TFG - Ángel Rus Latorre - ASIR</p>
    </footer>

</body>
</html>
<?php mysqli_close($conexion); ?>