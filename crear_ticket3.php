<?php 
ob_start();
require 'config.php';

// INCLUIR PHPMailer
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    require 'phpmailer/src/Exception.php';
    require 'phpmailer/src/PHPMailer.php';
    require 'phpmailer/src/SMTP.php';
}

$mensaje = '';
$error = '';

// === CARGAR CONFIG EMAIL CON VALORES POR DEFECTO ===
$stmt = $pdo->query("SELECT * FROM config_email LIMIT 1");
$config_row = $stmt->fetch(PDO::FETCH_ASSOC);

$config_email = [
    'smtp_host' => '',
    'smtp_port' => 587,
    'smtp_usuario' => '',
    'smtp_clave' => '',
    'smtp_encriptacion' => 'tls',
    'smtp_from_email' => '',  // ← ESTE SERÁ TU CORREO REAL (volante@macrd.com)
    'smtp_from_name' => 'Sansouci Desk',
    'correos_notificacion' => '',
    'activado' => 0
];

if(is_array($config_row)){
    $config_email = array_merge($config_email, $config_row);
}

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $cliente_email = trim($_POST['cliente_email'] ?? '');
    $asunto = trim($_POST['asunto'] ?? '');
    $mensaje_text = trim($_POST['mensaje'] ?? '');
    $tipo_servicio = trim($_POST['tipo_servicio'] ?? 'General');
    
    if(empty($cliente_email) || empty($asunto) || empty($mensaje_text)){
        $error = "Todos los campos son obligatorios";
    } else {
        try {
            // GENERAR NÚMERO DE TICKET
            $stmt = $pdo->query("SELECT COUNT(*) FROM tickets");
            $count = $stmt->fetchColumn();
            $numero = 'TCK-' . date('Y') . str_pad($count + 1, 5, '0', STR_PAD_LEFT);
            
            // INSERTAR TICKET
            $stmt = $pdo->prepare("INSERT INTO tickets (numero, cliente_email, asunto, mensaje, tipo_servicio, estado) 
                                   VALUES (?, ?, ?, ?, ?, 'abierto')");
            $stmt->execute([$numero, $cliente_email, $asunto, $mensaje_text, $tipo_servicio]);
            $ticket_id = $pdo->lastInsertId();
            
            // INSERTAR RESPUESTA DEL CLIENTE
            $stmt = $pdo->prepare("INSERT INTO respuestas (ticket_id, mensaje, autor, autor_email) 
                                   VALUES (?, ?, 'cliente', ?)");
            $stmt->execute([$ticket_id, $mensaje_text, $cliente_email]);

            // === ENVÍO DE CORREO (CORREGIDO PARA OFFICE 365 / GMAIL / ETC.) ===
            if(!empty($config_email['smtp_usuario']) && !empty($config_email['smtp_clave'])){
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = $config_email['smtp_host'];
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $config_email['smtp_usuario'];  // volante@macrd.com
                    $mail->Password   = $config_email['smtp_clave'];
                    $mail->SMTPSecure = $config_email['smtp_encriptacion'] ?: false;
                    $mail->Port       = (int)$config_email['smtp_port'];
                    $mail->CharSet    = 'UTF-8';

                    // ¡¡¡AQUÍ ESTÁ LA CLAVE!!!
                    // El remitente REAL debe ser tu cuenta (volante@macrd.com)
                    $mail->setFrom($config_email['smtp_usuario'], $config_email['smtp_from_name']);
                    
                    // Pero en el encabezado "Reply-To" ponemos soporte@sansouci.com.do
                    $mail->addReplyTo($config_email['smtp_from_email'] ?: $config_email['smtp_usuario'], 'Sansouci Desk');

                    $mail->addAddress($cliente_email);

                    // NOTIFICACIONES EN BCC
                    $destinos = array_filter(array_map('trim', explode(',', $config_email['correos_notificacion'])));
                    foreach ($destinos as $to) {
                        if($to && $to != $cliente_email){
                            $mail->addBCC($to);
                        }
                    }

                    $mail->isHTML(true);
                    $mail->Subject = "Nuevo Ticket #$numero - Sansouci Desk";
                    $mail->Body    = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 15px 40px rgba(0,0,0,0.2); border: 5px solid #003087;'>
                        <img src='https://www.sansouci.com.do/wp-content/uploads/2020/06/logo-sansouci.png' alt='Sansouci' style='height: 80px; display: block; margin: 0 auto 30px;'>
                        <h1 style='color: #003087; text-align: center; font-size: 32px; margin-bottom: 30px;'>TICKET CREADO</h1>
                        <div style='background: #f0f8ff; padding: 30px; border-radius: 15px; text-align: center; border: 3px dashed #003087;'>
                            <p style='font-size: 28px; margin: 15px 0;'><strong>Ticket:</strong> <span style='color: #003087; font-size: 40px; font-weight: bold;'>$numero</span></p>
                            <p style='font-size: 22px; margin: 15px 0;'><strong>Tipo de Servicio:</strong> " . htmlspecialchars($tipo_servicio) . "</p>
                            <p style='font-size: 22px; margin: 15px 0;'><strong>Asunto:</strong> " . htmlspecialchars($asunto) . "</p>
                        </div>
                        <p style='font-size: 20px; text-align: center; margin: 40px 0; color: #333;'>
                            Tu solicitud ha sido recibida y será atendida en menos de <strong>2 horas hábiles</strong>.
                        </p>
                        <div style='text-align: center; margin: 40px 0;'>
                            <a href='http://localhost/sansouci-desk/portal_cliente.php?email=" . urlencode($cliente_email) . "' 
                               style='background: #003087; color: white; padding: 20px 50px; text-decoration: none; border-radius: 50px; font-size: 24px; font-weight: bold; display: inline-block; box-shadow: 0 10px 30px rgba(0,48,135,0.4);'>
                               VER MI TICKET
                            </a>
                        </div>
                        <hr style='margin: 50px 0; border: 2px dashed #003087;'>
                        <p style='text-align: center; color: #666; font-size: 16px;'>
                            Sansouci Puerto de Santo Domingo<br>
                            Email: soporte@sansouci.com.do
                        </p>
                    </div>";

                    $mail->send();
                    $mensaje = "¡Ticket #$numero creado con éxito! Notificación enviada.";
                } catch (Exception $e) {
                    $mensaje = "Ticket #$numero creado. Error de correo: " . htmlspecialchars($mail->ErrorInfo);
                }
            } else {
                $mensaje = "Ticket #$numero creado. Configura el correo en Mantenimiento → Config. Email";
            }
        } catch(Exception $e){
            $error = "Error del sistema: " . htmlspecialchars($e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Ticket - Sansouci Desk</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #001f3f 0%, #003087 100%); }
        .card { backdrop-filter: blur(20px); background: rgba(255, 255, 255, 0.98); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-6">
    <div class="card rounded-3xl shadow-3xl w-full max-w-5xl border-12 border-blue-900">
        <div class="text-center pt-20 pb-12">
            <img src="https://www.sansouci.com.do/wp-content/uploads/2020/06/logo-sansouci.png" alt="Sansouci" class="h-32 mx-auto">
            <h1 class="text-7xl font-bold text-blue-900 mt-10">PORTAL DE CLIENTES</h1>
            <p class="text-4xl text-blue-700 mt-6">Crear Nueva Solicitud</p>
        </div>

        <div class="px-20 pb-24">
            <?php if($mensaje): ?>
            <div class="bg-green-100 border-12 border-green-600 text-green-800 px-16 py-12 rounded-3xl mb-16 text-4xl font-bold text-center shadow-3xl">
                <i class="fas fa-check-circle text-8xl mr-8"></i>
                <?= htmlspecialchars($mensaje) ?>
            </div>
            <?php endif; ?>

            <?php if($error): ?>
            <div class="bg-red-100 border-12 border-red-600 text-red-800 px-16 py-12 rounded-3xl mb-16 text-4xl font-bold text-center shadow-3xl">
                <i class="fas fa-times-circle text-8xl mr-8"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- TU FORMULARIO (igual que antes) -->
            <form method="POST" class="space-y-16">
                <!-- ... todos los campos ... -->
            </form>
        </div>
    </div>
</body>
</html>

<?php ob_end_flush(); ?>