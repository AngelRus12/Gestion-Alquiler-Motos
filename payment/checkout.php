<?php
/**
 * SIMULADOR BANCARIO - Checkout
 * 
 * Este archivo prepara la transacción y redirige al simulador bancario correspondiente.
 * Simula exactamente el flujo que harías con las APIs reales de cada proveedor de pago.
 * 
 * En tu aplicación real:
 * - Aquí crearías la transacción en la API del proveedor
 * - Obtendrías un token o URL de pago
 * - Redirigirías al usuario al portal del banco
 */

session_start();

// Validar que vengan los datos necesarios
if (!isset($_POST['payment_method']) || !isset($_POST['amount'])) {
    die('Error: Datos de pago incompletos');
}

// Capturar datos de la transacción
$paymentMethod = $_POST['payment_method'];
$amount = $_POST['amount'];
$orderId = $_POST['order_id'] ?? 'ORD-' . time();
$description = $_POST['description'] ?? 'Compra en tienda';

// Generar un ID de transacción único
$transactionId = strtoupper(uniqid('TXN-'));

// Detectar la URL base del proyecto dinámicamente
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

// Validar return_url contra una lista blanca de hosts permitidos
$defaultReturnUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/callback_pago.php'; // Explicitly set to the correct callback URL
$rawReturnUrl = trim((string)($_POST['return_url'] ?? ''));

$serverHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
$serverHost = preg_replace('/:\\d+$/', '', $serverHost);
$allowedReturnHosts = [
    $serverHost,
    'localhost',
    '127.0.0.1',
    'domino9.regline.cl',
];

$extraAllowedHosts = trim((string)($_ENV['ALLOWED_RETURN_URL_HOSTS'] ?? ''));
if ($extraAllowedHosts !== '') {
    foreach (explode(',', $extraAllowedHosts) as $host) {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\\d+$/', '', $host);
        if ($host !== '') {
            $allowedReturnHosts[] = $host;
        }
    }
}

$allowedReturnHosts = array_values(array_unique($allowedReturnHosts));
$returnUrl = $defaultReturnUrl;

if ($rawReturnUrl !== '') {
    if (strpos($rawReturnUrl, '/') === 0) {
        $returnUrl = $protocol . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $rawReturnUrl;
    } else {
        $parsedReturnUrl = parse_url($rawReturnUrl);
        if (
            is_array($parsedReturnUrl)
            && isset($parsedReturnUrl['scheme'], $parsedReturnUrl['host'])
        ) {
            $scheme = strtolower((string)$parsedReturnUrl['scheme']);
            $host = strtolower((string)$parsedReturnUrl['host']);
            $host = preg_replace('/:\\d+$/', '', $host);

            if (
                in_array($scheme, ['http', 'https'], true)
                && in_array($host, $allowedReturnHosts, true)
            ) {
                $returnUrl = $rawReturnUrl;
            }
        }
    }
}

// Configurar redirección automática en callback (aprobados)
$rawAutoRedirectOnApproved = $_POST['auto_redirect_on_approved'] ?? 'true';
$autoRedirectOnApproved = filter_var($rawAutoRedirectOnApproved, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
if ($autoRedirectOnApproved === null) {
    $autoRedirectOnApproved = true;
}

$redirectDelayMs = isset($_POST['redirect_delay_ms']) ? (int)$_POST['redirect_delay_ms'] : 2000;
$redirectDelayMs = max(0, min($redirectDelayMs, 15000));

// Guardar datos en sesión (en producción esto iría a base de datos)
$_SESSION['pending_transaction'] = [
    'transaction_id' => $transactionId,
    'payment_method' => $paymentMethod,
    'amount' => $amount,
    'order_id' => $orderId,
    'description' => $description,
    'timestamp' => time(),
    'return_url' => $returnUrl,
    'auto_redirect_on_approved' => $autoRedirectOnApproved,
    'redirect_delay_ms' => $redirectDelayMs,
];

// Generar token de sesión seguro (en producción usarías un hash criptográfico)
$token = hash('sha256', $transactionId . session_id() . time());
$_SESSION['transaction_token'] = $token;

// Preparar parámetros específicos según el método de pago
switch ($paymentMethod) {
    case 'webpay':
        // Webpay Plus (Transbank) - Parámetros reales
        $params = [
            'TBK_TOKEN' => $token,
            'TBK_ID_SESION' => session_id(),
            'TBK_ORDEN_COMPRA' => $orderId,
            'TBK_MONTO' => $amount,
            'payment_method' => 'webpay',
        ];
        break;
    
    case 'mercadopago':
        // Mercado Pago - Parámetros reales
        $params = [
            'preference_id' => 'MP-' . $transactionId,
            'external_reference' => $orderId,
            'init_point' => 'simulator',
            'amount' => $amount,
            'payment_method' => 'mercadopago',
        ];
        break;
    
    case 'paypal':
        // PayPal - Parámetros reales
        $params = [
            'token' => 'EC-' . substr($token, 0, 17),
            'order_id' => $orderId,
            'amount' => $amount,
            'currency' => 'EUR',
            'payment_method' => 'paypal',
        ];
        break;
    
    case 'bank_transfer':
        // Transferencia bancaria genérica
        $params = [
            'reference' => $transactionId,
            'order_id' => $orderId,
            'amount' => $amount,
            'payment_method' => 'bank_transfer',
        ];
        break;
    
    default:
        die('Método de pago no soportado');
}

// Agregar URL de retorno
$params['return_url'] = $_SESSION['pending_transaction']['return_url'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirigiendo al Portal de Pago...</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .loader {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body onload="document.getElementById('bank_form').submit();">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-lg">
                    <div class="card-body text-center p-5">
                        <div class="loader mx-auto mb-4"></div>
                        <h4 class="mb-3">Redirigiendo al Portal de Pago Seguro</h4>
                        <p class="text-muted mb-4">
                            <i class="bi bi-shield-lock-fill text-success"></i>
                            Conexión segura establecida
                        </p>
                        <div class="alert alert-info">
                            <small>
                                <strong>Método:</strong> <?php echo ucfirst($paymentMethod); ?><br>
                                <strong>Monto:</strong> $<?php echo number_format($amount, 2, ',', '.'); ?> €<br>
                                <strong>Orden:</strong> <?php echo $orderId; ?>
                            </small>
                        </div>
                        <p class="small text-muted">
                            No cierres esta ventana. Serás redirigido automáticamente...
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulario automático de redirección -->
    <form id="bank_form" action="simulator.php" method="POST" style="display: none;">
        <?php foreach ($params as $key => $value): ?>
            <input type="hidden" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>">
        <?php endforeach; ?>
    </form>

    <script>
        // Timeout de seguridad por si no se envía automáticamente
        setTimeout(function() {
            document.getElementById('bank_form').submit();
        }, 2000);
    </script>
</body>
</html>