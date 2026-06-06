<?php
/**
 * modificar_reserva.php
 * Página para que un usuario modifique las fechas de su reserva.
 * - Valido que el usuario sea el propietario y que se cumpla la regla de las 48h.
 * - Muestro un formulario con los datos actuales y un calendario para elegir nuevas fechas.
 * - El calendario que he implementado deshabilita las fechas ya ocupadas por otras reservas para esa moto.
 */
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'loginbd.php';
require_once 'funciones.php';

// --- 1. CONTROL DE ACCESO Y VALIDACIÓN INICIAL ---
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: perfil_usuario.php?error=no_id');
    exit();
}

$id_alquiler = (int)$_GET['id'];
$id_usuario = $_SESSION['usuario_id'];

// --- 2. CONEXIÓN A LA BASE DE DATOS ---
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
if (!$conexion) {
    die("Error de conexión a la base de datos.");
}
mysqli_set_charset($conexion, "utf8");

// --- 3. OBTENER DATOS Y VALIDAR PROPIEDAD Y REGLAS DE NEGOCIO ---
$sql_alquiler = "SELECT * FROM alquileres WHERE id = ? AND usuario_id = ?";
$stmt = mysqli_prepare($conexion, $sql_alquiler);
mysqli_stmt_bind_param($stmt, "ii", $id_alquiler, $id_usuario);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$alquiler = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt);

if (!$alquiler) { // El alquiler no existe o no pertenece al usuario.
    header('Location: perfil_usuario.php?error=no_encontrado');
    exit();
}

// Valido que el estado sea modificable.
if (!in_array($alquiler['estado'], ['pendiente', 'confirmado'])) {
    header('Location: perfil_usuario.php?error=no_modificable');
    exit();
}

// Valido la regla de negocio de las 48 horas.
if (strtotime($alquiler['fecha_inicio']) <= strtotime('+48 hours')) {
    header('Location: perfil_usuario.php?error=modificacion_fuera_plazo');
    exit();
}

// --- 4. OBTENER DATOS ADICIONALES PARA EL FORMULARIO ---
// Datos de la moto
$sql_moto = "SELECT marca, modelo, imagen, precio_dia FROM motos WHERE id = ?"; // Obtengo los datos de la moto.
$stmt_moto = mysqli_prepare($conexion, $sql_moto);
mysqli_stmt_bind_param($stmt_moto, "i", $alquiler['moto_id']);
mysqli_stmt_execute($stmt_moto);
$moto = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_moto));
mysqli_stmt_close($stmt_moto);

// Obtengo las fechas de otras reservas para esta moto y se las paso a JavaScript.
$sql_reservas = "SELECT fecha_inicio, fecha_fin FROM alquileres WHERE moto_id = ? AND id != ? AND estado IN ('confirmado', 'en_curso')";
$stmt_reservas = mysqli_prepare($conexion, $sql_reservas);
mysqli_stmt_bind_param($stmt_reservas, "ii", $alquiler['moto_id'], $id_alquiler);
mysqli_stmt_execute($stmt_reservas);
$resultado_reservas = mysqli_stmt_get_result($stmt_reservas);
$fechas_ocupadas = [];
while ($reserva = mysqli_fetch_assoc($resultado_reservas)) {
    $fechas_ocupadas[] = [
        'from' => $reserva['fecha_inicio'],
        'to' => $reserva['fecha_fin']
    ];
}
mysqli_stmt_close($stmt_reservas);

$is_mobile = preg_match("/(android|avantgo|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino)/i", $_SERVER["HTTP_USER_AGENT"]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Modificar Reserva - ARUSLAT</title>
    <link rel="icon" href="logo.png" type="image/png">
    <?php if ($is_mobile): ?>
        <link rel="stylesheet" href="estilos_mobile.css">
    <?php else: ?>
        <link rel="stylesheet" href="estilos.css">
    <?php endif; ?>
    <!-- Incluir Flatpickr para el calendario -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
</head>
<body>
    <?php include 'header.php'; ?>

    <main class="main-content">
        <div class="container">
            <div class="formulario-admin" style="max-width: 700px;">
                <h2 class="titulo-formulario">Modificar Reserva</h2>
                
                <div class="moto-detalle-card" style="margin-bottom: 30px;">
                    <?php
                    $imagen_src = 'imgs/default.jpg';
                    if (!empty($moto['imagen'])) {
                        $imagen_src = 'data:image/jpeg;base64,' . base64_encode($moto['imagen']);
                    }
                    ?>
                    <img src="<?php echo htmlspecialchars($imagen_src); ?>" alt="Moto" style="height: 150px; object-fit: cover;">
                    <h3><?php echo htmlspecialchars($moto['marca'] . ' ' . $moto['modelo']); ?></h3>
                    <p><strong>Precio base:</strong> <?php echo htmlspecialchars($moto['precio_dia']); ?>€/día</p>
                </div>

                <form action="procesar_modificacion_reserva.php" method="POST">
                    <input type="hidden" name="id_alquiler" value="<?php echo $id_alquiler; ?>">
                    
                    <div class="form-group">
                        <label>Fechas Actuales</label>
                        <p><?php echo htmlspecialchars(date("d/m/Y", strtotime($alquiler['fecha_inicio']))) . " al " . htmlspecialchars(date("d/m/Y", strtotime($alquiler['fecha_fin']))); ?></p>
                    </div>

                    <div class="form-group">
                        <label for="nuevas_fechas">Selecciona las Nuevas Fechas *</p></label>
                        <input type="text" id="nuevas_fechas" name="nuevas_fechas" required placeholder="Haz clic para elegir el nuevo rango de fechas">
                    </div>

                    <div id="resumen-precio" style="display: none; margin-top: 20px;">
                        <h4>Nuevo Resumen de Precio</h4>
                        <p><strong>Días seleccionados:</strong> <span id="dias-seleccionados">0</span></p>
                        <p class="precio-final"><strong>Nuevo Precio Total:</strong> <span id="nuevo-precio-total">0.00</span>€</p>
                    </div>

                    <div class="form-submit-group" style="margin-top: 30px;">
                        <button type="submit" class="btn">Confirmar Modificación</button>
                        <a href="perfil_usuario.php" class="boton boton-secundario" style="margin-left: 10px;">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <?php include 'footer.php'; ?>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const precioPorDia = <?php echo floatval($moto['precio_dia']); ?>;
        const fechasOcupadas = <?php echo json_encode($fechas_ocupadas); ?>;

        const fp = flatpickr("#nuevas_fechas", {
            mode: "range",
            dateFormat: "Y-m-d",
            minDate: "today",
            disable: fechasOcupadas,
            locale: {
                firstDayOfWeek: 1, // Lunes
                weekdays: {
                    shorthand: ["Dom", "Lun", "Mar", "Mié", "Jue", "Vie", "Sáb"],
                    longhand: ["Domingo", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"],
                },
                months: {
                    shorthand: ["Ene", "Feb", "Mar", "Abr", "May", "Jun", "Jul", "Ago", "Sep", "Oct", "Nov", "Dic"],
                    longhand: ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"],
                },
            },
            onChange: function(selectedDates) {
                const resumenDiv = document.getElementById('resumen-precio');
                if (selectedDates.length === 2) {
                    const inicio = selectedDates[0];
                    const fin = selectedDates[1];
                    
                    // Calcular diferencia en días
                    const diffTime = Math.abs(fin - inicio);
                    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;

                    const nuevoTotal = diffDays * precioPorDia;

                    document.getElementById('dias-seleccionados').textContent = diffDays;
                    document.getElementById('nuevo-precio-total').textContent = nuevoTotal.toFixed(2);
                    resumenDiv.style.display = 'block';
                } else {
                    resumenDiv.style.display = 'none';
                }
            }
        });
    });
    </script>

</body>
</html>
<?php
mysqli_close($conexion);
?>