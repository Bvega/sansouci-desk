<?php
require 'config.php';

// INCLUIR PHPMailer UNA SOLA VEZ
if (!class_exists('PHPMailer\PHPMailer\PHPMailer')) {
    require 'phpmailer/src/Exception.php';
    require 'phpmailer/src/PHPMailer.php';
    require 'phpmailer/src/SMTP.php';
}

$mensaje = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $cliente_email = trim($_POST['cliente_email'] ?? '');
    $asunto = trim($_POST['asunto'] ?? '');
    $mensaje_text = trim($_POST['mensaje'] ?? '');
    $tipo_servicio = trim($_POST['tipo_servicio'] ?? 'General');
    
    if(empty($cliente_email) || empty($asunto) || empty($mensaje_text)){
        $error = "Todos los campos son obligatorios";
    } else {
        try {
                        // INSERTAR TICKET (numero se genera automáticamente por trigger)
            $stmt = $pdo->prepare("INSERT INTO tickets (cliente_email, asunto, mensaje, tipo_servicio, estado) 
                                   VALUES (?, ?, ?, ?, 'abierto')");
            $stmt->execute([$cliente_email, $asunto, $mensaje_text, $tipo_servicio]);
            $ticket_id = $pdo->lastInsertId();
            
            // OBTENER EL NÚMERO GENERADO
            $stmt = $pdo->prepare("SELECT numero FROM tickets WHERE id = ?");
            $stmt->execute([$ticket_id]);
            $numero = $stmt->fetchColumn();
            
            // INSERTAR RESPUESTA DEL CLIENTE
            $stmt = $pdo->prepare("INSERT INTO respuestas (ticket_id, mensaje, autor, autor_email) 
                                   VALUES (?, ?, 'cliente', ?)");
            $stmt->execute([$ticket_id, $mensaje_text, $cliente_email]);

            // === ENVÍO DE NOTIFICACIÓN A CLIENTE + CORREOS DE NOTIFICACIÓN ===
            $stmt = $pdo->query("SELECT * FROM config_email LIMIT 1");
            $config_email = $stmt->fetch();

            if(!$config_email || empty($config_email['smtp_user'])){
                $mensaje = "Ticket #$numero creado. Configura el correo en Mantenimiento → Config. Email";
            } else {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = $config_email['smtp_host'];
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $config_email['smtp_user'];
                    $mail->Password   = $config_email['smtp_pass'];
                    $mail->SMTPSecure = $config_email['smtp_encriptacion'] ?? '';
                    $mail->Port       = $config_email['smtp_port'];
                    $mail->CharSet    = 'UTF-8';

                    $mail->setFrom($config_email['smtp_from_email'], $config_email['smtp_from_name']);
                    $mail->addAddress($cliente_email); // AL CLIENTE

                    // NOTIFICAR A CORREOS DE LA CONFIGURACIÓN
                    $destinos = array_filter(array_map('trim', explode(',', $config_email['correos_notificacion'] ?? '')));
                    foreach ($destinos as $to) {
                        if($to != $cliente_email){
                            $mail->addBCC($to); // BCC PARA NO MOSTRAR CORREOS
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
                            Tel: (809) 555-1234 | Email: soporte@sansouci.com.do
                        </p>
                    </div>";

                    $mail->send();
                    $mensaje = "¡Ticket #$numero creado con éxito! Notificación enviada a todos.";
                } catch (Exception $e) {
                    $mensaje = "Ticket #$numero creado. Error de correo: " . $mail->ErrorInfo;
                }
            }
        } catch(Exception $e){
            $error = "Error del sistema: " . $e->getMessage();
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

            <form method="POST" class="space-y-16">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-16">
                    <div>
                        <label class="block text-4xl font-bold text-blue-900 mb-8">
                            <i class="fas fa-envelope mr-6"></i> Tu Correo
                        </label>
                        <input type="email" name="cliente_email" required 
                               class="w-full p-10 border-8 border-blue-300 rounded-3xl text-3xl focus:border-blue-900 transition shadow-2xl"
                               placeholder="tucorreo@cliente.com">
                    </div>
                    <div>
                        <label class="block text-4xl font-bold text-blue-900 mb-8">
                            <i class="fas fa-concierge-bell mr-6"></i> Tipo de Servicio
                        </label>
                        <select name="tipo_servicio" required class="w-full p-10 border-8 border-blue-300 rounded-3xl text-3xl font-bold bg-gradient-to-r from-purple-50 to-purple-100">
                            <?php
                            $stmt = $pdo->query("SELECT nombre FROM tipos_servicio ORDER BY nombre");
                            while($row = $stmt->fetch()){
                                echo "<option value='" . htmlspecialchars($row['nombre']) . "'>" . htmlspecialchars($row['nombre']) . "</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-4xl font-bold text-blue-900 mb-8">
                        <i class="fas fa-tag mr-6"></i> Asunto
                    </label>
                    <input type="text" name="asunto" required 
                           class="w-full p-10 border-8 border-blue-300 rounded-3xl text-3xl focus:border-blue-900 transition shadow-2xl"
                           placeholder="Resumen de tu solicitud">
                </div>

                <div>
                    <label class="block text-4xl font-bold text-blue-900 mb-8">
                        <i class="fas fa-comment-dots mr-6"></i> Detalles
                    </label>
                    <textarea name="mensaje" rows="12" required 
                              class="w-full p-10 border-8 border-blue-300 rounded-3xl text-3xl focus:border-blue-900 transition resize-none shadow-2xl"
                              placeholder="Describe con detalle tu requerimiento..."></textarea>
                </div>

                <div class="text-center pt-16">
                    <button type="submit" 
                            class="bg-gradient-to-r from-green-600 to-green-500 text-white px-64 py-20 rounded-full text-6xl font-bold hover:from-green-700 hover:to-green-600 shadow-3xl transform hover:scale-110 transition duration-300">
                        <i class="fas fa-paper-plane mr-12"></i>
                        ENVIAR SOLICITUD
                    </button>
                </div>
            </form>

            <div class="mt-32 text-center">
                <p class="text-3xl text-gray-600">¿Ya tienes un ticket?</p>
                <a href="portal_cliente.php" class="text-blue-900 font-bold text-4xl hover:underline mt-8 inline-block">
                    Ver mis tickets existentes
                </a>
            </div>
        </div>
    </div>
</body>
</html>