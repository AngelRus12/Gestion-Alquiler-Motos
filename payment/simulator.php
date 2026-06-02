<?php
/**
 * SIMULADOR BANCARIO - Portal de Pago
 * 
 * Este archivo simula el portal del banco o pasarela de pago.
 * Aquí el usuario (desarrollador) puede elegir qué tipo de respuesta quiere probar.
 * 
 * Simula las interfaces de:
 * - Webpay Plus (Transbank)
 * - Mercado Pago
 * - PayPal
 * - Transferencia Bancaria
 */

session_start();

// Capturar datos que vienen desde checkout.php
$paymentMethod = $_POST['payment_method'] ?? 'unknown';
$amount = $_POST['amount'] ?? 0;
$returnUrl = $_POST['return_url'] ?? '';

// Parámetros específicos según método de pago
$token = $_POST['TBK_TOKEN'] ?? $_POST['token'] ?? $_POST['preference_id'] ?? $_POST['reference'] ?? '';
$orderId = $_POST['TBK_ORDEN_COMPRA'] ?? $_POST['external_reference'] ?? $_POST['order_id'] ?? '';

// Guardar en sesión para usar en callback
$_SESSION['simulator_data'] = [
    'payment_method' => $paymentMethod,
    'amount' => $amount,
    'token' => $token,
    'order_id' => $orderId,
    'return_url' => $returnUrl,
    'timestamp' => time()
];

// Configuración de marca según método de pago
$paymentConfig = [
    'webpay' => [
        'name' => 'Webpay Plus',
        'logo' => 'Transbank',
        'color' => 'primary',
        'icon' => 'credit-card'
    ],
    'mercadopago' => [
        'name' => 'Mercado Pago',
        'logo' => 'Mercado Pago',
        'color' => 'info',
        'icon' => 'wallet2'
    ],
    'paypal' => [
        'name' => 'PayPal',
        'logo' => 'PayPal',
        'color' => 'primary',
        'icon' => 'paypal'
    ],
    'bank_transfer' => [
        'name' => 'Transferencia Bancaria',
        'logo' => 'Banco',
        'color' => 'secondary',
        'icon' => 'bank'
    ]
];

$config = $paymentConfig[$paymentMethod] ?? $paymentConfig['webpay'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $config['name']; ?> - Portal de Pago Seguro</title>
    <link rel="icon" href="../logo.png" type="image/png">
    <link rel="stylesheet" href="../estilos.css">
    <link rel="stylesheet" href="../estilos_mobile.css">
</head>
<body class="login-body">

    <div class="pago-container" style="max-width: 650px;">
        <div class="pago-header">
            <p>Total a Pagar</p>
            <h1 class="monto-valor"><?php echo number_format($amount, 2, ',', '.'); ?> €</h1>
            <p class="info-alquiler">Comercio: ARUSLAT Motos (Orden: <?php echo htmlspecialchars($orderId); ?>)</p>
        </div>

        <div class="pago-body">
            <h3 class="titulo-seccion-pago">Simulador de Pasarela de Pago</h3>
            <p class="text-center" style="color: var(--texto-gris); margin-bottom: 30px;">
                Esta es una página de simulación. Elige una respuesta para continuar.
            </p>

            <div class="form-grid-2-col">
                <!-- APROBADO / ÉXITO -->
                <div class="form-group">
                    <form action="callback.php" method="POST">
                        <input type="hidden" name="response_type" value="approved">
                        <button type="submit" class="btn btn-block">
                            ✔️ Aprobar Pago
                        </button>
                    </form>
                </div>

                <!-- RECHAZADO -->
                <div class="form-group">
                    <form action="callback.php" method="POST">
                        <input type="hidden" name="response_type" value="rejected">
                        <button type="submit" class="btn btn-block boton-error">
                            ❌ Rechazar Pago
                        </button>
                    </form>
                </div>

                <!-- PENDIENTE -->
                <div class="form-group">
                    <form action="callback.php" method="POST">
                        <input type="hidden" name="response_type" value="pending">
                        <button type="submit" class="btn btn-block boton-secundario" style="background-color: #4a3e20; color: #ffc107;">
                            ⏳ Dejar como Pendiente
                        </button>
                    </form>
                </div>

                <!-- CANCELADO POR USUARIO -->
                <div class="form-group">
                    <form action="callback.php" method="POST">
                        <input type="hidden" name="response_type" value="cancelled">
                        <button type="submit" class="btn btn-block boton-secundario">
                            ↪️ Cancelar por Usuario
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</body>
</html>