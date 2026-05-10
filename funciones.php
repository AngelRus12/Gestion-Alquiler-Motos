<?php
/**
 * Este archivo contiene funciones de utilidad y de lógica de negocio
 * que se utilizan en varias partes de la aplicación.
 */

/**
 * Calcula la antigüedad de un usuario en días desde su fecha de registro.
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
    if ($row = $result->fetch_assoc()) {
        return $row['dias'];
    }
    return 0;
}

/**
 * Calcula el precio total de un alquiler de forma segura en el servidor.
 * @param mysqli $conn La conexión a la base de datos.
 * @param int $moto_id El ID de la moto a alquilar.
 * @param string $f_inicio La fecha de inicio del alquiler.
 * @param string $f_fin La fecha de fin del alquiler.
 * @return float El precio total calculado.
 */
function calcular_precio_total($conn, $moto_id, $f_inicio, $f_fin) {
    $query = "SELECT precio_dia FROM motos WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $moto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $precio_dia = $row['precio_dia'];
        // Se calcula la diferencia de días entre las fechas y se suma 1 para incluir el día de inicio.
        $dias = (strtotime($f_fin) - strtotime($f_inicio)) / (60 * 60 * 24) + 1;
        return $precio_dia * $dias;
    }
    return 0.00;
}

/**
 * Calcula el total gastado por un usuario en alquileres confirmados.
 * @param mysqli $conn La conexión a la base de datos.
 * @param int $usuario_id El ID del usuario.
 * @return float El total gastado, o 0.00 si no hay gastos.
 */
function total_gastadoo($conn, $usuario_id) {
    $query = "SELECT SUM(precio_total) AS total FROM alquileres WHERE usuario_id = ? AND estado = 'confirmado'";
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

// Procedimiento: sp_gestionar_estado_alquiler
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

// Función para insertar usuario con conversiones (equivalente a triggers)
function insertar_usuario($conn, $nombre, $apellidos, $email, $password, $telefono, $dni, $direccion, $rol = 'cliente', $estado = 'activo') {
    $email = strtolower($email);
    $nombre = strtoupper($nombre);
    $query = "INSERT INTO usuarios (nombre, apellidos, email, password, telefono, dni, direccion, fecha_registro, rol, estado) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssssssss", $nombre, $apellidos, $email, $password, $telefono, $dni, $direccion, $rol, $estado);
    return $stmt->execute();
}

// Función para cancelar reservas pendientes después de 24 horas (equivalente al evento)
function cancelar_reservas_antiguas($conn) {
    $conn->query("UPDATE alquileres SET estado = 'cancelado' WHERE estado = 'pendiente' AND fecha_reserva < (NOW() - INTERVAL 24 HOUR)");
}
?>