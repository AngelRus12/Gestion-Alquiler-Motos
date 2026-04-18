<?php
/**
 * SIMULADOR BANCARIO - Ejemplo de Integración
 * 
 * Este archivo muestra cómo integrar el simulador en tu proyecto existente.
 * Incluye ejemplos prácticos y casos de uso comunes.
 * 
 * NOTA: Este es un archivo de ejemplo. No se ejecuta directamente.
 */

// ========================================
// EJEMPLO 1: Integración Básica
// ========================================

/**
 * Caso de uso: Tienes un carrito de compras y quieres procesar el pago
 */
function ejemploIntegracionBasica() {
    // Supongamos que tienes estos datos del carrito
    $carrito = [
        'total' => 49990,
        'items' => [
            ['nombre' => 'Producto 1', 'precio' => 29990],
            ['nombre' => 'Producto 2', 'precio' => 20000]
        ],
        'cliente' => [
            'email' => 'cliente@ejemplo.com',
            'nombre' => 'Juan Pérez'
        ]
    ];
    
    // Generar orden de compra
    $ordenId = 'ORD-' . time();
    
    // HTML del botón de pago
    ?>
    <form action="payment/checkout.php" method="POST">
        <input type="hidden" name="amount" value="<?php echo $carrito['total']; ?>">
        <input type="hidden" name="order_id" value="<?php echo $ordenId; ?>">
        <input type="hidden" name="description" value="Compra en Mi Tienda">
        
        <!-- Selector de método de pago -->
        <select name="payment_method" class="form-select" required>
            <option value="">Selecciona un método</option>
            <option value="webpay">Webpay Plus</option>
            <option value="mercadopago">Mercado Pago</option>
            <option value="paypal">PayPal</option>
        </select>
        
        <button type="submit" class="btn btn-primary mt-3">
            Pagar $<?php echo number_format($carrito['total'], 0, ',', '.'); ?>
        </button>
    </form>
    <?php
}

// ========================================
// EJEMPLO 2: Validar Respuesta de Pago
// ========================================

/**
 * Caso de uso: Procesar la respuesta después del pago
 */
function ejemploValidarRespuesta() {
    session_start();
    
    // Recuperar transacción
    $transaction = $_SESSION['last_transaction'] ?? null;
    
    if (!$transaction) {
        return [
            'success' => false,
            'message' => 'No se encontró información de la transacción'
        ];
    }
    
    // Validar según estado
    switch ($transaction['status']) {
        case 'approved':
            // PAGO EXITOSO - Actualizar base de datos
            return [
                'success' => true,
                'message' => 'Pago aprobado correctamente',
                'transaction_id' => $transaction['transaction_id'],
                'authorization_code' => $transaction['authorization_code']
            ];
            
        case 'rejected':
            // PAGO RECHAZADO - Notificar al usuario
            return [
                'success' => false,
                'message' => 'El pago fue rechazado. Intenta con otro método.',
                'reason' => $transaction['status_detail']
            ];
            
        case 'pending':
            // PAGO PENDIENTE - Esperar confirmación
            return [
                'success' => false,
                'message' => 'Tu pago está en revisión. Te notificaremos.',
                'pending' => true
            ];
            
        default:
            return [
                'success' => false,
                'message' => 'Estado de pago desconocido',
                'status' => $transaction['status']
            ];
    }
}

// ========================================
// EJEMPLO 3: Actualizar Base de Datos
// ========================================

/**
 * Caso de uso: Guardar transacción en base de datos
 */
function ejemploGuardarEnBD($transaction) {
    // Este es un ejemplo con PDO
    // Adapta según tu framework (Laravel, WordPress, etc.)
    
    try {
        $pdo = new PDO('mysql:host=localhost;dbname=mi_tienda', 'usuario', 'password');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Preparar consulta
        $sql = "INSERT INTO transactions (
            transaction_id,
            order_id,
            payment_method,
            amount,
            status,
            authorization_code,
            response_data,
            created_at
        ) VALUES (
            :transaction_id,
            :order_id,
            :payment_method,
            :amount,
            :status,
            :authorization_code,
            :response_data,
            NOW()
        )";
        
        $stmt = $pdo->prepare($sql);
        
        // Ejecutar
        $stmt->execute([
            ':transaction_id' => $transaction['transaction_id'],
            ':order_id' => $transaction['order_id'],
            ':payment_method' => $transaction['payment_method'],
            ':amount' => $transaction['amount'],
            ':status' => $transaction['status'],
            ':authorization_code' => $transaction['authorization_code'],
            ':response_data' => json_encode($transaction['response_data'])
        ]);
        
        // Si el pago fue exitoso, actualizar orden
        if ($transaction['status'] === 'approved') {
            $sql = "UPDATE orders SET status = 'paid', paid_at = NOW() WHERE order_id = :order_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':order_id' => $transaction['order_id']]);
        }
        
        return true;
        
    } catch (PDOException $e) {
        error_log('Error guardando transacción: ' . $e->getMessage());
        return false;
    }
}

// ========================================
// EJEMPLO 4: Enviar Email de Confirmación
// ========================================

/**
 * Caso de uso: Notificar al cliente por email
 */
function ejemploEnviarEmail($transaction, $clienteEmail) {
    // Preparar contenido del email
    $asunto = ($transaction['status'] === 'approved') 
        ? 'Confirmación de Pago - Orden ' . $transaction['order_id']
        : 'Estado de tu Pago - Orden ' . $transaction['order_id'];
    
    $mensaje = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <h2>Hola,</h2>
        <p>Te informamos sobre el estado de tu pago:</p>
        
        <table style='border-collapse: collapse; width: 100%;'>
            <tr>
                <td style='padding: 10px; border: 1px solid #ddd;'><strong>Orden:</strong></td>
                <td style='padding: 10px; border: 1px solid #ddd;'>{$transaction['order_id']}</td>
            </tr>
            <tr>
                <td style='padding: 10px; border: 1px solid #ddd;'><strong>Monto:</strong></td>
                <td style='padding: 10px; border: 1px solid #ddd;'>$" . number_format($transaction['amount'], 0, ',', '.') . " CLP</td>
            </tr>
            <tr>
                <td style='padding: 10px; border: 1px solid #ddd;'><strong>Estado:</strong></td>
                <td style='padding: 10px; border: 1px solid #ddd;'>" . strtoupper($transaction['status']) . "</td>
            </tr>
            <tr>
                <td style='padding: 10px; border: 1px solid #ddd;'><strong>Fecha:</strong></td>
                <td style='padding: 10px; border: 1px solid #ddd;'>{$transaction['timestamp']}</td>
            </tr>
        </table>
        
        <p>Gracias por tu compra.</p>
        <p><small>Este es un email automático, por favor no responder.</small></p>
    </body>
    </html>
    ";
    
    // Headers
    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: Mi Tienda <noreply@mitienda.com>',
        'Reply-To: soporte@mitienda.com',
        'X-Mailer: PHP/' . phpversion()
    ];
    
    // Enviar email
    return mail($clienteEmail, $asunto, $mensaje, implode("\r\n", $headers));
}

// ========================================
// EJEMPLO 5: Webhook Handler
// ========================================

/**
 * Caso de uso: Recibir notificaciones del banco (webhook)
 * Guardar este código en webhook.php
 */
function ejemploWebhook() {
    // Recibir datos POST
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    // Log de la notificación
    file_put_contents(
        'logs/webhooks.log',
        date('Y-m-d H:i:s') . ' - ' . $json . "\n",
        FILE_APPEND
    );
    
    // Validar firma de seguridad (importante en producción)
    $firma = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
    if (!validarFirmaWebhook($data, $firma)) {
        http_response_code(401);
        exit('Firma inválida');
    }
    
    // Procesar según método de pago
    switch ($data['payment_method']) {
        case 'webpay':
            procesarWebhookWebpay($data);
            break;
        case 'mercadopago':
            procesarWebhookMercadoPago($data);
            break;
        case 'paypal':
            procesarWebhookPayPal($data);
            break;
    }
    
    // Responder OK
    http_response_code(200);
    echo json_encode(['status' => 'received']);
}

function validarFirmaWebhook($data, $firma) {
    // Implementar según proveedor
    $secretKey = 'tu_clave_secreta';
    $firmaCalculada = hash_hmac('sha256', json_encode($data), $secretKey);
    return hash_equals($firmaCalculada, $firma);
}

function procesarWebhookWebpay($data) {
    // Implementación específica para Webpay
    $orderId = $data['order_id'] ?? '';
    $status = $data['status'] ?? '';
    
    // Aquí actualizarías tu base de datos
    // $db->query("UPDATE orders SET status = ? WHERE id = ?", [$status, $orderId]);
    
    error_log("Webpay Webhook: Orden $orderId -> $status");
    return true;
}

function procesarWebhookMercadoPago($data) {
    // Implementación específica para Mercado Pago
    $paymentId = $data['payment_id'] ?? '';
    $status = $data['status'] ?? '';
    
    // Consultar detalles del pago a la API de Mercado Pago
    // $payment = $mp->get_payment($paymentId);
    
    error_log("Mercado Pago Webhook: Pago $paymentId -> $status");
    return true;
}

function procesarWebhookPayPal($data) {
    // Implementación específica para PayPal
    $paymentId = $data['id'] ?? '';
    $state = $data['state'] ?? '';
    
    // Verificar con la API de PayPal
    // $payment = \PayPal\Api\Payment::get($paymentId, $apiContext);
    
    error_log("PayPal Webhook: Pago $paymentId -> $state");
    return true;
}

// ========================================
// EJEMPLO 6: Consultar Estado de Pago
// ========================================

/**
 * Caso de uso: Consultar el estado de una transacción
 */
function ejemploConsultarEstado($transactionId) {
    // En desarrollo: buscar en sesión
    session_start();
    $transacciones = $_SESSION['transacciones'] ?? [];
    
    foreach ($transacciones as $tx) {
        if ($tx['transaction_id'] === $transactionId) {
            return $tx;
        }
    }
    
    // En producción: consultar a la API del proveedor
    // Ejemplo con Webpay:
    /*
    $transaction = new \Transbank\Webpay\WebpayPlus\Transaction();
    $response = $transaction->status($transactionId);
    return $response;
    */
    
    return null;
}

// ========================================
// EJEMPLO 7: Manejo de Errores
// ========================================

/**
 * Caso de uso: Manejar errores de forma elegante
 */
function ejemploManejoErrores() {
    // Datos de ejemplo
    $datos = [
        'amount' => 10000,
        'order_id' => 'ORD-123',
        'payment_method' => 'webpay'
    ];
    
    try {
        // Intentar procesar pago
        $resultado = procesarPago($datos);
        
        if ($resultado['success']) {
            // Éxito
            return [
                'status' => 'success',
                'message' => 'Pago procesado correctamente',
                'data' => $resultado
            ];
        } else {
            // Falló pero controlado
            return [
                'status' => 'error',
                'message' => $resultado['message'],
                'code' => $resultado['code']
            ];
        }
        
    } catch (Exception $e) {
        // Error no esperado
        error_log('Error procesando pago: ' . $e->getMessage());
        
        return [
            'status' => 'error',
            'message' => 'Ocurrió un error inesperado. Intenta nuevamente.',
            'code' => 'INTERNAL_ERROR'
        ];
    }
}

function procesarPago($datos) {
    // Función de ejemplo para procesar pago
    // En tu implementación real, aquí llamarías a la API del proveedor
    
    if (!isset($datos['amount']) || $datos['amount'] <= 0) {
        return [
            'success' => false,
            'message' => 'Monto inválido',
            'code' => 'INVALID_AMOUNT'
        ];
    }
    
    // Simular procesamiento
    return [
        'success' => true,
        'message' => 'Pago procesado',
        'transaction_id' => 'TXN-' . uniqid(),
        'authorization_code' => rand(100000, 999999)
    ];
}

// ========================================
// EJEMPLO 8: Testing Automatizado
// ========================================

/**
 * Caso de uso: Crear tests automáticos
 */
class PaymentSimulatorTest {
    
    public function testPagoAprobado() {
        $datos = [
            'amount' => 10000,
            'order_id' => 'TEST-001',
            'payment_method' => 'webpay',
            'response_type' => 'approved'
        ];
        
        $resultado = simularPago($datos);
        
        assert($resultado['status'] === 'approved');
        assert(isset($resultado['authorization_code']));
        assert($resultado['amount'] === 10000);
        
        echo "✓ Test pago aprobado: PASS\n";
    }
    
    public function testPagoRechazado() {
        $datos = [
            'amount' => 10000,
            'order_id' => 'TEST-002',
            'payment_method' => 'webpay',
            'response_type' => 'rejected'
        ];
        
        $resultado = simularPago($datos);
        
        assert($resultado['status'] === 'rejected');
        assert(!isset($resultado['authorization_code']) || $resultado['authorization_code'] === null);
        
        echo "✓ Test pago rechazado: PASS\n";
    }
    
    public function runAllTests() {
        $this->testPagoAprobado();
        $this->testPagoRechazado();
        echo "\n✓ Todos los tests pasaron exitosamente\n";
    }
}

function simularPago($datos) {
    // Función de ejemplo para simular un pago
    // Esta función replica lo que hace el simulador
    
    $responseType = $datos['response_type'] ?? 'approved';
    
    $response = [
        'transaction_id' => strtoupper(uniqid('TXN-')),
        'payment_method' => $datos['payment_method'],
        'amount' => $datos['amount'],
        'order_id' => $datos['order_id'],
        'timestamp' => date('Y-m-d H:i:s'),
    ];
    
    if ($responseType === 'approved') {
        $response['status'] = 'approved';
        $response['authorization_code'] = rand(100000, 999999);
    } else {
        $response['status'] = $responseType;
        $response['authorization_code'] = null;
    }
    
    return $response;
}

// ========================================
// EJEMPLO 9: Integración con Laravel
// ========================================

/**
 * Caso de uso: Usar el simulador en Laravel
 * NOTA: Este es código de ejemplo. Requiere Laravel para funcionar.
 */

/*
// app/Http/Controllers/PaymentController.php
// Este código requiere Laravel y sus dependencias

use Illuminate\Http\Request;
use App\Models\Order;

class PaymentController extends Controller {
    
    public function checkout(Request $request) {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:100',
            'payment_method' => 'required|in:webpay,mercadopago,paypal'
        ]);
        
        // Crear orden
        $order = Order::create([
            'user_id' => auth()->id(),
            'amount' => $validated['amount'],
            'status' => 'pending'
        ]);
        
        // Redirigir al simulador
        return view('payment.redirect', [
            'amount' => $validated['amount'],
            'order_id' => $order->id,
            'payment_method' => $validated['payment_method']
        ]);
    }
    
    public function callback(Request $request) {
        $transaction = session('last_transaction');
        
        if ($transaction['status'] === 'approved') {
            $order = Order::find($transaction['order_id']);
            $order->update(['status' => 'paid']);
            
            return redirect()->route('order.success', $order);
        }
        
        return redirect()->route('order.failed');
    }
}
*/

// ========================================
// EJEMPLO 10: Integración con WordPress
// ========================================

/**
 * Caso de uso: Usar el simulador en WooCommerce
 * NOTA: Este es código de ejemplo. Requiere WordPress y WooCommerce.
 */

/*
// wp-content/plugins/mi-plugin/payment-gateway.php
// Este código requiere WordPress y WooCommerce

class WC_Gateway_Simulator extends WC_Payment_Gateway {
    
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        
        // Datos para el simulador
        $payment_url = get_site_url() . '/payment/checkout.php';
        
        return [
            'result' => 'success',
            'redirect' => add_query_arg([
                'amount' => $order->get_total(),
                'order_id' => $order_id,
                'payment_method' => 'webpay'
            ], $payment_url)
        ];
    }
}
*/

// ========================================
// NOTAS IMPORTANTES
// ========================================

/**
 * 1. SEGURIDAD:
 *    - Siempre valida firmas en webhooks
 *    - No confíes en datos del cliente
 *    - Usa HTTPS en producción
 * 
 * 2. TESTING:
 *    - Prueba todos los escenarios (aprobado, rechazado, etc.)
 *    - Verifica timeouts y errores de red
 *    - Simula cargas concurrentes
 * 
 * 3. PRODUCCIÓN:
 *    - Cambia USE_SIMULATOR a false
 *    - Usa credenciales reales
 *    - Implementa logging robusto
 *    - Monitorea transacciones
 * 
 * 4. BASE DE DATOS:
 *    - Guarda todas las transacciones
 *    - Mantén historial de cambios de estado
 *    - Implementa respaldos regulares
 * 
 * 5. SOPORTE:
 *    - Documenta el flujo de pago
 *    - Provee mensajes claros al usuario
 *    - Ten procedimientos de reembolso
 */
