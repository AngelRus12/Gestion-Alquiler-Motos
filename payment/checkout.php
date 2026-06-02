<?php
/**
 * =================================================================
 * SIMULADOR DE PASARELA DE PAGO - CHECKOUT
 * =================================================================
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

// --- 1. VALIDACIÓN DE DATOS DE ENTRADA ---
// Se asegura de que los datos mínimos (método de pago y monto) han sido enviados por POST.
if (!isset($_POST['payment_method']) || !isset($_POST['amount'])) {
    die('Error: Datos de pago incompletos');
}

// --- 2. CAPTURA Y SANITIZACIÓN DE DATOS ---
// Se capturan los datos de la transacción enviados desde el formulario de `pago.php`.
$paymentMethod = $_POST['payment_method'];
$amount = (float)($_POST['amount'] ?? 0); // Asegurar que el monto sea un número flotante
$orderId = $_POST['order_id'] ?? 'ORD-' . time();
$description = $_POST['description'] ?? 'Compra en tienda';

// Se genera un ID de transacción único para el simulador.
$transactionId = strtoupper(uniqid('TXN-'));

// --- 3. GESTIÓN DE LA URL DE RETORNO (CALLBACK) ---
// Se detecta la URL base del proyecto dinámicamente para construir URLs absolutas.
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$baseUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');

// Se establece una URL de retorno por defecto, que es el script que procesará el pago en nuestra aplicación.
$defaultReturnUrl = $protocol . '://' . $_SERVER['HTTP_HOST'] . '/callback_pago.php'; // Explicitly set to the correct callback URL
$rawReturnUrl = trim((string)($_POST['return_url'] ?? ''));

// --- 4. MEDIDA DE SEGURIDAD: LISTA BLANCA DE HOSTS ---
// Para evitar redirecciones maliciosas (Open Redirect), se valida la URL de retorno
// contra una lista blanca de dominios permitidos.
$serverHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? 'localhost'));
$serverHost = preg_replace('/:\\d+$/', '', $serverHost);
$allowedReturnHosts = [
    $serverHost,
    'localhost',
    '127.0.0.1',
    'domino9.regline.cl',
];

// (Opcional) Permite añadir más hosts permitidos a través de variables de entorno.
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

// Se valida la URL de retorno proporcionada. Si es válida, se usa; si no, se usa la de por defecto.
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

// --- 5. CONFIGURACIÓN ADICIONAL ---
// Se configuran opciones para el comportamiento del callback, como la redirección automática.
$rawAutoRedirectOnApproved = $_POST['auto_redirect_on_approved'] ?? 'true';
$autoRedirectOnApproved = filter_var($rawAutoRedirectOnApproved, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
if ($autoRedirectOnApproved === null) {
    $autoRedirectOnApproved = true;
}
$redirectDelayMs = isset($_POST['redirect_delay_ms']) ? (int)$_POST['redirect_delay_ms'] : 2000;
$redirectDelayMs = max(0, min($redirectDelayMs, 15000));

// --- 6. GUARDAR DATOS DE LA TRANSACCIÓN EN SESIÓN ---
// Se guardan todos los datos de la transacción pendiente en la sesión.
// En una aplicación real, esto se registraría en una base de datos con estado 'pendiente'. Se usa 'last_transaction' para que callback_pago.php lo pueda leer.
$_SESSION['last_transaction'] = [
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

// Se genera un token de seguridad para la sesión, para verificar la integridad en el siguiente paso.
$token = hash('sha256', $transactionId . session_id() . time());
$_SESSION['transaction_token'] = $token;

// --- 7. PREPARAR PARÁMETROS PARA EL SIMULADOR ---
// Se preparan los parámetros que se enviarán al `simulator.php`.
// Cada método de pago tiene sus propios nombres de parámetros, y aquí se simula esa diferencia.
switch ($paymentMethod) {
    case 'webpay':
        // Webpay Plus (Transbank) - Parámetros reales
        $params = [
            'TBK_TOKEN' => $token,
            'TBK_ID_SESION' => session_id(),
            'TBK_ORDEN_COMPRA' => $orderId,
            'TBK_MONTO' => $amount,
            'payment_method' => 'webpay',
            'amount' => $amount, // Se añade para que el simulador lo muestre correctamente.
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

// Se añade la URL de retorno a los parámetros que se enviarán.
$params['return_url'] = $_SESSION['last_transaction']['return_url'];
?>
<!-- Esta página muestra un mensaje de "Redirigiendo..." y envía automáticamente
     un formulario oculto a `simulator.php` con todos los parámetros de la transacción. -->
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="../logo.png" type="image/png">
    <title>Redirigiendo al Portal de Pago...</title>
    <link rel="stylesheet" href="../estilos.css">
    <link rel="stylesheet" href="../estilos_mobile.css">
</head>
<body class="login-body" onload="document.getElementById('bank_form').submit();">
    <div class="pago-container">
        <div class="pago-body text-center">
            <div class="loader-container">
                <div class="loader"></div>
            </div>
            <h3 class="titulo-seccion-pago">Redirigiendo al pago seguro</h3>
            <p class="texto-gris">Estás siendo transferido al portal de pago. Por favor, espera.</p>
            
            <div class="resumen-pago-redirect">
                <p><strong>Método:</strong> <?php echo htmlspecialchars(ucfirst($paymentMethod)); ?></p>
                <p><strong>Monto:</strong> <?php echo htmlspecialchars(number_format($amount, 2, '.', '')); ?> €</p>
                <p><strong>Orden:</strong> <?php echo htmlspecialchars($orderId); ?></p>
            </div>

            <p class="texto-gris-claro" style="font-size: 12px; margin-top: 20px;">
                <span style="color: #4caf50;">✓</span> Conexión segura establecida.
            </p>
            <div class="form-group text-center btn-cancelar">
                <a href="../perfil_usuario.php?pago=cancelado" class="enlace-discreto">Cancelar y volver</a>
            </div>
        </div>
    </div>

    <!-- Formulario automático de redirección -->
    <form id="bank_form" action="simulator.php" method="POST" class="d-none">
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