<?php
/**
 * admin_dashboard.php
 * Panel administrativo para gestionar usuarios, motos, reservas y promociones.
 * - Solo accesible por admin.
 * - Actualiza estados de alquiler automáticamente y muestra estadísticas clave.
 * - Permite publicar promociones con fechas y estado activo.
 */
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

// --- 6. DATOS PARA EL CALENDARIO DE RESERVAS ---
// Primero, cojo todos los alquileres de la base de datos.
$resultado_eventos = mysqli_query($conexion, "SELECT * FROM alquileres ORDER BY fecha_inicio ASC");
$eventos_calendario = [];
// Luego, recorro cada alquiler uno por uno.
while ($evento = mysqli_fetch_assoc($resultado_eventos)) {
    // Por cada alquiler, hago una consulta para buscar el nombre del usuario que lo reservó.
    $stmt_u = mysqli_prepare($conexion, "SELECT nombre, apellidos FROM usuarios WHERE id = ?");
    mysqli_stmt_bind_param($stmt_u, "i", $evento['usuario_id']);
    mysqli_stmt_execute($stmt_u);
    // Si el usuario ya no existe, pongo un nombre genérico para que no dé error.
    $usuario_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_u)) ?: ['nombre' => 'Usuario', 'apellidos' => 'Eliminado'];
    mysqli_stmt_close($stmt_u);

    // Hago lo mismo para la moto: busco su marca y modelo.
    $stmt_m = mysqli_prepare($conexion, "SELECT marca, modelo FROM motos WHERE id = ?");
    mysqli_stmt_bind_param($stmt_m, "i", $evento['moto_id']);
    mysqli_stmt_execute($stmt_m);
    // Si la moto fue eliminada, también pongo un texto genérico.
    $moto_info = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_m)) ?: ['marca' => 'Moto', 'modelo' => 'Eliminada'];
    mysqli_stmt_close($stmt_m);

    $eventos_calendario[] = [
        'id'           => $evento['id'],
        'fecha_inicio' => $evento['fecha_inicio'],
        'fecha_fin'    => $evento['fecha_fin'],
        'estado'       => $evento['estado'],
        'cliente'      => trim($usuario_info['nombre'] . ' ' . $usuario_info['apellidos']),
        'moto'         => trim($moto_info['marca'] . ' ' . $moto_info['modelo']),
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
                            <?php while ($user = mysqli_fetch_assoc($resultado_usuarios)) { ?>
                            <tr>
                                <td><?php echo htmlspecialchars($user['id']); ?></td>
                                <td><?php echo htmlspecialchars($user['nombre'] . " " . $user['apellidos']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td><span class="etiqueta etiqueta-azul"><?php echo strtoupper(htmlspecialchars($user['rol'])); ?></span></td>
                                <td>
                                    <?php 
                                    // Se asigna una clase CSS diferente según el estado del usuario para darle un color distintivo.
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
                                            // Si la moto no está disponible, tengo que averiguar quién la tiene.
                                            // Primero, busco en 'alquileres' si hay alguna reserva activa para esta moto.
                                            $sql_alquiler_moto = "SELECT usuario_id, id as alquiler_id FROM alquileres WHERE moto_id = ? AND estado IN ('confirmado', 'en_curso') LIMIT 1";
                                            $stmt_alquiler_moto = mysqli_prepare($conexion, $sql_alquiler_moto);
                                            mysqli_stmt_bind_param($stmt_alquiler_moto, "i", $moto['id']);
                                            mysqli_stmt_execute($stmt_alquiler_moto);
                                            $res_alquiler_moto = mysqli_stmt_get_result($stmt_alquiler_moto);

                                            // Si encuentro un alquiler activo...
                                            if ($alquiler_info = mysqli_fetch_assoc($res_alquiler_moto)) {
                                                // ...uso el ID del usuario de ese alquiler para buscar su nombre en la tabla 'usuarios'.
                                                $sql_usuario_alquila = "SELECT nombre, apellidos FROM usuarios WHERE id = ?";
                                                $stmt_usuario_alquila = mysqli_prepare($conexion, $sql_usuario_alquila);
                                                mysqli_stmt_bind_param($stmt_usuario_alquila, "i", $alquiler_info['usuario_id']);
                                                mysqli_stmt_execute($stmt_usuario_alquila);
                                                $res_usuario_alquila = mysqli_stmt_get_result($stmt_usuario_alquila);
                                                // Finalmente, si encuentro al usuario, muestro su nombre con un enlace al detalle del alquiler.
                                                if ($quien_alquila = mysqli_fetch_assoc($res_usuario_alquila)) {
                                                    echo '<a href="detalle_alquiler.php?id=' . htmlspecialchars($alquiler_info['alquiler_id']) . '" class="boton boton-secundario boton-pequeño mt-5">👤 ' . htmlspecialchars($quien_alquila['nombre'] . ' ' . $quien_alquila['apellidos']) . '</a>';
                                                }
                                                mysqli_stmt_close($stmt_usuario_alquila);
                                            }
                                            mysqli_stmt_close($stmt_alquiler_moto);
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
                            // Aquí busco todos los alquileres que están pendientes de pago en tienda.
                            $res_pendientes_tabla = mysqli_query($conexion, "SELECT * FROM alquileres WHERE estado = 'pendiente' ORDER BY fecha_reserva DESC");

                            if (mysqli_num_rows($res_pendientes_tabla) > 0) {
                                // Recorro cada alquiler pendiente.
                                while ($alquiler = mysqli_fetch_assoc($res_pendientes_tabla)) {
                                    // Por cada uno, busco el nombre del cliente.
                                    $stmt_u_pen = mysqli_prepare($conexion, "SELECT nombre, apellidos FROM usuarios WHERE id = ?");
                                    mysqli_stmt_bind_param($stmt_u_pen, "i", $alquiler['usuario_id']);
                                    mysqli_stmt_execute($stmt_u_pen);
                                    $user_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_u_pen)) ?: ['nombre' => 'Usuario', 'apellidos' => 'Eliminado'];
                                    mysqli_stmt_close($stmt_u_pen);

                                    // Y también busco el nombre de la moto.
                                    $stmt_m_pen = mysqli_prepare($conexion, "SELECT marca, modelo FROM motos WHERE id = ?");
                                    mysqli_stmt_bind_param($stmt_m_pen, "i", $alquiler['moto_id']);
                                    mysqli_stmt_execute($stmt_m_pen);
                                    $moto_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_m_pen)) ?: ['marca' => 'Moto', 'modelo' => 'Eliminada'];
                                    mysqli_stmt_close($stmt_m_pen);
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
                            } else { ?>
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
                            // Para la tabla general, cojo absolutamente todos los alquileres del sistema.
                            $res_alquileres_todos = mysqli_query($conexion, "SELECT * FROM alquileres ORDER BY fecha_reserva DESC");

                            if (mysqli_num_rows($res_alquileres_todos) > 0) {
                                // Recorro cada alquiler que he encontrado.
                                while ($alquiler = mysqli_fetch_assoc($res_alquileres_todos)) {
                                    // Igual que antes, busco el nombre del usuario para esta fila.
                                    $stmt_u_all = mysqli_prepare($conexion, "SELECT nombre, apellidos FROM usuarios WHERE id = ?");
                                    mysqli_stmt_bind_param($stmt_u_all, "i", $alquiler['usuario_id']);
                                    mysqli_stmt_execute($stmt_u_all);
                                    $usuario_alquiler = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_u_all)) ?: ['nombre' => 'Usuario', 'apellidos' => 'Eliminado'];
                                    mysqli_stmt_close($stmt_u_all);

                                    // Y también el nombre de la moto.
                                    $stmt_m_all = mysqli_prepare($conexion, "SELECT marca, modelo FROM motos WHERE id = ?");
                                    mysqli_stmt_bind_param($stmt_m_all, "i", $alquiler['moto_id']);
                                    mysqli_stmt_execute($stmt_m_all);
                                    $moto_alquiler = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_m_all)) ?: ['marca' => 'Moto', 'modelo' => 'Eliminada'];
                                    mysqli_stmt_close($stmt_m_all);
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