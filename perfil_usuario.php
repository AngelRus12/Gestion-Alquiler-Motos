<?php
/**
 * perfil_usuario.php
 * Panel personal del usuario autenticado.
 * - Aquí muestro los datos personales, el historial de alquileres y mensajes de estado.
 * - Empleo mis funciones centralizadas como actualizar_sistema_completo() para mantener la consistencia.
 */
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'loginbd.php';

// Incluir funciones y actualizar el sistema
require_once 'funciones.php';

// ¡SEGURIDAD! Genero un token CSRF para proteger los formularios de esta página.
generar_csrf_token();

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

// Llamo a la función que actualiza el estado de todo el sistema antes de mostrar la página.
// Así me aseguro de que el usuario vea siempre la información más reciente (ej. un alquiler que acaba de finalizar).
actualizar_sistema_completo($conexion);

mysqli_set_charset($conexion, "utf8");

$u_id = $_SESSION['usuario_id'];

// Obtengo los datos del usuario y sus estadísticas (antigüedad, total gastado)
// utilizando las funciones personalizadas creadas en `funciones.php`.
// Usar consultas preparadas para seguridad y eficiencia
$sql_usuario = "SELECT *, antiguedad_usuario(?) as dias_antiguedad, total_gastado(?) as total_invertido FROM usuarios WHERE id = ?";
$stmt_usuario = mysqli_prepare($conexion, $sql_usuario);
mysqli_stmt_bind_param($stmt_usuario, "iii", $u_id, $u_id, $u_id);
mysqli_stmt_execute($stmt_usuario);
$res_stats = mysqli_stmt_get_result($stmt_usuario);
$usuario = mysqli_fetch_assoc($res_stats);
mysqli_stmt_close($stmt_usuario);
 
// --- LÓGICA DE PAGINACIÓN PARA ALQUILERES ---
$limit = 10; // 10 alquileres por página

// 1. Cuento el total de alquileres del usuario para la paginación.
$sql_count = "SELECT COUNT(*) as total FROM alquileres WHERE usuario_id = ?";
$stmt_count = mysqli_prepare($conexion, $sql_count);
mysqli_stmt_bind_param($stmt_count, "i", $u_id);
mysqli_stmt_execute($stmt_count);
$total_alquileres = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_count))['total'] ?? 0;
mysqli_stmt_close($stmt_count);

// 2. Calculo el número total de páginas.
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;
$total_pages = ceil($total_alquileres / $limit);

// 3. Obtengo solo los alquileres del usuario para la página actual.
$sql_alquileres = "SELECT * FROM alquileres WHERE usuario_id = ? ORDER BY fecha_reserva DESC LIMIT ? OFFSET ?";
$stmt_alquileres = mysqli_prepare($conexion, $sql_alquileres);
mysqli_stmt_bind_param($stmt_alquileres, "iii", $u_id, $limit, $offset);
mysqli_stmt_execute($stmt_alquileres);
$alquileres = mysqli_stmt_get_result($stmt_alquileres);
$alquileres_data = mysqli_fetch_all($alquileres, MYSQLI_ASSOC); // Datos solo para la página actual
mysqli_stmt_close($stmt_alquileres);

// --- OPTIMIZACIÓN N+1 ---
// 1. Para evitar el problema N+1, primero recolecto todos los IDs de moto de los alquileres de la página actual.
$moto_ids = [];
foreach ($alquileres_data as $alq) {
    if (!in_array($alq['moto_id'], $moto_ids)) {
        $moto_ids[] = $alq['moto_id'];
    }
}

// 2. Luego, obtengo todas las motos necesarias en UNA SOLA consulta.
$motos_map = [];
if (!empty($moto_ids)) {
    // Creo los placeholders (?) dinámicamente para el IN().
    $placeholders = implode(',', array_fill(0, count($moto_ids), '?'));
    $types = str_repeat('i', count($moto_ids));
    $sql_motos = "SELECT id, marca, modelo FROM motos WHERE id IN ($placeholders)";
    $stmt_motos = mysqli_prepare($conexion, $sql_motos);
    mysqli_stmt_bind_param($stmt_motos, $types, ...$moto_ids);
    mysqli_stmt_execute($stmt_motos);
    $resultado_motos = mysqli_stmt_get_result($stmt_motos);
    while ($moto = mysqli_fetch_assoc($resultado_motos)) {
        // Creo un "mapa" (array asociativo) para acceder fácilmente a los datos de cada moto por su ID.
        $motos_map[$moto['id']] = $moto;
    }
    mysqli_stmt_close($stmt_motos);
}

// Función para generar los enlaces de paginación (local para esta página)
function generar_paginacion($page, $total_pages, $base_url) {
    if ($total_pages <= 1) return;

    echo '<div class="paginacion paginacion-perfil">';
    // Botón Anterior
    if ($page > 1) {
        echo '<a href="' . $base_url . ($page - 1) . '" class="boton boton-pequeño">&laquo; Anterior</a>';
    }

    // Números de página
    for ($i = 1; $i <= $total_pages; $i++) {
        $active_class = ($i == $page) ? 'active' : '';
        echo '<a href="' . $base_url . $i . '" class="page-number ' . $active_class . '">' . $i . '</a>';
    }

    // Botón Siguiente
    if ($page < $total_pages) {
        echo '<a href="' . $base_url . ($page + 1) . '" class="boton boton-pequeño">Siguiente &raquo;</a>';
    }
    echo '</div>';
}

// Esta es mi función para detectar si el usuario está en un dispositivo móvil y cargar el CSS correspondiente.
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
            // Aquí compruebo si en la URL viene un parámetro 'pago' para mostrar una alerta al usuario.
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
            
            // Este es un mensaje más simple para cuando el usuario ha elegido pagar en tienda.
            if (isset($_GET['reserva']) && $_GET['reserva'] == 'ok') {
                echo '<div class="alerta-exito">';
                echo '<span class="alerta-icono">✔️</span> ¡Reserva realizada exitosamente! El pago se realizará en tienda.';
                echo '</div>';
            }

            // He implementado una lógica similar para los mensajes de cambio de contraseña.
            // Si el cambio fue exitoso, muestro un mensaje de éxito.
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

            // Y hago lo mismo para los mensajes de cancelación de reserva.
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
                        if (count($alquileres_data) > 0) {
                            // Recorro la lista de alquileres del usuario que obtuve antes.
                            foreach ($alquileres_data as $alq) {
                                // Busco la moto en mi "mapa", así no tengo que hacer una nueva consulta a la BD dentro del bucle.
                                $moto = $motos_map[$alq['moto_id']] ?? null;
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
                                    // Aquí pongo una clase CSS diferente según el estado del alquiler para que tenga un color distintivo.
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
                                <a href="detalle_alquiler.php?id=<?php echo htmlspecialchars($alq['id']); ?>" class="boton boton-pequeño">Ver Detalles</a>
                                <?php
                                // Esta es mi lógica para decidir si muestro los botones de Cancelar o Modificar.
                                // 1. El estado del alquiler debe ser 'pendiente' o 'confirmado'.
                                // 2. Y deben faltar más de 48 horas para el inicio del alquiler (regla de negocio).
                                $es_modificable = false;
                                if (in_array($alq['estado'], ['pendiente', 'confirmado'])) {
                                    $fecha_inicio_ts = strtotime($alq['fecha_inicio']);
                                    $limite_48h_ts = strtotime('+48 hours');
                                    if ($fecha_inicio_ts > $limite_48h_ts) {
                                        $es_modificable = true;
                                    }
                                }
                                if ($es_modificable) { ?>
                                    <a href="modificar_reserva.php?id=<?php echo htmlspecialchars($alq['id']); ?>" class="boton boton-pequeño boton-secundario ml-10">Modificar</a>
                                    <button onclick="cancelarReserva(this, <?php echo htmlspecialchars($alq['id']); ?>)" class="boton boton-pequeño boton-error ml-10">Cancelar</button><?php
                                } ?>
                            </td>
                        </tr>
                        <?php } 
                        } else { ?>
                            <tr><td colspan='7' class='text-center'>No tienes alquileres registrados.</td></tr>
                        <?php } ?>
                    </tbody>
                </table>
                <?php
                // Generar URL base para la paginación de alquileres
                $params = $_GET;
                unset($params['page']);
                $base_url_alquileres = 'perfil_usuario.php?' . http_build_query($params) . (empty($params) ? '' : '&') . 'page=';
                generar_paginacion($page, $total_pages, $base_url_alquileres);
                ?>
            </div>

            <div class="perfil-container">
                <h3>Cambiar Contraseña</h3>
                <form action="procesar_cambio_password.php" method="POST" class="form-grid-3-col">
                    <!-- Incluyo un campo oculto con el token CSRF para proteger este formulario contra ataques. -->
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

    <script>
    function cancelarReserva(button, idAlquiler) {
        if (!confirm('¿Estás seguro de que quieres cancelar esta reserva?')) {
            return;
        }

        // Deshabilito el botón para evitar que el usuario haga clic varias veces mientras se procesa.
        button.disabled = true;
        button.textContent = 'Cancelando...';

        // Preparo los datos para enviarlos vía POST con AJAX (usando fetch).
        const formData = new FormData();
        formData.append('id', idAlquiler);

        // Hago la petición AJAX.
        fetch('ajax_cancelar_reserva.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Si la cancelación tiene éxito, actualizo la interfaz sin recargar la página para una mejor UX.
                const fila = button.closest('tr');
                const celdaEstado = fila.querySelector('.etiqueta');
                celdaEstado.textContent = 'CANCELADO';
                celdaEstado.className = 'etiqueta etiqueta-error'; // Cambiar a la clase de cancelado
                button.remove(); // Elimino el botón de cancelar porque ya no se puede usar.
                alert(data.message);
            } else {
                // Si hay un error, muestro un mensaje y vuelvo a habilitar el botón.
                alert('Error: ' + data.message);
                button.disabled = false;
                button.textContent = 'Cancelar';
            }
        })
        .catch(error => console.error('Error en la petición AJAX:', error));
    }
    </script>
</body>
</html>
<?php mysqli_close($conexion); ?>