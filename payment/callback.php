<?php
/**
 * SIMULADOR BANCARIO - Callback / Respuesta
 * Este archivo recibe la respuesta del simulador, actualiza la base de datos
 * y redirige al usuario a su perfil con el estado del pago.
 */

session_start();
require_once '../loginbd.php';

// Recuperar datos de la sesión
$simulatorData = $_SESSION['simulator_data'] ?? [];
$pendingTransaction = $_SESSION['pending_transaction'] ?? [];

// Verificar que tenemos datos de transacción del simulador
if (empty($simulatorData)) {
    header('Location: ../catalogo.php?error=no_transaction_data');
    exit();
}

$paymentMethod = $simulatorData['payment_method'] ?? 'unknown';
$amount = $simulatorData['amount'] ?? 0;
$orderId = $simulatorData['order_id'] ?? '';
$responseType = $_POST['response_type'] ?? 'approved';

// Generar respuesta simulada según el tipo seleccionado
$responses = [
    'approved' => [
        'db_status' => 'confirmado',
        'status' => 'approved',
        'status_detail' => 'accredited',
        'title' => 'Pago Aprobado',
        'message' => '¡Tu pago fue procesado exitosamente!',
        'icon' => 'check-circle-fill',
        'color' => 'success',
        'code' => '00',
        'description' => 'Transacción aprobada sin problemas.',
        'redirect_param' => 'pago=confirmado'
    ],
    'rejected' => [
        'db_status' => 'cancelado',
        'status' => 'rejected',
        'status_detail' => 'cc_rejected_insufficient_amount',
        'title' => 'Pago Rechazado',
        'message' => 'Tu pago fue rechazado por el banco emisor.',
        'icon' => 'x-circle-fill',
        'color' => 'danger',
        'code' => '51',
        'description' => 'Fondos insuficientes o tarjeta rechazada.',
        'redirect_param' => 'pago=rechazado'
    ],
    'pending' => [
        'db_status' => 'pendiente',
        'status' => 'pending',
        'status_detail' => 'pending_contingency',
        'title' => 'Pago Pendiente',
        'message' => 'Tu pago está siendo procesado.',
        'icon' => 'clock-fill',
        'color' => 'warning',
        'code' => '02',
        'description' => 'El pago requiere verificación o está en revisión.',
        'redirect_param' => 'pago=pendiente'
    ],
    'cancelled' => [
        'db_status' => 'cancelado',
        'status' => 'cancelled',
        'status_detail' => 'by_user',
        'title' => 'Pago Cancelado',
        'message' => 'Has cancelado la operación de pago.',
        'icon' => 'arrow-left-circle-fill',
        'color' => 'secondary',
        'code' => 'USR_CANCEL',
        'description' => 'El usuario abandonó el proceso de pago.',
        'redirect_param' => 'pago=cancelado'
    ],
    'error' => [
        'db_status' => 'error',
        'status' => 'error',
        'status_detail' => 'internal_error',
        'title' => 'Error del Sistema',
        'message' => 'Ocurrió un error al procesar tu pago.',
        'icon' => 'exclamation-triangle-fill',
        'color' => 'dark',
        'code' => 'ERR-500',
        'description' => 'Error técnico en la plataforma de pagos.',
        'redirect_param' => 'pago=error'
    ],
    'timeout' => [
        'db_status' => 'error', // Un timeout se trata como un error
        'status' => 'timeout',
        'status_detail' => 'timeout_expired',
        'title' => 'Tiempo Agotado',
        'message' => 'El tiempo para completar el pago ha expirado.',
        'icon' => 'alarm-fill',
        'color' => 'info',
        'code' => 'TIMEOUT',
        'description' => 'La sesión de pago expiró por inactividad.',
        'redirect_param' => 'pago=error'
    ],
];

$response = $responses[$responseType] ?? $responses['approved'];
$returnUrl = $pendingTransaction['return_url'] ?? '../perfil_usuario.php';

// --- INICIO: LÓGICA DE BASE DE DATOS MOVIDA DESDE callback_pago.php ---

$conexion = mysqli_connect($db_hostname, $db_username, $db_password, $db_database);

if (!$conexion) {
    // Si la BD falla, redirigimos con un error específico
    header('Location: ' . $returnUrl . '?pago=error_db_conn');
    exit();
}

// Extraer el ID del alquiler del order_id (formato: ALQ-{id}-{timestamp})
$parts = explode('-', $orderId);
$id_alquiler = isset($parts[1]) ? (int)$parts[1] : 0;
$usuario_id = $_SESSION['usuario_id'] ?? 0;

if (!$id_alquiler || !$usuario_id) {
    mysqli_close($conexion);
    header('Location: ../catalogo.php?error=invalid_access');
    exit();
}

// Obtener moto_id para poder actualizar su estado si el pago se aprueba
$sql_check = "SELECT moto_id FROM alquileres WHERE id = ? AND usuario_id = ?";
$stmt_check = mysqli_prepare($conexion, $sql_check);
mysqli_stmt_bind_param($stmt_check, "ii", $id_alquiler, $usuario_id);
mysqli_stmt_execute($stmt_check);
$result = mysqli_stmt_get_result($stmt_check);
$alquiler = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt_check);

if (!$alquiler) {
    mysqli_close($conexion);
    header('Location: ../catalogo.php?error=alquiler_not_found');
    exit();
}

// Iniciar transacción
mysqli_begin_transaction($conexion);
$transaction_successful = true;

// 1. Actualizar el estado del alquiler
$nuevo_estado_db = $response['db_status'];
$sql_update = "UPDATE alquileres SET estado = ? WHERE id = ?";
$stmt_update = mysqli_prepare($conexion, $sql_update);
mysqli_stmt_bind_param($stmt_update, "si", $nuevo_estado_db, $id_alquiler);
if (!mysqli_stmt_execute($stmt_update)) {
    $transaction_successful = false;
}
mysqli_stmt_close($stmt_update);

// 2. Si el pago fue aprobado, marcar la moto como no disponible
if ($transaction_successful && $nuevo_estado_db === 'confirmado') {
    $moto_id = $alquiler['moto_id'];
    $sql_update_moto = "UPDATE motos SET disponible = 0 WHERE id = ?";
    $stmt_moto = mysqli_prepare($conexion, $sql_update_moto);
    mysqli_stmt_bind_param($stmt_moto, "i", $moto_id);
    if (!mysqli_stmt_execute($stmt_moto)) {
        $transaction_successful = false;
    }
    mysqli_stmt_close($stmt_moto);
}

// --- FIN: LÓGICA DE BASE DE DATOS ---


// Generar datos de transacción simulados (como los que devuelven las APIs reales)
$transactionData = [
    'transaction_id' => strtoupper(uniqid('TXN-')),
    'authorization_code' => ($responseType === 'approved') ? rand(100000, 999999) : null,
    'payment_id' => rand(1000000000, 9999999999),
    'timestamp' => date('Y-m-d H:i:s'),
    'payment_method' => $paymentMethod,
    'amount' => $amount,
    'currency' => 'EUR',
    'order_id' => $orderId,
    'status' => $response['status'],
    'status_detail' => $response['status_detail'],
    'response_code' => $response['code'],
];

// Simular respuesta específica por método de pago
switch ($paymentMethod) {
    case 'webpay':
        // Respuesta tipo Webpay (Transbank)
        $transactionData['response_data'] = [
            'vci' => 'TSY', // Transaction Security Indicator
            'amount' => $amount,
            'status' => ($responseType === 'approved') ? 'AUTHORIZED' : 'FAILED',
            'buy_order' => $orderId,
            'session_id' => session_id(),
            'card_detail' => [
                'card_number' => '****' . rand(1000, 9999),
            ],
            'accounting_date' => date('md'),
            'transaction_date' => date('Y-m-d H:i:s'),
            'authorization_code' => $transactionData['authorization_code'],
            'payment_type_code' => 'VN', // Venta Normal
            'response_code' => ($responseType === 'approved') ? 0 : -1,
            'installments_number' => 0,
        ];
        break;
    
    case 'mercadopago':
        // Respuesta tipo Mercado Pago
        $transactionData['response_data'] = [
            'id' => $transactionData['payment_id'],
            'status' => $response['status'],
            'status_detail' => $response['status_detail'],
            'payment_method_id' => 'visa',
            'payment_type_id' => 'credit_card',
            'transaction_amount' => $amount,
            'currency_id' => 'EUR',
            'date_created' => date('c'),
            'date_approved' => ($responseType === 'approved') ? date('c') : null,
            'authorization_code' => $transactionData['authorization_code'],
            'external_reference' => $orderId,
            'merchant_order_id' => rand(1000000, 9999999),
            'payer' => [
                'id' => rand(100000, 999999),
                'email' => 'test_user@test.com',
                'identification' => [
                    'type' => 'RUT',
                    'number' => '11111111-1'
                ]
            ],
        ];
        break;
    
    case 'paypal':
        // Respuesta tipo PayPal
        $transactionData['response_data'] = [
            'id' => 'PAY-' . strtoupper(substr(md5(time()), 0, 17)),
            'intent' => 'sale',
            'state' => ($responseType === 'approved') ? 'approved' : 'failed',
            'cart' => $orderId,
            'create_time' => date('c'),
            'update_time' => date('c'),
            'payer' => [
                'payment_method' => 'paypal',
                'status' => 'VERIFIED',
                'payer_info' => [
                    'email' => 'test@example.com',
                    'first_name' => 'Test',
                    'last_name' => 'User',
                    'payer_id' => 'PAYERID' . rand(100000, 999999),
                    'country_code' => 'CL'
                ]
            ],
            'transactions' => [[
                'amount' => [
                    'total' => number_format($amount / 1000, 2, '.', ''), // Convertir a dólares aprox
                    'currency' => 'USD',
                ],
                'description' => 'Payment description',
                'invoice_number' => $orderId,
            ]],
        ];
        break;
    
    case 'bank_transfer':
        // Respuesta de transferencia bancaria
        $transactionData['response_data'] = [
            'reference' => $transactionData['transaction_id'],
            'status' => $response['status'],
            'bank_name' => 'Banco de Chile',
            'account_number' => '****5678',
            'amount' => $amount,
            'currency' => 'EUR',
            'date' => date('Y-m-d H:i:s'),
        ];
        break;
}

// Guardar resultado en sesión (en producción esto iría a base de datos)
$_SESSION['last_transaction'] = $transactionData;

// Finalizar transacción y limpiar sesión
if ($transaction_successful) {
    mysqli_commit($conexion);
    $redirect_param = $response['redirect_param'];
} else {
    mysqli_rollback($conexion);
    $redirect_param = 'pago=error_procesamiento';
}

mysqli_close($conexion);

// Limpiar variables de sesión relacionadas con el pago
unset($_SESSION['id_pago_pendiente']);
unset($_SESSION['monto_pago']);
unset($_SESSION['simulator_data']);
unset($_SESSION['pending_transaction']);

// Redirigir al perfil del usuario con el mensaje de estado
header('Location: ' . $returnUrl . '?' . $redirect_param);
exit();

/*
 * NOTA: El resto del HTML de esta página ya no se mostrará porque la lógica
 * ahora redirige directamente al usuario. Se podría eliminar para limpiar el código,
 * pero se mantiene por si se necesita para depuración en el futuro.
 */
?>
