<?php 
ob_start();
require 'header.php';
require 'config.php';

if(!isset($_GET['ticket_id']) || !is_numeric($_GET['ticket_id'])){
    die('<div class="p-20 text-center text-red-600 text-5xl font-bold bg-white rounded-3xl shadow-3xl">Ticket no válido</div>');
}

$ticket_id = intval($_GET['ticket_id']);

// CARGAR TICKET
$stmt = $pdo->prepare("SELECT t.*, u.nombre as agente_nombre FROM tickets t LEFT JOIN users u ON t.agente_id = u.id WHERE t.id = ?");
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch();

if(!$ticket){
    die('<div class="p-20 text-center text-red-600 text-5xl font-bold bg-white rounded-3xl shadow-3xl">Ticket no encontrado</div>');
}

$numero = $ticket['numero'] ?? 'TCK-'.str_pad($ticket['id'],5,'0',STR_PAD_LEFT);

// CARGAR RESPUESTAS
$stmt = $pdo->prepare("SELECT * FROM respuestas WHERE ticket_id = ? ORDER BY creado_el");
$stmt->execute([$ticket_id]);
$respuestas = $stmt->fetchAll();

// CARGAR CONFIG EMAIL
$stmt = $pdo->query("SELECT * FROM config_email LIMIT 1");
$config_email = $stmt->fetch();

// === PROCESAR RESPUESTA ===
$mensaje = '';
if($_POST && isset($_POST['respuesta'])){
    $respuesta = trim($_POST['respuesta']);
    if(empty($respuesta)){
        $mensaje = "La respuesta no puede estar vacía";
    } else {
        try {
            // INSERTAR RESPUESTA
            $stmt = $pdo->prepare("INSERT INTO respuestas (ticket_id, mensaje, autor, autor_email, creado_el) 
                                   VALUES (?, ?, 'agente', ?, NOW())");
            $stmt->execute([$ticket_id, $respuesta, $user['email']]);

            // === ENVÍO DE CORREO AL CLIENTE + NOTIFICACIONES ===
            if($config_email && !empty($config_email['smtp_usuario'])){
                require 'phpmailer/src/Exception.php';
                require 'phpmailer/src/PHPMailer.php';
                require 'phpmailer/src/SMTP.php';

                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                try {
                    $mail->isSMTP();
                    $mail->Host       = $config_email['smtp_host'];
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $config_email['smtp_usuario'];
                    $mail->Password   = $config_email['smtp_clave'];
                    $mail->SMTPSecure = $config_email['smtp_encriptacion'] ?? '';
                    $mail->Port       = $config_email['smtp_port'];
                    $mail->CharSet    = 'UTF-8';

                    $mail->setFrom($config_email['smtp_from_email'], $config_email['smtp_from_name']);
                    $mail->addAddress($ticket['cliente_email']);

                    $destinos = array_filter(array_map('trim', explode(',', $config_email['correos_notificacion'] ?? '')));
                    foreach ($destinos as $to) {
                        if($to && $to != $ticket['cliente_email']){
                            $mail->addBCC($to);
                        }
                    }

                    $mail->isHTML(true);
                    $mail->Subject = "Re: Ticket #$numero - {$ticket['asunto']}";
                    $mail->Body    = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; background: white; padding: 40px; border-radius: 20px; box-shadow: 0 15px 40px rgba(0,0,0,0.2); border: 5px solid #003087;'>
                        <img src='https://www.sansouci.com.do/wp-content/uploads/2020/06/logo-sansouci.png' alt='Sansouci' style='height: 80px; display: block; margin: 0 auto 30px;'>
                        <h1 style='color: #003087; text-align: center; font-size: 32px;'>RESPUESTA A TU TICKET</h1>
                        <div style='background: #f0f8ff; padding: 30px; border-radius: 15px; text-align: center; border: 3px dashed #003087;'>
                            <p style='font-size: 28px; margin: 15px 0;'><strong>Ticket:</strong> <span style='color: #003087; font-size: 40px; font-weight: bold;'>$numero</span></p>
                        </div>
                        <div style='background: #e6f7ff; padding: 30px; border-radius: 15px; margin: 30px 0; border-left: 8px solid #003087;'>
                            <p style='font-size: 20px; color: #003087; font-weight: bold; margin-bottom: 20px;'>Respuesta del agente:</p>
                            <p style='font-size: 18px; color: #333; line-height: 1.8;'>" . nl2br(htmlspecialchars($respuesta)) . "</p>
                        </div>
                        <div style='text-align: center; margin: 40px 0;'>
                            <a href='http://localhost/sansouci-desk/portal_cliente.php?email=" . urlencode($ticket['cliente_email']) . "' 
                               style='background: #003087; color: white; padding: 20px 50px; text-decoration: none; border-radius: 50px; font-size: 24px; font-weight: bold; display: inline-block;'>
                               VER MI TICKET
                            </a>
                        </div>
                    </div>";

                    $mail->send();
                    $mensaje = "Respuesta enviada al cliente y notificaciones";
                } catch (Exception $e) {
                    $mensaje = "Respuesta guardada. Error de correo: " . $mail->ErrorInfo;
                }
            } else {
                $mensaje = "Respuesta guardada. Configura el correo en Mantenimiento → Config. Email";
            }

            header("Location: responder.php?ticket_id=$ticket_id&enviado=1");
            exit();

        } catch(Exception $e){
            $mensaje = "Error del sistema: " . $e->getMessage();
        }
    }
}

$mensaje_exito = (isset($_GET['enviado']) && $_GET['enviado'] == 1);
?>

<!-- TU HTML ÉPICO (mismo que antes) -->
<!-- ... (todo tu diseño) ... -->

<?php 
ob_end_flush();
require 'footer.php'; 
?>