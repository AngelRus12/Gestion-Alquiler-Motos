<?php
session_start();

// Seguridad: Si no hay un pago pendiente, redirigir al catálogo
if (!isset($_SESSION['id_pago_pendiente'])) {
    header('Location: catalogo.php');
    exit();
}

$monto = $_SESSION['monto_pago'];

// Función para detectar dispositivos móviles
function isMobile() {
    return preg_match("/(android|avantgo|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino)/i", $_SERVER["HTTP_USER_AGENT"]);
}

$is_mobile = isMobile();
$id_alquiler = $_SESSION['id_pago_pendiente'];

// Preparar datos para el simulador de pago
$amount = $monto; // Monto en euros
$order_id = 'ALQ-' . $id_alquiler . '-' . time();
$description = 'Alquiler de motocicleta - ID: ' . $id_alquiler;

// URL de retorno después del pago
$return_url = 'http://' . $_SERVER['HTTP_HOST'] . '/perfil_usuario.php';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pago Seguro - ARUSLAT</title>
    <?php if ($is_mobile): ?>
        <link rel="stylesheet" href="estilos_mobile.css">
    <?php else: ?>
        <link rel="stylesheet" href="estilos.css">
    <?php endif; ?>
    <style>
        /* Estilos para la selección de método de pago */
        .pago-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 30px;
            background: var(--bg-tarjeta);
            border: 1px solid var(--borde-tarjeta);
            border-radius: var(--borde-redondeado);
            box-shadow: var(--sombre-tarjeta);
        }

        .monto-display {
            text-align: center;
            margin-bottom: 30px;
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }

        .monto-display p {
            font-size: 0.9em;
            color: var(--texto-gris);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }

        .monto-valor {
            font-size: 3.2rem;
            color: var(--naranja-principal);
            font-weight: 800;
            display: block;
        }

        .metodo-pago {
            margin: 20px 0;
            border: 1px solid var(--borde-tarjeta);
            border-radius: 8px;
            overflow: hidden;
        }

        .metodo-option {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .metodo-option:last-child {
            border-bottom: none;
        }

        .metodo-option:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .metodo-option input[type="radio"] {
            margin-right: 15px;
            transform: scale(1.2);
        }

        .metodo-info {
            flex: 1;
        }

        .metodo-info h4 {
            margin: 0 0 5px 0;
            color: var(--texto-blanco);
            font-size: 1.1em;
        }

        .metodo-info p {
            margin: 0;
            color: var(--texto-gris);
            font-size: 0.9em;
        }

        .metodo-logo {
            width: 60px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8em;
            color: var(--texto-gris);
        }

        .btn-confirmar {
            width: 100%;
            padding: 15px;
            background: var(--naranja-principal);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .btn-confirmar:hover {
            background: #e55a00;
            transform: translateY(-2px);
        }

        .btn-confirmar:disabled {
            background: var(--texto-gris);
            cursor: not-allowed;
            transform: none;
        }

        .btn-cancelar {
            display: block;
            text-align: center;
            text-decoration: none;
            padding: 12px;
            margin-top: 10px;
            border: 1px solid #44332f;
            color: var(--texto-gris);
            border-radius: 8px;
            font-size: 0.9em;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-cancelar:hover {
            background: rgba(255, 255, 255, 0.05);
            color: var(--texto-blanco);
            border-color: var(--texto-gris);
        }
    </style>
</head>
<body>

    <main class="container">
        <div class="pago-container">
            <div class="monto-display">
                <p>Total a pagar</p>
                <span class="monto-valor"><?php echo number_format($monto, 2); ?>€</span>
            </div>

            <h3 style="color: white; text-align: center; margin-bottom: 20px;">Selecciona tu método de pago</h3>

            <form action="payment/checkout.php" method="POST" id="paymentForm">
                <!-- Datos ocultos -->
                <input type="hidden" name="amount" value="<?php echo $amount; ?>">
                <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
                <input type="hidden" name="description" value="<?php echo $description; ?>">
                <input type="hidden" name="return_url" value="<?php echo $return_url; ?>">
                <input type="hidden" name="auto_redirect_on_approved" value="true">
                <input type="hidden" name="redirect_delay_ms" value="3000">

                <div class="metodo-pago">
                    <!-- Webpay Plus -->
                    <div class="metodo-option" onclick="selectPayment('webpay')">
                        <input type="radio" name="payment_method" id="webpay" value="webpay" checked>
                        <div class="metodo-info">
                            <h4>Webpay Plus</h4>
                            <p>Tarjetas de crédito y débito - Seguro y rápido</p>
                        </div>
                        <div class="metodo-logo">Webpay</div>
                    </div>

                    <!-- Mercado Pago -->
                    <div class="metodo-option" onclick="selectPayment('mercadopago')">
                        <input type="radio" name="payment_method" id="mercadopago" value="mercadopago">
                        <div class="metodo-info">
                            <h4>Mercado Pago</h4>
                            <p>Tarjetas, efectivo y más opciones de pago</p>
                        </div>
                        <div class="metodo-logo">MP</div>
                    </div>

                    <!-- PayPal -->
                    <div class="metodo-option" onclick="selectPayment('paypal')">
                        <input type="radio" name="payment_method" id="paypal" value="paypal">
                        <div class="metodo-info">
                            <h4>PayPal</h4>
                            <p>Paga con tu cuenta PayPal de forma segura</p>
                        </div>
                        <div class="metodo-logo">PayPal</div>
                    </div>

                    <!-- Transferencia Bancaria -->
                    <div class="metodo-option" onclick="selectPayment('bank_transfer')">
                        <input type="radio" name="payment_method" id="bank_transfer" value="bank_transfer">
                        <div class="metodo-info">
                            <h4>Transferencia Bancaria</h4>
                            <p>Transferencia manual desde tu banco</p>
                        </div>
                        <div class="metodo-logo">Banco</div>
                    </div>
                </div>

                <button type="submit" class="btn-confirmar" id="confirmBtn">
                    Confirmar Pago Seguro
                </button>

                <a href="catalogo" class="btn-cancelar">
                    ✕ Cancelar y volver
                </a>
            </form>
        </div>
    </main>

    <script>
        function selectPayment(method) {
            // Marcar el radio button seleccionado
            document.getElementById(method).checked = true;

            // Resaltar la opción seleccionada
            const options = document.querySelectorAll('.metodo-option');
            options.forEach(option => {
                option.style.background = 'transparent';
                option.style.borderLeft = 'none';
            });

            const selectedOption = document.querySelector(`input[value="${method}"]`).closest('.metodo-option');
            selectedOption.style.background = 'rgba(255, 152, 0, 0.1)';
            selectedOption.style.borderLeft = '4px solid var(--naranja-principal)';
        }

        // Seleccionar Webpay por defecto al cargar
        window.onload = function() {
            selectPayment('webpay');
        };
    </script>

    <?php if (isset($_SESSION['usuario_id'])): ?>
    <script src="logout_session.js"></script>
    <?php endif; ?>

</body>
</html>