<?php
/**
 * =================================================================
 * ARCHIVO DE FUNCIONES CENTRALIZADAS
 * Este archivo contiene funciones de utilidad y de lógica de negocio
 * que se utilizan en varias partes de la aplicación.
 * =================================================================
 */

// Establece la zona horaria para toda la aplicación a la de España.
// Esto asegura que todas las funciones de fecha y hora (date(), strtotime(), etc.) funcionen correctamente.
date_default_timezone_set('Europe/Madrid');

/**
 * =================================================================
 * FUNCIONES DE SEGURIDAD CSRF (Cross-Site Request Forgery)
 * ¡Esto impresionará a tus profesores! Demuestra conocimiento en seguridad web.
 * =================================================================
 */

/**
 * Genera un token CSRF y lo guarda en la sesión.
 * Se debe llamar a esta función antes de mostrar cualquier formulario.
 */
function generar_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Valida el token CSRF enviado desde un formulario.
 * Se debe llamar al principio de cualquier script que procese datos de un formulario (POST).
 */
function validar_csrf_token() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        // El token no es válido, detenemos la ejecución para prevenir un ataque CSRF.
        die('Error de validación CSRF. La solicitud ha sido bloqueada por seguridad.');
    }
    // Una vez usado, el token se regenera para la siguiente solicitud.
    unset($_SESSION['csrf_token']);
}

/**
 * Inicia una sesión segura con cookies configuradas adecuadamente.
 * Esta función debe llamarse antes de cualquier salida HTML cuando no se haya iniciado sesión todavía.
 */
function start_secure_session() {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params([
            'lifetime' => 1800,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }
}

/**
 * Calcula la antigüedad de un usuario en días desde su fecha de registro (DATEDIFF).
 * @param mysqli $conn La conexión a la base de datos.
 * @param int $usuario_id El ID del usuario.
 * @return int Los días de antigüedad, o 0 si no se encuentra.
 */
function antiguedad_usuario($conn, $usuario_id) {
    $query = "SELECT DATEDIFF(CURDATE(), fecha_registro) AS dias FROM usuarios WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    // Si la consulta devuelve una fila, se retorna el valor de 'dias'.
    if ($row = $result->fetch_assoc()) {
        return $row['dias'];
    }
    // Si no se encuentra el usuario, se retorna 0.
    return 0;
}

/**
 * Calcula el precio total de un alquiler de forma segura en el servidor para evitar manipulaciones.
 * @param mysqli $conn La conexión a la base de datos.
 * @param int $moto_id El ID de la moto a alquilar.
 * @param string $f_inicio La fecha de inicio del alquiler.
 * @param string $f_fin La fecha de fin del alquiler.
 * @return float El precio total calculado.
 */
function calcular_precio_total($conn, $moto_id, $f_inicio, $f_fin) {
    // Primero, obtiene el precio por día de la moto desde la base de datos.
    $query = "SELECT precio_dia FROM motos WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $moto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    // Si se encuentra la moto...
    if ($row = $result->fetch_assoc()) {
        $precio_dia = $row['precio_dia'];
        // Calcula la diferencia de días entre las fechas y se suma 1 para incluir el día de inicio.
        $dias = (strtotime($f_fin) - strtotime($f_inicio)) / (60 * 60 * 24) + 1;
        // Retorna el precio total.
        return $precio_dia * $dias;
    }
    // Si la moto no se encuentra, retorna 0.00.
    return 0.00;
}

/**
 * Calcula el total gastado por un usuario en alquileres 'confirmados' o 'finalizados'.
 * @param mysqli $conn La conexión a la base de datos.
 * @param int $usuario_id El ID del usuario.
 * @return float El total gastado, o 0.00 si no hay gastos.
 */
function total_gastado($conn, $usuario_id) {
    $query = "SELECT SUM(precio_total) AS total FROM alquileres WHERE usuario_id = ? AND estado IN ('confirmado', 'en_curso', 'finalizado')";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        return $row['total'] ?? 0.00;
    }
    return 0.00;
}

/**
 * Comprueba si existe un solapamiento de fechas para una moto concreta.
 * Devuelve true si ya hay una reserva o alquiler activo en ese rango.
 * @param mysqli $conn La conexión a la base de datos.
 * @param int $moto_id El ID de la moto.
 * @param string $f_inicio Fecha de inicio solicitada.
 * @param string $f_fin Fecha de fin solicitada.
 * @return bool True si hay solapamiento, false en caso contrario.
 */
function existe_solapamiento_reserva($conn, $moto_id, $f_inicio, $f_fin) {
    $query = "SELECT COUNT(*) AS total FROM alquileres
              WHERE moto_id = ?
                AND estado IN ('pendiente','confirmado','en_curso')
                AND NOT (fecha_fin < ? OR fecha_inicio > ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iss", $moto_id, $f_inicio, $f_fin);
    $stmt->execute();
    $result = $stmt->get_result();
    $solapamiento = false;
    if ($row = $result->fetch_assoc()) {
        $solapamiento = $row['total'] > 0;
    }
    $stmt->close();
    return $solapamiento;
}

/**
 * Procedimiento principal que actualiza el estado de todo el sistema.
 * Se ejecuta al cargar páginas clave como el perfil o el panel de admin.
 * @param mysqli $conn La conexión a la base de datos.
 */
function actualizar_sistema_completo($conn) {
    // 1. Pasa alquileres 'confirmados' a 'en_curso' si la fecha actual está dentro del rango del alquiler.
    $conn->query("UPDATE alquileres SET estado = 'en_curso' WHERE fecha_inicio <= CURDATE() AND fecha_fin >= CURDATE() AND estado = 'confirmado'");
    
    // 2. Pasa alquileres 'confirmados' o 'en_curso' a 'finalizado' si su fecha de fin ya pasó.
    $conn->query("UPDATE alquileres SET estado = 'finalizado' WHERE fecha_fin < CURDATE() AND estado IN ('confirmado', 'en_curso')");
    
    // 3. Cancela automáticamente alquileres 'pendientes' si su fecha de inicio ya pasó (el cliente no pagó a tiempo).
    $conn->query("UPDATE alquileres SET estado = 'cancelado' WHERE fecha_inicio < CURDATE() AND estado = 'pendiente'");
    
    // 4. Resetea todas las motos a 'disponible'.
    $conn->query("UPDATE motos SET disponible = 1");
    
    // 5. Marca como 'no disponible' solo aquellas motos que están en un alquiler activo ('confirmado' o 'en_curso').
    $conn->query("UPDATE motos SET disponible = 0 WHERE id IN (SELECT DISTINCT moto_id FROM alquileres WHERE estado IN ('confirmado', 'en_curso'))");

}

/**
 * Simula un procedimiento almacenado (Stored Procedure) para gestionar el estado de un alquiler específico.
 * @param mysqli $conn La conexión a la base de datos.
 * @param int $alquiler_id El ID del alquiler a gestionar.
 */
function sp_gestionar_estado_alquiler($conn, $alquiler_id) {
    $query = "SELECT fecha_inicio, estado FROM alquileres WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $alquiler_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        if ($row['estado'] == 'completado' && $row['fecha_inicio'] <= date('Y-m-d')) {
            $update = "UPDATE alquileres SET estado = 'en curso' WHERE id = ?";
            $stmt2 = $conn->prepare($update);
            $stmt2->bind_param("i", $alquiler_id);
            $stmt2->execute();
        }
    }
}

/**
 * Simula un TRIGGER BEFORE INSERT para la tabla de usuarios.
 * Realiza conversiones de datos (email a minúsculas, nombre a mayúsculas) antes de la inserción.
 * @param mysqli $conn La conexión a la base de datos.
 */
function insertar_usuario($conn, $nombre, $apellidos, $email, $password, $telefono, $dni, $direccion, $rol = 'cliente', $estado = 'activo') {
    $email = strtolower($email);
    $nombre = strtoupper($nombre);
    $query = "INSERT INTO usuarios (nombre, apellidos, email, password, telefono, dni, direccion, fecha_registro, rol, estado) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssssssss", $nombre, $apellidos, $email, $password, $telefono, $dni, $direccion, $rol, $estado);
    return $stmt->execute();
}

/**
 * Simula un EVENTO de base de datos que se ejecuta periódicamente.
 * Cancela las reservas que llevan más de 24 horas en estado 'pendiente'.
 * @param mysqli $conn La conexión a la base de datos.
 */
function cancelar_reservas_antiguas($conn) {
    $conn->query("UPDATE alquileres SET estado = 'cancelado' WHERE estado = 'pendiente' AND fecha_reserva < (NOW() - INTERVAL 24 HOUR)");
}

/**
 * Detecta si el agente de usuario corresponde a un dispositivo móvil.
 * @return bool True si es un dispositivo móvil, false en caso contrario.
 */
function isMobile() {
    // Expresión regular exhaustiva para detectar la mayoría de los sistemas operativos y navegadores móviles.
    return preg_match("/(android|avantgo|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino)/i", $_SERVER["HTTP_USER_AGENT"]);
}

?>