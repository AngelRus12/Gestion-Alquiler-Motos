<?php
// Establece la codificación de caracteres a UTF-8 para soportar caracteres especiales.
header('Content-Type: text/html; charset=utf-8');
// Inicia la sesión para poder acceder a las variables de sesión.
session_start();
// Incluye el archivo con las credenciales de la base de datos.
require_once 'loginbd.php';
// Incluye el archivo que contiene funciones reutilizables, como la de actualizar el sistema.
require_once 'funciones.php';

// --- 1. CONTROL DE ACCESO ---
// Verifica si el usuario ha iniciado sesión y si tiene el rol de 'admin'.
// Si no cumple las condiciones, se le redirige a la página de login con un mensaje de error.
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

// --- 2. CONEXIÓN A LA BASE DE DATOS ---
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
// Si la conexión falla, se detiene la ejecución y se muestra un error.
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}
// Establece el conjunto de caracteres a UTF-8 para la conexión con la base de datos.
mysqli_set_charset($conexion, "utf8");

// --- 3. ACTUALIZACIÓN DEL SISTEMA ---
// Llama a una función centralizada que actualiza el estado de alquileres y motos.
// Por ejemplo, pasa alquileres 'confirmados' a 'en_curso' si la fecha de inicio ya ha llegado.
actualizar_sistema_completo($conexion);

// --- 4. GESTIÓN DE MENSAJES DE FEEDBACK ---
// Comprueba la URL en busca de parámetros 'msg' (éxito) o 'error' para mostrar alertas al administrador.
$mensaje_html = "";
if (isset($_GET['msg'])) {
    // Se usan mensajes genéricos para cubrir diferentes operaciones (crear, actualizar, eliminar).
    $mensaje_html = "<div class='alerta alerta-exito'>Operación realizada con éxito.</div>";
}
if (isset($_GET['error'])) {
    // Manejo específico para el error de auto-eliminación.
    if ($_GET['error'] == 'autodelecion') {
        $mensaje_html = "<div class='alerta alerta-error'>No puedes eliminar tu propia cuenta.</div>";
    } else {
        // Mensaje de error genérico para otras situaciones.
        $mensaje_html = "<div class='alerta alerta-error'>Hubo un error al procesar la solicitud.</div>";
    }
}

// --- 5. CONSULTAS PARA LAS TARJETAS DE RESUMEN (OVERVIEW) ---
// Se realizan 3 consultas separadas para obtener los totales de usuarios, motos y alquileres pendientes.
$resultado_usuarios = mysqli_query($conexion, "SELECT id, nombre, apellidos, email, rol, estado FROM usuarios");
$resultado_motos = mysqli_query($conexion, "SELECT id, marca, modelo, precio_dia, disponible, tipo, imagen FROM motos");

// Nuevas consultas para el resumen de estadísticas
$total_users_res = mysqli_query($conexion, "SELECT COUNT(*) as total FROM usuarios");
$total_motos_res = mysqli_query($conexion, "SELECT COUNT(*) as total FROM motos");
$res_pendientes_res = mysqli_query($conexion, "SELECT COUNT(*) as total FROM alquileres WHERE estado = 'pendiente'");

// Se extrae el valor 'total' de cada resultado.
$total_users = mysqli_fetch_assoc($total_users_res)['total'];
$total_motos = mysqli_fetch_assoc($total_motos_res)['total'];
$total_pendientes = mysqli_fetch_assoc($res_pendientes_res)['total'];

// Asegura que la tabla de promociones exista.
crear_tabla_promociones_si_no_existe($conexion);

$mensaje_promocion_html = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['guardar_promocion'])) {
    $titulo = trim($_POST['titulo']);
    $mensaje = trim($_POST['mensaje']);
    $enlace = trim($_POST['enlace']);
    $fecha_inicio = trim($_POST['fecha_inicio']);
    $fecha_fin = trim($_POST['fecha_fin']);
    $activo = isset($_POST['activo']) ? 1 : 0;

    if (empty($titulo) || empty($mensaje)) {
        $mensaje_promocion_html = "<div class='alerta alerta-error'>El título y el mensaje son obligatorios para publicar una promoción.</div>";
    } else {
        if ($activo) {
            mysqli_query($conexion, "UPDATE promociones SET activo = 0");
        }
        $sql_promocion = "INSERT INTO promociones (titulo, mensaje, enlace, fecha_inicio, fecha_fin, activo)
                          VALUES (?, ?, ?, NULLIF(?,''), NULLIF(?,''), ?)";
        $stmt_promocion = mysqli_prepare($conexion, $sql_promocion);
        mysqli_stmt_bind_param($stmt_promocion, "sssssi", $titulo, $mensaje, $enlace, $fecha_inicio, $fecha_fin, $activo);
        if (mysqli_stmt_execute($stmt_promocion)) {
            $mensaje_promocion_html = "<div class='alerta alerta-exito'>Promoción guardada correctamente y publicada en la página de inicio.</div>";
        } else {
            $mensaje_promocion_html = "<div class='alerta alerta-error'>No se pudo guardar la promoción. Intenta de nuevo.</div>";
        }
        mysqli_stmt_close($stmt_promocion);
    }
}

$res_promociones = mysqli_query($conexion, "SELECT * FROM promociones ORDER BY activo DESC, fecha_inicio DESC, fecha_creacion DESC");

// --- 6. DATOS PARA EL CALENDARIO DE RESERVAS ---
$consulta_eventos = "SELECT a.id, a.fecha_inicio, a.fecha_fin, a.estado, u.nombre, u.apellidos, m.marca, m.modelo
                     FROM alquileres a
                     LEFT JOIN usuarios u ON a.usuario_id = u.id
                     LEFT JOIN motos m ON a.moto_id = m.id
                     ORDER BY a.fecha_inicio ASC";
$resultado_eventos = mysqli_query($conexion, $consulta_eventos);
$eventos_calendario = [];
while ($evento = mysqli_fetch_assoc($resultado_eventos)) {
    $eventos_calendario[] = [
        'id' => $evento['id'],
        'fecha_inicio' => $evento['fecha_inicio'],
        'fecha_fin' => $evento['fecha_fin'],
        'estado' => $evento['estado'],
        'cliente' => trim($evento['nombre'] . ' ' . $evento['apellidos']),
        'moto' => trim($evento['marca'] . ' ' . $evento['modelo']),
    ];
}

?>
<!-- El resto del archivo es la estructura HTML que muestra los datos obtenidos. -->

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="logo.png" type="image/png">
    <link rel="apple-touch-icon" href="logo.png">
    <title>Panel Administrativo - ARUSLAT</title>
    <link rel="stylesheet" href="estilos.css">
    <style>
        .calendar-panel {margin: 30px 0; padding: 20px; background: rgba(255,255,255,0.08); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px;}
        .calendar-header {display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 16px;}
        .calendar-header h2 {margin: 0;}
        .calendar-nav {display: flex; gap: 10px;}
        .calendar-nav button {padding: 10px 14px; background: #2d1f1b; color: #fff; border: none; border-radius: 8px; cursor: pointer;}
        .calendar-grid {display: grid; grid-template-columns: repeat(7, 1fr); gap: 8px;}
        .calendar-day {padding: 12px; background: rgba(255,255,255,0.05); border-radius: 10px; min-height: 120px; display: flex; flex-direction: column;}
        .calendar-day.disabled {opacity: 0.35;}
        .calendar-day strong {display: block; margin-bottom: 8px;}
        .calendar-event {margin-bottom: 6px; padding: 5px 8px; border-radius: 8px; color: #fff; font-size: 0.82rem; line-height: 1.2; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;}
        .event-pendiente {background: #f1c40f;}
        .event-confirmado {background: #27ae60;}
        .event-en_curso {background: #3498db;}
        .event-finalizado {background: #7f8c8d;}
        .event-cancelado {background: #e74c3c;}
        .calendar-legends {display: flex; flex-wrap: wrap; gap: 10px; margin-top: 12px;}
        .legend-item {display: inline-flex; align-items: center; gap: 6px; font-size: 0.85rem;}
        .legend-badge {width: 14px; height: 14px; border-radius: 4px; display: inline-block;}
        .calendar-event-list {margin-top: 18px;}
        .calendar-event-list li {margin-bottom: 10px;}
        .calendar-event-list small {color: #ccc;}
    </style>
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

            <?php echo $mensaje_promocion_html; ?>
            <section>
                <div class="seccion-titulo-admin">
                    <h2>Ofertas y Promociones</h2>
                </div>
                <div class="contenedor-tabla">
                    <form method="POST" style="display:grid; gap:12px; margin-bottom:24px;">
                        <div>
                            <label>Título de la promoción</label>
                            <input type="text" name="titulo" required placeholder="Ej. 20% de descuento este fin de semana" />
                        </div>
                        <div>
                            <label>Mensaje</label>
                            <textarea name="mensaje" required rows="3" placeholder="Texto que se mostrará en la página de inicio"></textarea>
                        </div>
                        <div>
                            <label>Enlace (opcional)</label>
                            <input type="url" name="enlace" placeholder="https://tusitio.com/oferta" />
                        </div>
                        <div style="display:flex; gap:12px; flex-wrap:wrap;">
                            <div style="flex:1; min-width:160px;">
                                <label>Fecha inicio (opcional)</label>
                                <input type="date" name="fecha_inicio" />
                            </div>
                            <div style="flex:1; min-width:160px;">
                                <label>Fecha fin (opcional)</label>
                                <input type="date" name="fecha_fin" />
                            </div>
                            <div style="flex:1; min-width:160px; display:flex; align-items:flex-end;">
                                <label style="display:block;"><input type="checkbox" name="activo" checked /> Publicar ahora</label>
                            </div>
                        </div>
                        <button type="submit" name="guardar_promocion" class="boton">Publicar promoción</button>
                    </form>
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Título</th>
                                <th>Mensaje</th>
                                <th>Fechas</th>
                                <th>Activo</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($promo = mysqli_fetch_assoc($res_promociones)) { ?>
                                <tr>
                                    <td><?php echo $promo['id']; ?></td>
                                    <td><?php echo htmlspecialchars($promo['titulo']); ?></td>
                                    <td><?php echo htmlspecialchars($promo['mensaje']); ?></td>
                                    <td><?php echo $promo['fecha_inicio'] ?: '-'; ?> - <?php echo $promo['fecha_fin'] ?: '-'; ?></td>
                                    <td><?php echo $promo['activo'] ? '<span class="etiqueta etiqueta-exito">Sí</span>' : '<span class="etiqueta etiqueta-aviso">No</span>'; ?></td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="calendar-panel">
                <div class="calendar-header">
                    <div>
                        <h2>Calendario de Reservas</h2>
                        <p>Visualiza todas las reservas y eventos de entrega/recogida por mes.</p>
                    </div>
                    <div class="calendar-nav">
                        <button id="prevMonth">‹ Mes anterior</button>
                        <button id="nextMonth">Mes siguiente ›</button>
                    </div>
                </div>
                <div id="calendarGrid" class="calendar-grid"></div>
                <div class="calendar-legends">
                    <span class="legend-item"><span class="legend-badge" style="background:#f1c40f"></span>Pendiente</span>
                    <span class="legend-item"><span class="legend-badge" style="background:#27ae60"></span>Confirmado</span>
                    <span class="legend-item"><span class="legend-badge" style="background:#3498db"></span>En curso</span>
                    <span class="legend-item"><span class="legend-badge" style="background:#7f8c8d"></span>Finalizado</span>
                    <span class="legend-item"><span class="legend-badge" style="background:#e74c3c"></span>Cancelado</span>
                </div>
                <div class="calendar-event-list">
                    <h3>Próximos eventos</h3>
                    <ul id="calendarEventList"></ul>
                </div>
            </section>

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
                                    // Se asigna una clase CSS diferente según el estado del usuario para darle un color distintivo.
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
                                    <a href="editar_usuario.php?id=<?php echo $user['id']; ?>" class="boton boton-pequeño">Editar</a>
                                    <a href="eliminar_usuario.php?id=<?php echo $user['id']; ?>" class="boton boton-pequeño etiqueta-error">Eliminar</a>
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
                                    // Lógica para mostrar la imagen de la moto.
                                    // Si la moto no tiene imagen, se muestra una por defecto.
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
                                    <?php // Se muestra un estado diferente si la moto está disponible o alquilada. ?>
                                    <?php if ($moto['disponible'] == 1 || $moto['disponible'] == 'si') { ?>
                                        <span class="etiqueta etiqueta-exito">Disponible</span>
                                    <?php } else { ?>
                                        <div class="info-alquilada">
                                            <span class="etiqueta etiqueta-aviso">Alquilada</span>
                                            <?php
                                            // --- PROBLEMA N+1: CONSULTAS EN BUCLE ---
                                            // Este bloque de código, aunque funciona, es ineficiente. Por cada moto alquilada,
                                            // realiza DOS consultas adicionales a la base de datos dentro del bucle `while`.
                                            // Esto se conoce como el "problema N+1" y puede ralentizar mucho la página si hay muchas motos.
                                            // Paso 1: Encontrar el alquiler activo ('confirmado' o 'en_curso') para esta moto específica.
                                            $sql_alquiler_moto = "SELECT usuario_id, id as alquiler_id FROM alquileres WHERE moto_id = ? AND estado IN ('confirmado', 'en_curso') LIMIT 1";
                                            $stmt_alquiler_moto = mysqli_prepare($conexion, $sql_alquiler_moto);
                                            mysqli_stmt_bind_param($stmt_alquiler_moto, "i", $moto['id']);
                                            mysqli_stmt_execute($stmt_alquiler_moto);
                                            $res_alquiler_moto = mysqli_stmt_get_result($stmt_alquiler_moto);

                                            // Si se encuentra un alquiler activo...
                                            if ($alquiler_info = mysqli_fetch_assoc($res_alquiler_moto)) {
                                                $usuario_alquila_id = $alquiler_info['usuario_id'];
                                                $alquiler_id = $alquiler_info['alquiler_id'];

                                                // Paso 2: Con el ID del usuario obtenido, hacer una segunda consulta para obtener su nombre.
                                                $sql_usuario_alquila = "SELECT nombre, apellidos FROM usuarios WHERE id = ?";
                                                $stmt_usuario_alquila = mysqli_prepare($conexion, $sql_usuario_alquila);
                                                mysqli_stmt_bind_param($stmt_usuario_alquila, "i", $usuario_alquila_id);
                                                mysqli_stmt_execute($stmt_usuario_alquila);
                                                $res_usuario_alquila = mysqli_stmt_get_result($stmt_usuario_alquila);
                                                
                                                if ($quien_alquila = mysqli_fetch_assoc($res_usuario_alquila)) {
                                                    // Paso 3: Mostrar el nombre del cliente con un enlace al detalle del alquiler.
                                                    echo '<a href="detalle_alquiler.php?id=' . $alquiler_id . '" class="boton boton-secundario boton-pequeño" style="margin-top: 5px;">👤 ' . htmlspecialchars($quien_alquila['nombre'] . ' ' . $quien_alquila['apellidos']) . '</a>';
                                                }
                                                mysqli_stmt_close($stmt_usuario_alquila);
                                            }
                                            mysqli_stmt_close($stmt_alquiler_moto);
                                            ?>
                                        </div>
                                    <?php } ?>
                                </td>
                                <td style="white-space: nowrap;">
                                    <a href="editar_moto.php?id=<?php echo $moto['id']; ?>" class="boton boton-pequeño">Editar</a>
                                    <a href="eliminar_moto.php?id=<?php echo $moto['id']; ?>" class="boton boton-pequeño etiqueta-error">Eliminar</a>
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
                            // --- SOLUCIÓN AL PROBLEMA N+1: PRE-CARGA DE DATOS (Eager Loading) ---
                            // Esta es la forma optimizada de obtener datos relacionados.

                            // Paso 1: Obtener todos los alquileres pendientes de una sola vez.
                            $res_pendientes_tabla = mysqli_query($conexion, "SELECT * FROM alquileres WHERE estado = 'pendiente' ORDER BY fecha_reserva DESC");
                            $alquileres_pendientes = [];
                            $user_ids_pendientes = [];
                            $moto_ids_pendientes = [];

                            // Se comprueba si la consulta devolvió algún resultado.
                            if (mysqli_num_rows($res_pendientes_tabla) > 0) {
                                // Paso 2: Recorrer los resultados y guardar los IDs de usuario y moto en arrays.
                                // Esto evita hacer una consulta a la base de datos por cada fila de la tabla (problema N+1).
                                while ($alquiler = mysqli_fetch_assoc($res_pendientes_tabla)) {
                                    $alquileres_pendientes[] = $alquiler;
                                    $user_ids_pendientes[] = $alquiler['usuario_id'];
                                    $moto_ids_pendientes[] = $alquiler['moto_id'];
                                }

                                // Paso 3: Obtener todos los datos de los usuarios necesarios con UNA SOLA consulta.
                                $usuarios_pendientes = [];
                                if (!empty($user_ids_pendientes)) {
                                    // Se usa "IN (...)" para traer múltiples usuarios a la vez, y `array_unique` para no pedir el mismo ID varias veces.
                                    $res_u = mysqli_query($conexion, "SELECT id, nombre, apellidos FROM usuarios WHERE id IN (" . implode(',', array_unique($user_ids_pendientes)) . ")");
                                    // Se crea un array asociativo donde la clave es el ID del usuario para un acceso rápido después.
                                    while ($user_data = mysqli_fetch_assoc($res_u)) { $usuarios_pendientes[$user_data['id']] = $user_data; }
                                }

                                // Paso 4: Obtener todos los datos de las motos necesarias con UNA SOLA consulta.
                                $motos_pendientes = [];
                                if (!empty($moto_ids_pendientes)) {
                                    // Misma estrategia que con los usuarios.
                                    $res_m = mysqli_query($conexion, "SELECT id, marca, modelo FROM motos WHERE id IN (" . implode(',', array_unique($moto_ids_pendientes)) . ")");
                                    while ($moto_data = mysqli_fetch_assoc($res_m)) { $motos_pendientes[$moto_data['id']] = $moto_data; }
                                }

                                // Paso 5: Ahora sí, recorrer los alquileres y mostrar los datos.
                                // La información de usuario y moto se obtiene de los arrays que ya tenemos en memoria, no de la BD.
                                foreach ($alquileres_pendientes as $alquiler) {
                                    // Se usa el operador de fusión de null (??) para evitar errores si un usuario o moto ha sido eliminado.
                                    $user_data = $usuarios_pendientes[$alquiler['usuario_id']] ?? ['nombre' => 'Usuario', 'apellidos' => 'Eliminado'];
                                    $moto_data = $motos_pendientes[$alquiler['moto_id']] ?? ['marca' => 'Moto', 'modelo' => 'Eliminada'];
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
                                    <!-- Formulario para confirmar el pago en tienda -->
                                    <form action="confirmar_pago_tienda.php" method="GET" style="display:inline; background:none; padding:0; border:none; box-shadow:none;">
                                        <input type="hidden" name="id" value="<?php echo $alquiler['id']; ?>">
                                        <button type="submit" class="boton btn-sm etiqueta-exito" style="border: none; cursor: pointer; padding: 10px 15px;">
                                            Confirmar Pago
                                        </button>
                                    </form>
                                    <!-- Formulario para que el admin cancele el alquiler -->
                                    <form action="cancelar_alquiler_admin.php" method="GET" style="display:inline; margin-left: 5px;">
                                        <input type="hidden" name="id" value="<?php echo $alquiler['id']; ?>">
                                        <button type="submit" class="boton boton-pequeño boton-error">Cancelar</button>
                                    </form>
                                </td>
                            </tr>
                            <?php 
                                } 
                            } else { ?>
                                <!-- Mensaje que se muestra si no hay alquileres pendientes. -->
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
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            // --- LÓGICA OPTIMIZADA PARA LA TABLA DE TODOS LOS ALQUILERES ---
                            // Se sigue la misma estrategia que en la tabla de pagos pendientes para ser más eficientes.

                            // Paso 1: Obtener todos los alquileres.
                            $res_alquileres_todos = mysqli_query($conexion, "SELECT * FROM alquileres ORDER BY fecha_reserva DESC");
                            $todos_alquileres = [];
                            $todos_user_ids = [];
                            $todos_moto_ids = [];

                            if (mysqli_num_rows($res_alquileres_todos) > 0) {
                                // Paso 2: Guardar los IDs de usuario y moto en arrays.
                                while ($alquiler = mysqli_fetch_assoc($res_alquileres_todos)) {
                                    $todos_alquileres[] = $alquiler;
                                    $todos_user_ids[] = $alquiler['usuario_id'];
                                    $todos_moto_ids[] = $alquiler['moto_id'];
                                }

                                // Paso 3: Obtener todos los usuarios y motos necesarios en solo dos consultas.
                                $todos_usuarios = [];
                                if (!empty($todos_user_ids)) {
                                    $res_u_todos = mysqli_query($conexion, "SELECT id, nombre, apellidos FROM usuarios WHERE id IN (" . implode(',', array_unique($todos_user_ids)) . ")");
                                    while ($user_data = mysqli_fetch_assoc($res_u_todos)) { $todos_usuarios[$user_data['id']] = $user_data; }
                                }

                                $todas_motos = [];
                                if (!empty($todos_moto_ids)) {
                                    // La función array_unique() es importante para no pedir el mismo ID varias veces.
                                    $res_m_todos = mysqli_query($conexion, "SELECT id, marca, modelo FROM motos WHERE id IN (" . implode(',', array_unique($todos_moto_ids)) . ")");
                                    while ($moto_data = mysqli_fetch_assoc($res_m_todos)) { $todas_motos[$moto_data['id']] = $moto_data; }
                                }

                                // Paso 4: Iterar y mostrar los datos combinados desde la memoria.
                                foreach ($todos_alquileres as $alquiler_full) {
                                    $usuario_alquiler = $todos_usuarios[$alquiler_full['usuario_id']] ?? ['nombre' => 'Usuario', 'apellidos' => 'Eliminado'];
                                    $moto_alquiler = $todas_motos[$alquiler_full['moto_id']] ?? ['marca' => 'Moto', 'modelo' => 'Eliminada'];
                            ?>
                            <tr>
                                <td>#<?php echo $alquiler_full['id']; ?></td>
                                <td><?php echo htmlspecialchars($usuario_alquiler['nombre'] . " " . $usuario_alquiler['apellidos']); ?></td>
                                <td><?php echo $moto_alquiler['marca'] . " " . $moto_alquiler['modelo']; ?></td>
                                <td>
                                    <?php echo date("d/m/Y", strtotime($alquiler_full['fecha_inicio'])); ?> - 
                                    <?php echo date("d/m/Y", strtotime($alquiler_full['fecha_fin'])); ?>
                                </td>
                                <td class="precio"><strong><?php echo $alquiler_full['precio_total']; ?>€</strong></td>
                                <td><span class="etiqueta estado-alquiler <?php echo $alquiler_full['estado']; ?>"><?php echo str_replace('_', ' ', strtoupper($alquiler_full['estado'])); ?></span></td>
                                <td>
                                    <a href="detalle_alquiler.php?id=<?php echo $alquiler_full['id']; ?>" class="boton boton-pequeño">Ver Detalles</a>
                                </td>
                            </tr>
                            <?php 
                                } 
                            } else { ?>
                                <!-- Mensaje que se muestra si no hay ningún alquiler en el sistema. -->
                                <tr><td colspan="6" style="text-align:center; color: var(--texto-gris);">No hay alquileres registrados en el sistema.</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </section>

        </div>
    </main>

    <script>
        const calendarEvents = <?php echo json_encode($eventos_calendario, JSON_HEX_TAG); ?>;
        const calendarGrid = document.getElementById('calendarGrid');
        const eventList = document.getElementById('calendarEventList');
        const prevMonthBtn = document.getElementById('prevMonth');
        const nextMonthBtn = document.getElementById('nextMonth');

        let activeDate = new Date();

        function formatDate(date) {
            return date.toISOString().split('T')[0];
        }

        function getDaysInMonth(year, month) {
            return new Date(year, month + 1, 0).getDate();
        }

        function renderCalendar() {
            const year = activeDate.getFullYear();
            const month = activeDate.getMonth();
            const firstDay = new Date(year, month, 1).getDay();
            const daysInMonth = getDaysInMonth(year, month);
            const monthNames = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

            document.querySelector('.calendar-header h2').textContent = `Calendario de Reservas — ${monthNames[month]} ${year}`;

            calendarGrid.innerHTML = '';

            const dayLabels = ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
            dayLabels.forEach(label => {
                const cell = document.createElement('div');
                cell.className = 'calendar-day disabled';
                cell.innerHTML = `<strong>${label}</strong>`;
                calendarGrid.appendChild(cell);
            });

            let startOffset = firstDay === 0 ? 6 : firstDay - 1;
            for (let i = 0; i < startOffset; i++) {
                const emptyCell = document.createElement('div');
                emptyCell.className = 'calendar-day disabled';
                calendarGrid.appendChild(emptyCell);
            }

            const eventMap = {};
            calendarEvents.forEach(event => {
                const start = new Date(event.fecha_inicio);
                const end = new Date(event.fecha_fin);
                for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                    const key = formatDate(d);
                    if (!eventMap[key]) eventMap[key] = [];
                    eventMap[key].push(event);
                }
            });

            for (let day = 1; day <= daysInMonth; day++) {
                const date = new Date(year, month, day);
                const formatted = formatDate(date);
                const cell = document.createElement('div');
                cell.className = 'calendar-day';
                cell.innerHTML = `<strong>${day}</strong>`;

                const events = eventMap[formatted] || [];
                events.slice(0, 3).forEach(event => {
                    const eventEl = document.createElement('div');
                    eventEl.className = `calendar-event event-${event.estado}`;
                    eventEl.title = `${event.estado.toUpperCase()}: ${event.moto} (${event.cliente})`;
                    eventEl.textContent = `${event.moto}`;
                    cell.appendChild(eventEl);
                });
                if (events.length > 3) {
                    const moreEl = document.createElement('div');
                    moreEl.className = 'calendar-event';
                    moreEl.style.background = 'rgba(255,255,255,0.12)';
                    moreEl.textContent = `+${events.length - 3} más`;
                    cell.appendChild(moreEl);
                }
                calendarGrid.appendChild(cell);
            }

            renderEventList();
        }

        function renderEventList() {
            const today = new Date();
            const nextEvents = calendarEvents
                .filter(event => new Date(event.fecha_fin) >= today)
                .sort((a, b) => new Date(a.fecha_inicio) - new Date(b.fecha_inicio))
                .slice(0, 8);

            eventList.innerHTML = '';
            if (nextEvents.length === 0) {
                const noEvents = document.createElement('li');
                noEvents.textContent = 'No hay eventos próximos.';
                eventList.appendChild(noEvents);
                return;
            }

            nextEvents.forEach(event => {
                const item = document.createElement('li');
                item.innerHTML = `<strong>${event.moto}</strong> (<em>${event.cliente}</em>)<br><small>${event.fecha_inicio} → ${event.fecha_fin} • ${event.estado.replace('_', ' ')}</small>`;
                eventList.appendChild(item);
            });
        }

        prevMonthBtn.addEventListener('click', () => {
            activeDate.setMonth(activeDate.getMonth() - 1);
            renderCalendar();
        });
        nextMonthBtn.addEventListener('click', () => {
            activeDate.setMonth(activeDate.getMonth() + 1);
            renderCalendar();
        });

        document.addEventListener('DOMContentLoaded', renderCalendar);
    </script>

    <footer class="pie-pagina">
        <p>&copy; 2026 ARUSLAT - Alquiler de Motos</p>
        <p>Proyecto TFG - Ángel Rus Latorre - ASIR</p>
    </footer>

</body>
</html>
<?php
// --- 6. CIERRE DE CONEXIÓN ---
// Es una buena práctica cerrar la conexión a la base de datos al final del script
// para liberar recursos en el servidor.
mysqli_close($conexion); 
?>