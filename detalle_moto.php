<?php
/**
 * detalle_moto.php
 * Esta página muestra la información completa de una moto y permite al usuario reservarla.
 * - Uso consultas preparadas para obtener los datos de forma segura a partir del ID.
 * - He incluido validaciones de disponibilidad y mensajes de error claros para el usuario.
 */
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'funciones.php';
require_once 'loginbd.php';
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}

mysqli_set_charset($conexion, "utf8");

$id_moto = 0;
if (isset($_GET['id'])) {
    $id_moto = (int)$_GET['id'];
}

// Uso una consulta preparada para coger los datos de la moto de forma segura.
// evitando que alguien pueda manipular la URL para atacar la base de datos.
$consulta = "SELECT * FROM motos WHERE id = ?";
$stmt = mysqli_prepare($conexion, $consulta);
// La "i" indica que el parámetro que voy a vincular es un entero (integer).
mysqli_stmt_bind_param($stmt, "i", $id_moto);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$moto = mysqli_fetch_assoc($resultado);

// Si no se encuentra ninguna moto con ese ID, se redirige al catálogo.
if (!$moto) { 
    header('Location: catalogo.php'); 
    exit(); 
}

$esta_disponible = ($moto['disponible'] == 1);

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
    <title><?php echo htmlspecialchars($moto['marca'] . " " . $moto['modelo']); ?> - ARUSLAT</title>
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

    <main class="container">
        <div class="detalle-grid">
            <div class="detalle-info">
                <div class="detalle-precio-header">
                    <p class="precio-grande"><?php echo htmlspecialchars($moto['precio_dia']); ?> €/día</p>
                </div>
                <div class="detalle-img">
                    <img src="data:image/jpeg;base64,<?php echo base64_encode($moto['imagen']); ?>" alt="Moto">
                </div>
                <h1 class="titulo-seccion titulo-detalle-moto"><?php echo htmlspecialchars($moto['marca'] . " " . $moto['modelo']); ?></h1>
                <p><?php echo htmlspecialchars($moto['descripcion']); ?></p>
                <div class="perfil-container detalle-info-specs">
                    <p>• Matrícula: <strong><?php echo htmlspecialchars($moto['matricula']); ?></strong></p>
                    <p>• Año: <strong><?php echo htmlspecialchars($moto['año']); ?></strong></p>
                    <p>• Cilindrada: <strong><?php echo htmlspecialchars($moto['cilindrada']); ?> cc</strong></p>
                    <p>• Tipo: <strong><?php echo htmlspecialchars(ucfirst($moto['tipo'])); ?></strong></p>
                </div>
            </div>

            <div class="detalle-reserva">
                <div class="reserva-card">
                    <h3>Reserva tu Moto</h3>
                    <?php
                    if (isset($_GET['error'])) {
                        $mensaje_error = '';
                        switch ($_GET['error']) {
                            case 'fecha_invalida':
                                $mensaje_error = 'Las fechas seleccionadas no son válidas. Comprueba el intervalo y evita fechas pasadas.';
                                break;
                            case 'fechas_solapadas':
                                $mensaje_error = 'Las fechas elegidas ya están ocupadas para esta moto. Selecciona otro rango o comprueba disponibilidad.';
                                break;
                            case 'reserva':
                                $mensaje_error = 'No se pudo completar la reserva. Intenta nuevamente más tarde.';
                                break;
                        }
                        if ($mensaje_error) {
                            echo '<div class="alerta alerta-error alerta-margen">' . htmlspecialchars($mensaje_error) . '</div>';
                        }
                    }
                    ?>
                    <?php if ($esta_disponible): ?>
                        <form action="procesar_reserva.php" method="POST">
                            <input type="hidden" name="id_moto" value="<?php echo $moto['id']; ?>">
                            <input type="hidden" name="precio_dia" id="precio_dia_val" value="<?php echo $moto['precio_dia']; ?>">

                            <div class="form-group">
                                <label>Fecha Inicio:</label>
                                <input type="date" name="f_inicio" id="f_inicio" required onchange="calcularTotal()">
                            </div>
                            <div class="form-group">
                                <label>Fecha Fin:</label>
                                <input type="date" name="f_fin" id="f_fin" required onchange="calcularTotal()">
                            </div>
                            <div class="form-group">
                                <label>Método de Pago:</label>
                                <select name="metodo_pago" required class="pago-metodo-select">
                                    <option value="tienda">Pago en Tienda</option>
                                    <option value="web">Pago Online con Tarjeta</option>
                                </select>
                            </div>                            
                            <div id="precio-desglose" class="precio-desglose" style="display: none;">
                                <div class="precio-fila">
                                    <span id="etiqueta-precio-base">Precio base</span> <span>(<span id="dias-seleccionados">0</span> días)</span>
                                    <span id="precio-base-valor">0.00€</span>
                                </div>
                                
                                <hr class="divider-muted">
                                <div class="precio-fila">
                                    <strong>Precio Total</strong>
                                    <strong id="precio-final" class="precio-total-final">0.00€</strong>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-block espaciado-arriba">Reservar</button>
                        </form>
                    <?php else: ?>
                        <p class="alerta-error text-center alerta-sin-fondo">No disponible actualmente</p>
                        <?php
                        // Si la moto no está disponible y soy yo (un admin) quien la está viendo,
                        // muestro quién la tiene alquilada.
                        if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
                            // Primero, busco si hay un alquiler activo para esta moto.
                            $sql_alquiler_moto = "SELECT usuario_id, fecha_inicio, fecha_fin, id as alquiler_id FROM alquileres WHERE moto_id = ? AND estado IN ('confirmado', 'en_curso') ORDER BY fecha_inicio DESC LIMIT 1";
                            $stmt_alquiler_moto = mysqli_prepare($conexion, $sql_alquiler_moto);
                            mysqli_stmt_bind_param($stmt_alquiler_moto, "i", $id_moto);
                            mysqli_stmt_execute($stmt_alquiler_moto);
                            $res_alquiler_moto = mysqli_stmt_get_result($stmt_alquiler_moto);

                            // Si lo encuentro...
                            if ($alquiler_info = mysqli_fetch_assoc($res_alquiler_moto)) {
                                // ...uso el ID de ese usuario para buscar su nombre.
                                $sql_usuario_alquila = "SELECT nombre, apellidos FROM usuarios WHERE id = ?";
                                $stmt_usuario_alquila = mysqli_prepare($conexion, $sql_usuario_alquila);
                                mysqli_stmt_bind_param($stmt_usuario_alquila, "i", $alquiler_info['usuario_id']);
                                mysqli_stmt_execute($stmt_usuario_alquila);
                                $res_usuario_alquila = mysqli_stmt_get_result($stmt_usuario_alquila);
                                
                                // Y si encuentro al usuario, muestro su nombre y las fechas del alquiler.
                                if ($quien_alquila = mysqli_fetch_assoc($res_usuario_alquila)) {
                                    $alquiler_actual = array_merge($alquiler_info, $quien_alquila);
                                    ?>
                                    <!-- Muestro la información combinada en el HTML. -->
                                    <div class="alerta alerta-error text-center espaciado-arriba">
                                        <p class="detalle-alquilada-text"><strong>Alquilada por:</strong> <a href="detalle_alquiler.php?id=<?php echo $alquiler_actual['alquiler_id']; ?>" class="enlace-discreto"><?php echo htmlspecialchars($alquiler_actual['nombre'] . ' ' . $alquiler_actual['apellidos']); ?></a></p>
                                        <p class="detalle-alquiler-periodo">Del <?php echo date('d/m/Y', strtotime($alquiler_actual['fecha_inicio'])); ?> al <?php echo date('d/m/Y', strtotime($alquiler_actual['fecha_fin'])); ?></p>
                                    </div>
                                <?php }
                                mysqli_stmt_close($stmt_usuario_alquila);
                            }
                            mysqli_stmt_close($stmt_alquiler_moto);
                        } ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
    
        function calcularTotal() {
            const f_inicio_input = document.getElementById('f_inicio');
            const f_fin_input = document.getElementById('f_fin');
            const precio_dia = parseFloat(document.getElementById('precio_dia_val').value);
            const moto_id = <?php echo $moto['id']; ?>;

            const precioDesglose = document.getElementById('precio-desglose');
            const diasSeleccionados = document.getElementById('dias-seleccionados');
            const precioBaseValorElement = document.getElementById('precio-base-valor');
            const precioFinalElement = document.getElementById('precio-final');

            // 1. Establecer el mínimo de la fecha de fin basado en la fecha de inicio
            if (f_inicio_input.value) {
                f_fin_input.min = f_inicio_input.value;
            }

            const fechaInicio = new Date(f_inicio_input.value);
            const fechaFin = new Date(f_fin_input.value);

            if (f_inicio_input.value && f_fin_input.value && fechaFin >= fechaInicio) {
                    const milisegundosPorDia = 1000 * 60 * 60 * 24;
                    const dias = Math.floor((fechaFin - fechaInicio) / milisegundosPorDia) + 1;
                    const precioTotal = dias * precio_dia;

                    diasSeleccionados.textContent = dias;
                    precioBaseValorElement.textContent = `${precioTotal.toFixed(2)}€`;
                    precioFinalElement.textContent = precioTotal.toFixed(2) + '€';
                    precioDesglose.style.display = 'block';

            } else {
                precioDesglose.style.display = 'none';
                if (f_fin_input.value && fechaFin < fechaInicio) {
                    f_fin_input.style.borderColor = "#f44336"; // Marca error en rojo
                    alert("La fecha de finalización no puede ser anterior a la de inicio.");
                    f_fin_input.value = ""; // Limpia el campo erróneo
                }
            }
        }

        // Opcional: Impedir que reserven fechas pasadas al cargar la página
        window.onload = function() {
            const hoy = new Date().toISOString().split('T')[0];
            document.getElementById('f_inicio').setAttribute('min', hoy);
            document.getElementById('f_fin').setAttribute('min', hoy);
        };
        
            </script>

    <?php mysqli_stmt_close($stmt); // Cerrar la consulta preparada principal ?>
</body>
</html>