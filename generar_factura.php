<?php
/**
 * generar_factura.php
 * Genera y muestra una factura PDF para un alquiler concreto.
 * - Usa la librería FPDF para crear un PDF dinámico.
 * - Verifica que el usuario sea el dueño del alquiler o administrador.
 */
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

// --- 5. CÁLCULOS PARA LA FACTURA (BASE IMPONIBLE, IVA, ETC.) ---
$precio_total = $alquiler['precio_total'];
$iva_tasa = 0.21; // 21% de IVA
$base_imponible = $precio_total / (1 + $iva_tasa);
$iva_monto = $precio_total - $base_imponible;

// --- 5. CREACIÓN DEL PDF CON FPDF ---

class PDF extends FPDF
{
    // Cabecera de página
    function Header()
    {
        // Logo
        if (file_exists('logo.png')) {
            $this->Image('logo.png', 10, 10, 40);
        }
        // Título de la factura
        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor(34, 34, 34);
        $this->Cell(0, 10, 'FACTURA ARUSLAT', 0, 1, 'R');
        
        // Número de factura
        global $alquiler; // Hacemos la variable global para acceder a ella aquí
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 7, utf8_decode('Nº: FAC-') . date("Y") . '-' . str_pad($alquiler['id'], 6, "0", STR_PAD_LEFT), 0, 1, 'R');
        $this->Cell(0, 7, 'Fecha: ' . date("d/m/Y"), 0, 1, 'R');
    }

    // Pie de página
    function Footer()
    {
        $this->SetY(-15); // Posición a 1.5 cm del final
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
        $this->Cell(0, 10, utf8_decode('Gracias por su confianza - ARUSLAT Motos'), 0, 0, 'R');
    }
}

// Creación del objeto PDF
$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetTitle('Factura Alquiler #' . $alquiler['id'] . ' - ARUSLAT');
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(true, 20);
$pdf->SetFont('Arial', '', 11);
$pdf->SetTextColor(0, 0, 0);

$pdf->Ln(30); // Espacio después de la cabecera

// --- Datos del Cliente y Empresa ---
$pdf->SetFillColor(240, 240, 240);
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(95, 8, 'Emitido por', 0, 0, 'L', true);
$pdf->Cell(95, 8, 'Facturado a', 0, 1, 'L', true);

$pdf->SetFont('Arial', '', 10);
$pdf->Cell(95, 6, 'ARUSLAT Motos S.L.', 0, 0, 'L');
$pdf->Cell(95, 6, utf8_decode($alquiler['nombre'] . ' ' . $alquiler['apellidos']), 0, 1, 'L');
$pdf->Cell(95, 6, 'CIF: B-12345678', 0, 0, 'L');
$pdf->Cell(95, 6, 'DNI: ' . $alquiler['dni'], 0, 1, 'L');
$pdf->Cell(95, 6, 'Calle Maria Lejarrega 3, Torreperogil', 0, 0, 'L');
$pdf->Cell(95, 6, utf8_decode($alquiler['direccion']), 0, 1, 'L');
$pdf->Cell(95, 6, 'info@alquilermotos.com', 0, 0, 'L');
$pdf->Cell(95, 6, $alquiler['email'], 0, 1, 'L');
$pdf->Ln(15);

// --- Tabla de Detalles del Alquiler ---
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(52, 58, 64); // Un gris oscuro para la cabecera
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(70, 10, 'Concepto', 1, 0, 'L', true);
$pdf->Cell(20, 10, utf8_decode('Días'), 1, 0, 'C', true);
$pdf->Cell(40, 10, 'Periodo', 1, 0, 'C', true);
$pdf->Cell(30, 10, utf8_decode('Precio/Día'), 1, 0, 'C', true);
$pdf->Cell(30, 10, 'Subtotal', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 10);
$pdf->SetTextColor(0, 0, 0);

$concepto = utf8_decode("Alquiler de moto " . $alquiler['marca'] . " " . $alquiler['modelo'] . " (Mat. " . $alquiler['matricula'] . ")");
$fecha_inicio_formato = date("d/m/Y", strtotime($alquiler['fecha_inicio']));
$fecha_fin_formato = date("d/m/Y", strtotime($alquiler['fecha_fin']));
$dias_alquiler_texto = $alquiler['dias_alquiler'];
$periodo_alquiler_texto = $fecha_inicio_formato . " a\n" . $fecha_fin_formato;
$precio_dia = number_format($base_imponible / $alquiler['dias_alquiler'], 2) . ' ' . chr(128);
$subtotal = number_format($base_imponible, 2) . ' ' . chr(128);

// Usamos MultiCell para que el texto del concepto se ajuste automáticamente si es muy largo
$y_inicial = $pdf->GetY();
$x_inicial = $pdf->GetX();

// Dibujamos la primera celda (Concepto) y calculamos la altura que ocupará
$pdf->MultiCell(70, 8, $concepto, 'LR', 'L');
$y_final = $pdf->GetY();
$altura_fila = $y_final - $y_inicial;

// Reposicionamos el cursor a la derecha de la celda anterior, en la misma línea inicial
$pdf->SetXY($x_inicial + 70, $y_inicial);

// Dibujamos el resto de celdas como MultiCell, usando la altura calculada para que todas tengan el mismo alto
$pdf->MultiCell(20, $altura_fila, $dias_alquiler_texto, 'R', 'C');
$pdf->SetXY($x_inicial + 90, $y_inicial);
$pdf->MultiCell(40, $altura_fila / 2, $periodo_alquiler_texto, 'R', 'C');
$pdf->SetXY($x_inicial + 130, $y_inicial);
$pdf->MultiCell(30, $altura_fila, $precio_dia, 'R', 'R');
$pdf->SetXY($x_inicial + 160, $y_inicial);
$pdf->MultiCell(30, $altura_fila, $subtotal, 'R', 'R');
$pdf->Cell(190, 0, '', 'T', 1); // Línea inferior de la tabla

// --- Totales ---
$pdf->Ln(5);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(130, 7, '', 0, 0);
$pdf->Cell(30, 7, 'Base Imponible', 1, 0, 'R');
$pdf->Cell(30, 7, number_format($base_imponible, 2) . ' ' . chr(128), 1, 1, 'R');

$pdf->Cell(130, 7, '', 0, 0);
$pdf->Cell(30, 7, 'IVA (21%)', 1, 0, 'R');
$pdf->Cell(30, 7, number_format($iva_monto, 2) . ' ' . chr(128), 1, 1, 'R');

$pdf->SetFont('Arial', 'B', 12);
$pdf->SetFillColor(52, 58, 64);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell(130, 10, '', 0, 0);
$pdf->Cell(30, 10, 'TOTAL', 1, 0, 'C', true);
$pdf->Cell(30, 10, number_format($alquiler['precio_total'], 2) . ' ' . chr(128), 1, 1, 'R', true);

$pdf->Output('I', 'Factura-ALQ' . $alquiler['id'] . '.pdf'); // 'I' para mostrar en navegador, 'D' para forzar descarga
?>