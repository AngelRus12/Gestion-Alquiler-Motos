<?php
// Inicia la sesión para poder verificar el rol del usuario.
session_start();
// Incluye el archivo con las credenciales de la base de datos.
require_once 'loginbd.php';

// --- 1. CONTROL DE ACCESO ---
// Verifica si el usuario tiene el rol de 'admin'. Si no, lo redirige.
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: login.php?error=acceso_denegado');
    exit();
}

// --- 2. VERIFICACIÓN DEL MÉTODO DE SOLICITUD ---
// Asegura que el script solo se ejecute si los datos vienen de un formulario enviado por POST.
// Esto previene que alguien pueda acceder al script directamente desde la URL.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: nueva_moto.php');
    exit();
}

// --- 3. CONEXIÓN A LA BASE DE DATOS ---
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
if (!$conexion) {
    // Si la conexión falla, redirige al formulario con un mensaje de error.
    header('Location: nueva_moto.php?error=db');
    exit();
}
// Establece la codificación de caracteres a UTF-8.
mysqli_set_charset($conexion, "utf8");

// --- 4. RECOLECCIÓN Y SANITIZACIÓN DE DATOS ---
// Se recogen los datos del formulario. `trim()` elimina espacios en blanco al inicio y al final.
// El operador de fusión de null (??) asegura que si un campo no viene, se le asigna un valor por defecto.
$marca = trim($_POST['marca'] ?? '');
$modelo = trim($_POST['modelo'] ?? '');
$año = (int)($_POST['año'] ?? 0);
$cilindrada = (int)($_POST['cilindrada'] ?? 0);
$kilometraje = (int)($_POST['kilometraje'] ?? 0);
$tipo = trim($_POST['tipo'] ?? '');
$matricula = trim($_POST['matricula'] ?? '');
$precio_dia = (float)($_POST['precio_dia'] ?? 0.0);
$descripcion = trim($_POST['descripcion'] ?? '');

// Se valida que los campos obligatorios no estén vacíos o con valores inválidos.
if (empty($marca) || empty($modelo) || $año <= 0 || $cilindrada <= 0 || empty($tipo) || empty($matricula) || $precio_dia <= 0) {
    header('Location: nueva_moto.php?error=campos_vacios');
    exit();
}

// --- 5. PROCESAMIENTO DE LA IMAGEN ---
$imagen_contenido = null;
// Se verifica si se subió un archivo, si no hubo errores y si el archivo tiene contenido.
if (isset($_FILES['imagen']) && $_FILES['imagen']['error'] == UPLOAD_ERR_OK) {
    // `file_get_contents` lee el contenido binario del archivo temporal subido.
    // Este contenido se guardará directamente en la columna de tipo BLOB de la base de datos.
    $imagen_contenido = file_get_contents($_FILES['imagen']['tmp_name']);
} else {
    // Si la imagen es obligatoria y falla la subida, se redirige con un error.
    header('Location: nueva_moto.php?error=imagen');
    exit();
}

// --- 6. INSERCIÓN EN LA BASE DE DATOS (CONSULTA PREPARADA) ---
// Se usa una consulta preparada para evitar inyecciones SQL. Los `?` son marcadores de posición.
$sql = "INSERT INTO motos (marca, modelo, año, cilindrada, kilometraje, tipo, matricula, precio_dia, descripcion, imagen, disponible) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";

$stmt = mysqli_prepare($conexion, $sql);

// Se asocian las variables a los marcadores de posición. La cadena "ssiiissdsb" especifica el tipo de cada variable:
// s: string, i: integer, d: double (float), b: blob (para la imagen).
mysqli_stmt_bind_param($stmt, "ssiiissdsb", $marca, $modelo, $año, $cilindrada, $kilometraje, $tipo, $matricula, $precio_dia, $descripcion, $imagen_contenido);

// Se ejecuta la consulta.
if (mysqli_stmt_execute($stmt)) {
    // Si la inserción es exitosa, se redirige al panel de admin con un mensaje de éxito.
    header('Location: admin_dashboard.php?msg=moto_creada');
} else {
    // Si hay un error, se redirige de vuelta al formulario con un mensaje de error.
    header('Location: nueva_moto.php?error=sql');
}

// Se cierran la consulta preparada y la conexión para liberar recursos.
mysqli_stmt_close($stmt);
mysqli_close($conexion);
exit();