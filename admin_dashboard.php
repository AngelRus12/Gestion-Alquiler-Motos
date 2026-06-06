<?php
/**
 * admin_dashboard.php
 * Panel administrativo para gestionar usuarios, motos, reservas y promociones.
 * - Lo he diseñado para que sea accesible solo por administradores.
 * - Antes de mostrar nada, actualizo los estados de los alquileres y muestro estadísticas clave.
 * - Desde aquí permito publicar promociones con fechas y estado activo.
 */
// Establece la codificación de caracteres a UTF-8 para soportar caracteres especiales.
header('Content-Type: text/html; charset=utf-8');
// Inicio la sesión para poder acceder a las variables de sesión.
session_start();
// Incluye el archivo con las credenciales de la base de datos.
require_once 'loginbd.php';
// Incluye el archivo que contiene funciones reutilizables, como la de actualizar el sistema.
require_once 'funciones.php';

// --- 1. CONTROL DE ACCESO ---
// Verifico si el usuario ha iniciado sesión y si tiene el rol de 'admin'.
// Si no cumple las condiciones, lo redirijo a la página de login con un mensaje de error.
if (!isset($_SESSION['usuario_id']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

// --- 2. CONEXIÓN A LA BASE DE DATOS ---
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
// Si la conexión falla, detengo la ejecución y muestro un error.
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}
// Establezco el conjunto de caracteres a UTF-8 para la conexión con la base de datos.
mysqli_set_charset($conexion, "utf8");

// --- 3. ACTUALIZACIÓN DEL SISTEMA ---
// Llamo a mi función centralizada que actualiza el estado de alquileres y motos.
// Por ejemplo, esta función pasa alquileres 'confirmados' a 'en_curso' si la fecha de inicio ya ha llegado.
actualizar_sistema_completo($conexion);

// --- 4. GESTIÓN DE MENSAJES DE FEEDBACK ---
// Compruebo la URL en busca de parámetros 'msg' (éxito) o 'error' para mostrarle alertas al administrador.
$mensaje_html = "";
if (isset($_GET['msg'])) {
    // Uso mensajes genéricos para cubrir diferentes operaciones (crear, actualizar, eliminar).
    $mensaje_html = "<div class='alerta alerta-exito'>Operación realizada con éxito.</div>";
}
if (isset($_GET['error'])) {
    // Aquí manejo un caso específico: el error de auto-eliminación.
    if ($_GET['error'] == 'autodelecion') {
        $mensaje_html = "<div class='alerta alerta-error'>No puedes eliminar tu propia cuenta.</div>";
    } else {
        // Y un mensaje de error genérico para otras situaciones.
        $mensaje_html = "<div class='alerta alerta-error'>Hubo un error al procesar la solicitud.</div>";
    }
}

// --- 5. CONSULTAS PARA LAS TARJETAS DE RESUMEN (OVERVIEW) ---
// Se realizan 3 consultas separadas para obtener los totales de usuarios, motos y alquileres pendientes.
$total_users_res = mysqli_query($conexion, "SELECT COUNT(*) as total FROM usuarios");
$total_motos_res = mysqli_query($conexion, "SELECT COUNT(*) as total FROM motos");
$res_pendientes_res = mysqli_query($conexion, "SELECT COUNT(*) as total FROM alquileres WHERE estado = 'pendiente'");

// Extraigo el valor 'total' de cada resultado.
$total_users = mysqli_fetch_assoc($total_users_res)['total'] ?? 0;
$total_motos = mysqli_fetch_assoc($total_motos_res)['total'] ?? 0;
$total_pendientes_res = mysqli_query($conexion, "SELECT COUNT(*) as total FROM alquileres WHERE estado = 'pendiente'");
$total_pendientes = mysqli_fetch_assoc($total_pendientes_res)['total'] ?? 0;

// --- 5.1. LÓGICA DE PAGINACIÓN ---
$limit = 10; // Límite de registros por página

// Paginación para Usuarios
$page_users = isset($_GET['page_users']) ? (int)$_GET['page_users'] : 1;
$offset_users = ($page_users - 1) * $limit;
$total_pages_users = ceil($total_users / $limit);

// Paginación para Motos
$page_motos = isset($_GET['page_motos']) ? (int)$_GET['page_motos'] : 1;
$offset_motos = ($page_motos - 1) * $limit;
$total_pages_motos = ceil($total_motos / $limit);

// Paginación para Alquileres
$total_alquileres_res = mysqli_query($conexion, "SELECT COUNT(*) as total FROM alquileres");
$total_alquileres = mysqli_fetch_assoc($total_alquileres_res)['total'] ?? 0;
$page_alquileres = isset($_GET['page_alquileres']) ? (int)$_GET['page_alquileres'] : 1;
$offset_alquileres = ($page_alquileres - 1) * $limit;
$total_pages_alquileres = ceil($total_alquileres / $limit);

// --- 6. OPTIMIZACIÓN DE CONSULTAS (N+1) PARA TABLAS Y CALENDARIO ---
// En lugar de hacer consultas dentro de bucles, obtengo todos los datos que necesito al principio.

// a) Obtener los alquileres para la página actual y para el calendario
$stmt_alquileres = mysqli_prepare($conexion, "SELECT * FROM alquileres ORDER BY fecha_reserva DESC LIMIT ? OFFSET ?");
mysqli_stmt_bind_param($stmt_alquileres, "ii", $limit, $offset_alquileres);
mysqli_stmt_execute($stmt_alquileres);
$resultado_alquileres = mysqli_stmt_get_result($stmt_alquileres);
$alquileres_todos = mysqli_fetch_all($resultado_alquileres, MYSQLI_ASSOC);

// b) Obtener todos los usuarios y motos
$stmt_usuarios = mysqli_prepare($conexion, "SELECT id, nombre, apellidos, email, rol, estado FROM usuarios ORDER BY id DESC LIMIT ? OFFSET ?");
mysqli_stmt_bind_param($stmt_usuarios, "ii", $limit, $offset_users);
mysqli_stmt_execute($stmt_usuarios);
$resultado_usuarios = mysqli_stmt_get_result($stmt_usuarios);
$usuarios_todos = mysqli_fetch_all($resultado_usuarios, MYSQLI_ASSOC);

$resultado_motos_tabla = mysqli_query($conexion, "SELECT id, marca, modelo, precio_dia, disponible, tipo, imagen FROM motos ORDER BY id DESC LIMIT $limit OFFSET $offset_motos");
$motos_todas = mysqli_fetch_all($resultado_motos_tabla, MYSQLI_ASSOC);

// c) Creo "mapas" para un acceso rápido a los datos, así no necesito hacer nuevas consultas dentro de los bucles.
$usuarios_map = [];
foreach ($usuarios_todos as $usuario) {
    $usuarios_map[$usuario['id']] = $usuario;
}

$motos_map = [];
foreach ($motos_todas as $moto) {
    $motos_map[$moto['id']] = $moto;
}

// d) Preparo los datos para el calendario (para esto, obtengo todos los alquileres, sin paginación).
$eventos_calendario = [];
$resultado_alquileres_calendario = mysqli_query($conexion, "SELECT * FROM alquileres ORDER BY fecha_reserva DESC");
$alquileres_calendario = mysqli_fetch_all($resultado_alquileres_calendario, MYSQLI_ASSOC);

foreach ($alquileres_calendario as $evento) {
    // Busco el usuario en mi mapa. Si no existe (porque fue eliminado), uso valores por defecto.
    $usuario_info = $usuarios_map[$evento['usuario_id']] ?? ['nombre' => 'Usuario', 'apellidos' => 'Eliminado'];
    
    // Hago lo mismo para la moto.
    $moto_info = $motos_map[$evento['moto_id']] ?? ['marca' => 'Moto', 'modelo' => 'Eliminada'];
    
    $eventos_calendario[] = [
        'id'           => $evento['id'],
        'fecha_inicio' => $evento['fecha_inicio'],
        'fecha_fin'    => $evento['fecha_fin'],
        'estado'       => $evento['estado'],
        'cliente'      => trim($usuario_info['nombre'] . ' ' . $usuario_info['apellidos']),
        'moto'         => trim($moto_info['marca'] . ' ' . $moto_info['modelo']),
    ];
}

// Esta es una función local para generar los enlaces de paginación.
function generar_paginacion($page, $total_pages, $base_url) {
    if ($total_pages <= 1) return;

    echo '<div class="paginacion">';
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

            <h2 class="titulo-seccion titulo-seccion-left">Resumen Overview</h2>

            <div class="admin-stats-grid">
                <div class="stat-card">
                    <div class="info">
                        <h4>Usuarios Totales</h4>
                        <p class="number"><?php echo htmlspecialchars($total_users); ?></p>
                    </div>
                    <div class="stat-icon">👤</div>
                </div>
                <div class="stat-card">
                    <div class="info">
                        <h4>Catálogo de Motos</h4>
                        <p class="number"><?php echo htmlspecialchars($total_motos); ?></p>
                    </div>
                    <div class="stat-icon">🏍️</div>
                </div>
                <div class="stat-card">
                    <div class="info">
                        <h4>Pagos Pendientes</h4>
                        <p class="number"><?php echo htmlspecialchars($total_pendientes); ?></p>
                    </div>
                    <div class="stat-icon">💰</div>
                </div>
            </div>

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
                    <span class="legend-item"><span class="legend-badge pendiente"></span>Pendiente</span>
                    <span class="legend-item"><span class="legend-badge confirmado"></span>Confirmado</span>
                    <span class="legend-item"><span class="legend-badge en_curso"></span>En curso</span>
                    <span class="legend-item"><span class="legend-badge finalizado"></span>Finalizado</span>
                    <span class="legend-item"><span class="legend-badge cancelado"></span>Cancelado</span>
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
                            <?php foreach ($usuarios_todos as $user) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                <td><?php echo htmlspecialchars($user['nombre'] . " " . $user['apellidos']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><span class="etiqueta etiqueta-azul"><?php echo strtoupper(htmlspecialchars($user['rol'])); ?></span></td>
                                <td>
                                    <?php 
                                    // Asigno una clase CSS diferente según el estado del usuario para darle un color distintivo.
                                    $clase_estado = 'etiqueta-error';
                                    if ($user['estado'] == 'activo') {
                                        $clase_estado = 'etiqueta-exito';
                                    }
                                    ?>
                                    <span class="etiqueta <?php echo htmlspecialchars($clase_estado); ?>">
                                        <?php echo strtoupper(htmlspecialchars($user['estado'])); ?>
                                    </span>
                                </td>
                                <td class="nowrap">
                                    <a href="editar_usuario.php?id=<?php echo htmlspecialchars($user['id']); ?>" class="boton boton-pequeño">Editar</a>
                                    <a href="eliminar_usuario.php?id=<?php echo htmlspecialchars($user['id']); ?>" class="boton boton-pequeño etiqueta-error">Eliminar</a>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php
                // Generar URL base para paginación de usuarios
                $params = $_GET;
                unset($params['page_users']);
                $base_url_users = 'admin_dashboard.php?' . http_build_query($params) . '&page_users=';
                generar_paginacion($page_users, $total_pages_users, $base_url_users);
                ?>
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
                            <?php foreach ($motos_todas as $moto) { ?>
                            <tr>
                                <td>
                                    <?php 
                                    $imagen_src = 'imgs/default.jpg';
                                    if (!empty($moto['imagen'])) {
                                        $imagen_src = 'data:image/jpeg;base64,' . base64_encode($moto['imagen']);
                                    }
                                    ?>
                                    <img src="<?php echo htmlspecialchars($imagen_src); ?>" alt="Moto" class="miniatura-admin">
                                </td>
                                <td><strong class="text-white"><?php echo htmlspecialchars($moto['marca'] . " " . $moto['modelo']); ?></strong></td>
                                <td><?php echo strtoupper(htmlspecialchars($moto['tipo'])); ?></td>
                                <td class="precio"><?php echo htmlspecialchars($moto['precio_dia']); ?>€</td>
                                <td>
                                    <?php if ($moto['disponible'] == 1 || $moto['disponible'] == 'si') { ?>
                                        <span class="etiqueta etiqueta-exito">Disponible</span>
                                    <?php } else { ?>
                                        <div class="info-alquilada">
                                            <span class="etiqueta etiqueta-aviso">Alquilada</span>
                                            <?php
                                            // Busco un alquiler activo para esta moto en los datos que ya tengo.
                                            foreach ($alquileres_todos as $alquiler_activo) {
                                                if ($alquiler_activo['moto_id'] == $moto['id'] && in_array($alquiler_activo['estado'], ['confirmado', 'en_curso'])) {
                                                    // Uso el mapa de usuarios para obtener el nombre.
                                                    $quien_alquila = $usuarios_map[$alquiler_activo['usuario_id']] ?? null;
                                                    if ($quien_alquila) {
                                                        echo '<a href="detalle_alquiler.php?id=' . htmlspecialchars($alquiler_activo['id']) . '" class="boton boton-secundario boton-pequeño mt-5">👤 ' . htmlspecialchars($quien_alquila['nombre'] . ' ' . $quien_alquila['apellidos']) . '</a>';
                                                    }
                                                    break; // Solo necesitamos mostrar un usuario
                                                }
                                            }
                                            ?>
                                        </div>
                                    <?php } ?>
                                </td>
                                <td class="nowrap">
                                    <a href="editar_moto.php?id=<?php echo htmlspecialchars($moto['id']); ?>" class="boton boton-pequeño">Editar</a>
                                    <a href="eliminar_moto.php?id=<?php echo htmlspecialchars($moto['id']); ?>" class="boton boton-pequeño etiqueta-error">Eliminar</a>
                                </td>
                            </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php
                // Generar URL base para paginación de motos
                $params = $_GET;
                unset($params['page_motos']);
                $base_url_motos = 'admin_dashboard.php?' . http_build_query($params) . '&page_motos=';
                generar_paginacion($page_motos, $total_pages_motos, $base_url_motos);
                ?>
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
                            $hay_pendientes = false;
                            // Para los pendientes, recorro todos los alquileres del calendario, no solo los que están paginados.
                            foreach ($alquileres_calendario as $alquiler) {
                                if ($alquiler['estado'] === 'pendiente') {
                                    $hay_pendientes = true;
                                    // Uso los mapas para obtener los datos sin hacer nuevas consultas.
                                    $user_data = $usuarios_map[$alquiler['usuario_id']] ?? ['nombre' => 'Usuario', 'apellidos' => 'Eliminado'];
                                    $moto_data = $motos_map[$alquiler['moto_id']] ?? ['marca' => 'Moto', 'modelo' => 'Eliminada'];
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user_data['nombre'] . " " . $user_data['apellidos']); ?></td>
                                <td><?php echo htmlspecialchars($moto_data['marca'] . " " . $moto_data['modelo']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars(date("d/m/Y", strtotime($alquiler['fecha_inicio']))); ?> - 
                                    <?php echo htmlspecialchars(date("d/m/Y", strtotime($alquiler['fecha_fin']))); ?>
                                </td>
                                <td class="precio"><strong><?php echo htmlspecialchars($alquiler['precio_total']); ?>€</strong></td>
                                <td>
                                    <!-- Formulario para confirmar el pago en tienda -->
                                    <form action="confirmar_pago_tienda.php" method="GET" class="inline-form">
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($alquiler['id']); ?>">
                                        <button type="submit" class="boton btn-sm etiqueta-exito">
                                            Confirmar Pago
                                        </button>
                                    </form>
                                    <!-- Formulario para que el admin cancele el alquiler -->
                                    <form action="cancelar_alquiler_admin.php" method="GET" class="inline-form ml-10">
                                        <input type="hidden" name="id" value="<?php echo htmlspecialchars($alquiler['id']); ?>">
                                        <button type="submit" class="boton boton-pequeño boton-error">Cancelar</button>
                                    </form>
                                </td>
                            </tr>
                            <?php 
                                }
                            } if (!$hay_pendientes) { // Si no encontré ninguno, muestro un mensaje. ?>
                                <!-- Mensaje que se muestra si no hay alquileres pendientes. -->
                                <tr><td colspan="5" class="table-empty">No hay pagos pendientes de confirmación.</td></tr>
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
                            if (!empty($alquileres_todos)) {
                                foreach ($alquileres_todos as $alquiler) {
                                    // Reutilizo los mapas que ya he creado.
                                    $usuario_alquiler = $usuarios_map[$alquiler['usuario_id']] ?? ['nombre' => 'Usuario', 'apellidos' => 'Eliminado'];
                                    $moto_alquiler = $motos_map[$alquiler['moto_id']] ?? ['marca' => 'Moto', 'modelo' => 'Eliminada'];
                            ?>
                            <tr>
                                <td>#<?php echo htmlspecialchars($alquiler['id']); ?></td>
                                <td><?php echo htmlspecialchars($usuario_alquiler['nombre'] . " " . $usuario_alquiler['apellidos']); ?></td>
                                <td><?php echo htmlspecialchars($moto_alquiler['marca'] . " " . $moto_alquiler['modelo']); ?></td>
                                <td>
                                    <?php echo htmlspecialchars(date("d/m/Y", strtotime($alquiler['fecha_inicio']))); ?> - 
                                    <?php echo htmlspecialchars(date("d/m/Y", strtotime($alquiler['fecha_fin']))); ?>
                                </td>
                                <td class="precio"><strong><?php echo htmlspecialchars($alquiler['precio_total']); ?>€</strong></td>
                                <td><span class="etiqueta estado-alquiler <?php echo htmlspecialchars($alquiler['estado']); ?>"><?php echo htmlspecialchars(str_replace('_', ' ', strtoupper($alquiler['estado']))); ?></span></td>
                                <td>
                                    <a href="detalle_alquiler.php?id=<?php echo htmlspecialchars($alquiler['id']); ?>" class="boton boton-pequeño">Ver Detalles</a>
                                </td>
                            </tr>
                            <?php 
                                }
                            } else { ?>
                                <!-- Mensaje que se muestra si no hay ningún alquiler en el sistema. -->
                                <tr><td colspan="6" class="table-empty">No hay alquileres registrados en el sistema.</td></tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
                <?php
                // Generar URL base para paginación de alquileres
                $params = $_GET;
                unset($params['page_alquileres']);
                $base_url_alquileres = 'admin_dashboard.php?' . http_build_query($params) . '&page_alquileres=';
                generar_paginacion($page_alquileres, $total_pages_alquileres, $base_url_alquileres);
                ?>
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
// Como buena práctica, cierro la conexión a la base de datos al final del script
// para liberar recursos en el servidor.
mysqli_close($conexion); 
?>