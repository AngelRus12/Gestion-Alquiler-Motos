<?php
// Funciones PHP equivalentes a las funciones y procedimientos SQL eliminados

// Requiere conexión a la BD
// Asume que $conn es la conexión mysqli

// Función: antiguedad_usuario
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

// Función: calcular_precio_total
function calcular_precio_total($conn, $moto_id, $f_inicio, $f_fin) {
    $query = "SELECT precio_dia FROM motos WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $moto_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $precio_dia = $row['precio_dia'];
        $dias = (strtotime($f_fin) - strtotime($f_inicio)) / (60 * 60 * 24) + 1;
        return $precio_dia * $dias;
    }
    return 0.00;
}

// Función: total_gastadoo
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

// Procedimiento: actualizar_disponibilidad_motos
function actualizar_disponibilidad_motos($conn) {
    // Marcar como no disponible
    $conn->query("UPDATE motos SET disponible = 0 WHERE id IN (SELECT DISTINCT moto_id FROM alquileres WHERE estado IN ('confirmado', 'en curso') AND CURDATE() <= fecha_fin)");
    // Marcar como disponible
    $conn->query("UPDATE motos SET disponible = 1 WHERE id NOT IN (SELECT DISTINCT moto_id FROM alquileres WHERE estado IN ('confirmado', 'en curso') AND CURDATE() <= fecha_fin)");
}

// Procedimiento: actualizar_estados_alquileres
function actualizar_estados_alquileres($conn) {
    $conn->query("UPDATE alquileres SET estado = 'en curso' WHERE fecha_inicio <= CURDATE() AND fecha_fin >= CURDATE() AND estado = 'completado'");
    $conn->query("UPDATE alquileres SET estado = 'finalizado' WHERE fecha_fin < CURDATE() AND estado IN ('en curso', 'completado')");
}

// Procedimiento: actualizar_sistema_completo
function actualizar_sistema_completo($conn) {
    $conn->query("UPDATE alquileres SET estado = 'en_curso' WHERE fecha_inicio <= CURDATE() AND fecha_fin >= CURDATE() AND estado = 'confirmado'");
    $conn->query("UPDATE alquileres SET estado = 'finalizado' WHERE fecha_fin < CURDATE() AND estado IN ('confirmado', 'en_curso')");
    $conn->query("UPDATE alquileres SET estado = 'cancelado' WHERE fecha_inicio <= CURDATE() AND estado = 'pendiente'");
    $conn->query("UPDATE motos SET disponible = 1");
    $conn->query("UPDATE motos SET disponible = 0 WHERE id IN (SELECT moto_id FROM alquileres WHERE estado = 'en_curso')");
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