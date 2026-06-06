<?php
/**
 * generar_factura.php
 * Genera y muestra una factura PDF para un alquiler concreto.
 * - Uso la librería FPDF para crear un PDF dinámico.
 * - Verifico que el usuario sea el dueño del alquiler o un administrador.
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

// --- 3. OBTENCIÓN DE DATOS  ---
<<<<<<< HEAD
// Para la factura, necesito datos de 3 tablas. Los obtengo por separado.
=======
// Para la factura, necesito datos de 3 tablas. Los cojo por separado.
>>>>>>> 4f061c50123c453b05ee62e02056c332830aa6a5

// Primero, obtengo los datos principales del alquiler usando el ID que viene en la URL.
$sql_alquiler = "SELECT * FROM alquileres WHERE id = ?";
$stmt_alquiler = mysqli_prepare($conexion, $sql_alquiler);
mysqli_stmt_bind_param($stmt_alquiler, "i", $alquiler_id);
mysqli_stmt_execute($stmt_alquiler);
$resultado_alquiler = mysqli_stmt_get_result($stmt_alquiler);
$alquiler = mysqli_fetch_assoc($resultado_alquiler);
mysqli_stmt_close($stmt_alquiler);

// Si no encuentro el alquiler, detengo el script.
if (!$alquiler) {
    mysqli_close($conexion);
    die("El alquiler solicitado no existe.");
}

// Segundo, uso el 'moto_id' del alquiler para buscar los datos de la moto.
$sql_moto = "SELECT marca, modelo, matricula FROM motos WHERE id = ?";
$stmt_moto = mysqli_prepare($conexion, $sql_moto);
mysqli_stmt_bind_param($stmt_moto, "i", $alquiler['moto_id']);
mysqli_stmt_execute($stmt_moto);
$moto_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_moto));
mysqli_stmt_close($stmt_moto);

// Tercero, uso el 'usuario_id' del alquiler para buscar los datos del cliente.
$sql_usuario = "SELECT nombre, apellidos, email, dni, direccion FROM usuarios WHERE id = ?";
$stmt_usuario = mysqli_prepare($conexion, $sql_usuario);
mysqli_stmt_bind_param($stmt_usuario, "i", $alquiler['usuario_id']);
mysqli_stmt_execute($stmt_usuario);
$usuario_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_usuario));
mysqli_stmt_close($stmt_usuario);

mysqli_close($conexion);

// --- 4. VERIFICACIÓN DE PERMISOS ---
// Compruebo que el usuario que pide la factura es el dueño o un administrador.
if (!$alquiler || !$moto_data || !$usuario_data || ($alquiler['usuario_id'] != $usuario_id && $_SESSION['rol'] !== 'admin')) {
    die("No tienes permiso para ver esta factura o los datos están incompletos.");
}

// --- 5. CÁLCULOS PARA LA FACTURA ---
$precio_total = $alquiler['precio_total'];
$iva_tasa = 0.21; // 21% de IVA
$base_imponible = $precio_total / (1 + $iva_tasa);
$iva_monto = $precio_total - $base_imponible;

// Junto los datos de la moto y el usuario con los del alquiler en un solo array para
// que sea más fácil usarlos después al escribir el PDF.
$alquiler = array_merge($alquiler, $moto_data, $usuario_data);

// --- 5. CREACIÓN DEL PDF CON FPDF ---

class PDF extends FPDF
{
    // Cabecera de página
    function Header() // He personalizado la cabecera de la factura.
    {
        // Logo
        if (file_exists('logo.png')) {
            $this->Image('logo.png', 10, 10, 40); // Añado mi logo.
        }
        // Título de la factura
        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor(34, 34, 34);
        $this->Cell(0, 10, 'FACTURA ARUSLAT', 0, 1, 'R');
        
        // Número de factura
        global $alquiler; // Hago la variable global para poder acceder a ella aquí.
        $this->SetFont('Arial', '', 10);
        $this->Cell(0, 7, utf8_decode('Nº: FAC-') . date("Y") . '-' . str_pad($alquiler['id'], 6, "0", STR_PAD_LEFT), 0, 1, 'R');
        $this->Cell(0, 7, 'Fecha: ' . date("d/m/Y"), 0, 1, 'R');
    }

    // Pie de página personalizado.
    function Footer()
    {
        $this->SetY(-15); // Posición a 1.5 cm del final
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, utf8_decode('Página ') . $this->PageNo() . '/{nb}', 0, 0, 'C');
        $this->Cell(0, 10, utf8_decode('Gracias por su confianza - ARUSLAT Motos'), 0, 0, 'R');
    }
}

// Creación del objeto PDF.
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

// Uso MultiCell para que el texto del concepto se ajuste automáticamente si es muy largo.
$y_inicial = $pdf->GetY();
$x_inicial = $pdf->GetX();

// Dibujo la primera celda (Concepto) y calculo la altura que ocupará.
$pdf->MultiCell(70, 8, $concepto, 'LR', 'L');
$y_final = $pdf->GetY();
$altura_fila = $y_final - $y_inicial;

// Reposiciono el cursor a la derecha de la celda anterior, en la misma línea inicial.
$pdf->SetXY($x_inicial + 70, $y_inicial);

// Dibujo el resto de celdas como MultiCell, usando la altura que he calculado para que todas tengan el mismo alto.
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

$pdf->Output('I', 'Factura-ALQ' . $alquiler['id'] . '.pdf'); // 'I' para mostrar en navegador, 'D' para forzar descarga.
?>