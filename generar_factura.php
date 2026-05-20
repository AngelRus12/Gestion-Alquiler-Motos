<?php
session_start();
require_once 'loginbd.php';
require_once 'libs/fpdf/fpdf.php';

// --- 1. CONTROL DE ACCESO Y VALIDACIÓN ---
if (!isset($_SESSION['usuario_id'])) {
    die("Acceso denegado. Debes iniciar sesión.");
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    die("ID de alquiler no válido.");
}

$alquiler_id = (int)$_GET['id'];
$usuario_id = $_SESSION['usuario_id'];

// --- 2. CONEXIÓN A LA BASE DE DATOS ---
$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);
if (!$conexion) {
    die("Error de conexión a la base de datos.");
}
mysqli_set_charset($conexion, "utf8");

// --- 3. OBTENCIÓN DE DATOS (Consulta con JOIN para eficiencia) ---
// Usamos JOINs para obtener toda la información en una sola consulta.
$sql = "SELECT a.*, m.marca, m.modelo, m.matricula, u.nombre, u.apellidos, u.email, u.dni, u.direccion
        FROM alquileres a
        JOIN motos m ON a.moto_id = m.id
        JOIN usuarios u ON a.usuario_id = u.id
        WHERE a.id = ?";

$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "i", $alquiler_id);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$alquiler = mysqli_fetch_assoc($resultado);
mysqli_stmt_close($stmt);
mysqli_close($conexion);

// --- 4. VERIFICACIÓN DE PERMISOS ---
// El usuario debe ser el dueño del alquiler o un administrador.
if (!$alquiler || ($alquiler['usuario_id'] != $usuario_id && $_SESSION['rol'] !== 'admin')) {
    die("No tienes permiso para ver esta factura.");
}

// --- 5. CREACIÓN DEL PDF CON FPDF ---

class PDF extends FPDF
{
    // Cabecera de página
    function Header()
    {
        // Logo (asegúrate de tener 'logo.png' en la misma carpeta o proporciona la ruta correcta)
        if (file_exists('logo.png')) {
            $this->Image('logo.png', 10, 6, 30);
        }
        $this->SetFont('Arial', 'B', 20);
        $this->Cell(80); // Mover a la derecha
        $this->Cell(30, 10, 'FACTURA', 1, 0, 'C'); // Título
        $this->Ln(20); // Salto de línea
    }

    // Pie de página
    function Footer()
    {
        $this->SetY(-15); // Posición a 1.5 cm del final
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
        $this->SetX(-90);
        $this->Cell(0, 10, utf8_decode('ARUSLAT Motos - TFG Ángel Rus Latorre'), 0, 0, 'R');
    }
}

// Creación del objeto PDF
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 12);

// --- Información de la Factura ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, utf8_decode('Información de la Factura'), 0, 1);
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(40, 7, utf8_decode('Nº Factura:'), 0, 0);
$pdf->Cell(0, 7, 'FAC-' . date("Y") . '-' . str_pad($alquiler['id'], 6, "0", STR_PAD_LEFT), 0, 1);
$pdf->Cell(40, 7, 'Fecha Factura:', 0, 0);
$pdf->Cell(0, 7, date("d/m/Y"), 0, 1);
$pdf->Cell(40, 7, 'ID Alquiler:', 0, 0);
$pdf->Cell(0, 7, $alquiler['id'], 0, 1);
$pdf->Ln(10);

// --- Datos del Cliente y Empresa ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(95, 7, 'Facturado a:', 0, 0);
$pdf->Cell(95, 7, 'Emitido por:', 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(95, 6, utf8_decode($alquiler['nombre'] . ' ' . $alquiler['apellidos']), 0, 0);
$pdf->Cell(95, 6, 'ARUSLAT Motos S.L.', 0, 1);
$pdf->Cell(95, 6, 'DNI: ' . $alquiler['dni'], 0, 0);
$pdf->Cell(95, 6, 'CIF: B-12345678', 0, 1);
$pdf->Cell(95, 6, utf8_decode($alquiler['direccion']), 0, 0);
$pdf->Cell(95, 6, 'Calle Maria Lejarrega 3', 0, 1);
$pdf->Cell(95, 6, $alquiler['email'], 0, 0);
$pdf->Cell(95, 6, 'info@alquilermotos.com', 0, 1);
$pdf->Ln(15);

// --- Tabla de Detalles del Alquiler ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(224, 224, 224); // Color de fondo para la cabecera
$pdf->Cell(130, 10, 'Concepto', 1, 0, 'C', true);
$pdf->Cell(30, 10, 'Cantidad', 1, 0, 'C', true);
$pdf->Cell(30, 10, 'Total', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 11);
$concepto = utf8_decode("Alquiler de moto " . $alquiler['marca'] . " " . $alquiler['modelo'] . " (Mat. " . $alquiler['matricula'] . ")");
$periodo = utf8_decode("Periodo: " . date("d/m/Y", strtotime($alquiler['fecha_inicio'])) . " al " . date("d/m/Y", strtotime($alquiler['fecha_fin'])));

$pdf->Cell(130, 8, $concepto, 'LR', 0, 'L');
$pdf->Cell(30, 8, $alquiler['dias_alquiler'] . utf8_decode(' días'), 'R', 0, 'C');
$pdf->Cell(30, 8, number_format($alquiler['precio_total'], 2) . ' ' . chr(128), 'R', 1, 'R'); // chr(128) es el símbolo €

$pdf->Cell(130, 8, $periodo, 'LRB', 0, 'L');
$pdf->Cell(30, 8, '', 'RB', 0, 'C');
$pdf->Cell(30, 8, '', 'RB', 1, 'R');

// --- Totales ---
$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(130, 10, '', 0, 0);
$pdf->Cell(30, 10, 'TOTAL', 1, 0, 'C');
$pdf->Cell(30, 10, number_format($alquiler['precio_total'], 2) . ' ' . chr(128), 1, 1, 'R');

$pdf->Output('I', 'Factura-ALQ' . $alquiler['id'] . '.pdf'); // 'I' para mostrar en navegador, 'D' para forzar descarga
?>