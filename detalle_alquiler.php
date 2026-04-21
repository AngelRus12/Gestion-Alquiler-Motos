<?php
header('Content-Type: text/html; charset=utf-8');
session_start();
require_once 'loginbd.php';

// 1. Seguridad: Verificar si el usuario está logueado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

// 2. Validar que se ha proporcionado un ID de alquiler
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: perfil_usuario.php?error=id_invalido');
    exit();
}

$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}

mysqli_set_charset($conexion, "utf8");

$alquiler_id = (int)$_GET['id'];
$usuario_id = $_SESSION['usuario_id'];

// 3. Consulta para obtener los detalles del alquiler, la moto y el usuario.
// Se adapta la consulta según el rol del usuario.
if (isset($_SESSION['rol']) && $_SESSION['rol'] === 'admin') {
    // Si es admin, puede ver cualquier alquiler
    $sql = "SELECT 
                a.*, 
                m.marca, m.modelo, m.tipo, m.precio_dia, m.imagen, m.descripcion as moto_descripcion,
                u.nombre, u.apellidos, u.email
            FROM alquileres a
            JOIN motos m ON a.moto_id = m.id
            JOIN usuarios u ON a.usuario_id = u.id
            WHERE a.id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $alquiler_id);
} else {
    // Si es un usuario normal, solo puede ver sus propios alquileres
    $sql = "SELECT 
                a.*, 
                m.marca, m.modelo, m.tipo, m.precio_dia, m.imagen, m.descripcion as moto_descripcion,
                u.nombre, u.apellidos, u.email
            FROM alquileres a
            JOIN motos m ON a.moto_id = m.id
            JOIN usuarios u ON a.usuario_id = u.id
            WHERE a.id = ? AND a.usuario_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $alquiler_id, $usuario_id);
}
mysqli_stmt_execute($stmt); // Ejecutamos la consulta preparada
$resultado = mysqli_stmt_get_result($stmt);
$alquiler = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt);

// 4. Seguridad: Si el alquiler no existe o no pertenece al usuario, redirigir
if (!$alquiler) {
    header('Location: perfil_usuario.php?error=no_encontrado');
    exit();
}