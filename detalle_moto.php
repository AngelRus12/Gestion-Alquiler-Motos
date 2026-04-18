<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
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

$id_moto = mysqli_real_escape_string($conexion, $id_moto);

$consulta = "SELECT * FROM motos WHERE id = $id_moto";
$resultado = mysqli_query($conexion, $consulta);
$moto = mysqli_fetch_assoc($resultado);

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
            <h1>ARUSLAT</h1>
            <nav>
                <a href="index">Inicio</a>
                <a href="catalogo">Catálogo</a>
                <?php if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin'): ?>
                    <a href="admin_dashboard" class="nav-destacado" style="color: #ff9800; font-weight: bold;">Panel Admin</a>
                <?php endif; ?>
                <a href="perfil_usuario">Mi Perfil</a> 
                <a href="logout">Cerrar Sesión</a>
            </nav>
        </div>
    </header>

    <main class="container">
        <div class="detalle-grid">
            <div class="detalle-info">
                <div class="detalle-img">
                    <?php if (!empty($moto['imagen'])): ?>
                        <img src="data:image/jpeg;base64,<?php echo base64_encode($moto['imagen']); ?>" alt="Moto">
                    <?php else: ?>
                        <img src="imgs/default.jpg" alt="Sin imagen">
                    <?php endif; ?>
                </div>
                <h1 class="titulo-seccion" style="text-align: left; margin-top: 20px; margin-bottom: 15px;"><?php echo htmlspecialchars($moto['marca'] . " " . $moto['modelo']); ?></h1>
                <p><?php echo htmlspecialchars($moto['descripcion']); ?></p>
                <div class="perfil-container" style="margin-top: 20px; padding: 25px;">
                    <p>• Matricula: <strong><?php echo $moto['matricula']; ?> </strong></p>
                    <p>• Año: <strong><?php echo $moto['año']; ?> </strong></p>
                    <p>• Cilindrada: <strong><?php echo $moto['cilindrada']; ?> cc</strong></p> 
                    <p>• Precio dia: <strong><?php echo $moto['precio_dia']; ?> €</strong></p>
                </div>
            </div>

            <div class="detalle-reserva">
                <div class="reserva-card">
                    <h3>Reserva tu Moto</h3>
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
                                <select name="metodo_pago" required style="width: 100%; padding: 10px; background: #2a1e1a; color: white; border-radius: 6px; border: 1px solid #2d1f1b;">
                                    <option value="tienda">Pago en Tienda</option>
                                    <option value="web">Pago Online con Tarjeta</option>
                                </select>
                            </div>
                            <div class="desglose-precio">
                                <p>Total: <strong id="total_val" class="precio-total-final">0.00</strong> €</p>
                            </div>
                            <button type="submit" class="boton" style="width: 100%; margin-top: 10px;">Reservar</button>
                        </form>
                    <?php else: ?>
                        <p style="color: #f44336; text-align: center; font-weight: bold; font-size: 1.1em;">No disponible actualmente</p>
                        <?php
                        // Si el usuario es admin, mostrar quién la tiene alquilada
                        if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
                            $sql_alquiler_actual = "SELECT u.nombre, u.apellidos, a.fecha_inicio, a.fecha_fin, a.id as alquiler_id
                                                    FROM alquileres a
                                                    JOIN usuarios u ON a.usuario_id = u.id
                                                    WHERE a.moto_id = ? AND a.estado IN ('confirmado', 'en_curso')
                                                    ORDER BY a.fecha_inicio DESC
                                                    LIMIT 1";
                            $stmt_alquiler = mysqli_prepare($conexion, $sql_alquiler_actual);
                            mysqli_stmt_bind_param($stmt_alquiler, "i", $id_moto);
                            mysqli_stmt_execute($stmt_alquiler);
                            $res_alquiler = mysqli_stmt_get_result($stmt_alquiler);

                            if ($alquiler_actual = mysqli_fetch_assoc($res_alquiler)) { ?>
                                <div class="alerta" style="margin-top: 20px; text-align: center; background-color: #2e1a1a; border-color: #c62828;">
                                    <p style="margin:0; color: white;"><strong>Alquilada por:</strong> <a href="detalle_alquiler.php?id=<?php echo $alquiler_actual['alquiler_id']; ?>" style="color: var(--naranja-principal); text-decoration: underline;"><?php echo htmlspecialchars($alquiler_actual['nombre'] . ' ' . $alquiler_actual['apellidos']); ?></a></p>
                                    <p style="margin:5px 0 0 0; font-size: 0.9em;">Del <?php echo date('d/m/Y', strtotime($alquiler_actual['fecha_inicio'])); ?> al <?php echo date('d/m/Y', strtotime($alquiler_actual['fecha_fin'])); ?></p>
                                </div>
                            <?php }
                            mysqli_stmt_close($stmt_alquiler);
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
            const total_display = document.getElementById('total_val');
            const precio_dia = parseFloat(document.getElementById('precio_dia_val').value);

            // 1. Establecer el mínimo de la fecha de fin basado en la fecha de inicio
            if (f_inicio_input.value) {
                f_fin_input.min = f_inicio_input.value;
            }

            const d1 = new Date(f_inicio_input.value);
            const d2 = new Date(f_fin_input.value);

            if (f_inicio_input.value && f_fin_input.value) {
                // 2. Validar que la fecha fin sea mayor o igual a la de inicio
                if (d2 >= d1) {
                    const milisegundosPorDia = 1000 * 60 * 60 * 24;
                    // Calculamos la diferencia y sumamos 1 para incluir el día de inicio
                    const dias = Math.floor((d2 - d1) / milisegundosPorDia) + 1;
                    total_display.textContent = (dias * precio_dia).toFixed(2);
                    f_fin_input.style.borderColor = "#2d1f1b"; // Reset color si es correcto
                } else {
                    // Si el usuario intenta poner una fecha anterior manualmente
                    total_display.textContent = "0.00";
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

    <?php if (isset($_SESSION['usuario_id'])): ?>
    <script src="logout_session.js"></script>
    <?php endif; ?>
</body>
</html>