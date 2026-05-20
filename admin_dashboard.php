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