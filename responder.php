<?php
ob_start();
require 'header.php'; // aquí ya tenemos $pdo y $user

// 1. Validar sesión y ticket_id
if (!$user) {
    header('Location: login.php');
    exit();
}

if (!isset($_GET['ticket_id']) || !is_numeric($_GET['ticket_id'])) {
    die('<div class="p-10 text-2xl text-red-700 font-bold">Ticket no válido</div>');
}

$ticket_id = (int) $_GET['ticket_id'];

// 2. Cargar ticket (JOIN con users SOLO por tickets.agente_id)
$stmt = $pdo->prepare("
    SELECT t.*, u.nombre AS agente_nombre
    FROM tickets t
    LEFT JOIN users u ON t.agente_id = u.id
    WHERE t.id = ?
");
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) {
    die('<div class="p-10 text-2xl text-red-700 font-bold">Ticket no encontrado</div>');
}

$numero = $ticket['numero'] ?? ('TCK-' . str_pad($ticket['id'], 5, '0', STR_PAD_LEFT));

// 3. Cargar respuestas
$stmt = $pdo->prepare("
    SELECT *
    FROM respuestas
    WHERE ticket_id = ?
    ORDER BY creado_el ASC
");
$stmt->execute([$ticket_id]);
$respuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Cargar configuración de correo DESDE LA TABLA config_email
$config_row = [];
try {
    $stmt = $pdo->query("SELECT * FROM config_email WHERE id = 1");
    $config_row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    $config_row = [];
}

// Unificación de claves (soporta smtp_user/smtp_pass y smtp_usuario/smtp_clave, etc.)
$emailConfig = [
    'host'        => $config_row['smtp_host'] ?? 'smtp.gmail.com',
    'port'        => isset($config_row['smtp_port']) ? (int)$config_row['smtp_port'] : 587,
    'username'    => $config_row['smtp_user']    ?? ($config_row['smtp_usuario'] ?? ''),
    'password'    => $config_row['smtp_pass']    ?? ($config_row['smtp_clave'] ?? ''),
    'encryption'  => $config_row['smtp_secure']  ?? ($config_row['smtp_encriptacion'] ?? 'tls'),
    'from_email'  => $config_row['from_email']   ?? ($config_row['smtp_from_email'] ?? ''),
    'from_name'   => $config_row['from_name']    ?? ($config_row['smtp_from_name'] ?? 'Sansouci Desk'),
    'notificar'   => $config_row['correos_notificacion'] ?? '',
    'enabled'     => (int)($config_row['activado'] ?? ($config_row['activo'] ?? 1)),
];

// 5. Procesar envío de respuesta
$mensaje_flash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $respuesta    = trim($_POST['respuesta'] ?? '');
    $nuevo_estado = $_POST['estado'] ?? $ticket['estado'];

    if ($respuesta === '') {
        $mensaje_flash = "La respuesta no puede estar vacía.";
    } else {
        try {
            // 5.1 Insertar respuesta
            $stmt = $pdo->prepare("
                INSERT INTO respuestas (ticket_id, mensaje, autor, autor_email, creado_el)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $ticket_id,
                $respuesta,
                $user['nombre'] ?? 'Agente',
                $user['email']  ?? ''
            ]);

            // 5.2 Actualizar estado del ticket
            if (!in_array($nuevo_estado, ['abierto', 'en_proceso', 'cerrado'], true)) {
                $nuevo_estado = 'en_proceso';
            }

            $stmt = $pdo->prepare("
                UPDATE tickets
                SET estado = ?, actualizado_el = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$nuevo_estado, $ticket_id]);

            // 5.3 Enviar correo al cliente (y notificaciones internas)
            if (
                !empty($emailConfig['username']) &&
                !empty($emailConfig['password']) &&
                $emailConfig['enabled'] === 1
            ) {
                require 'PHPMailer/src/Exception.php';
                require 'PHPMailer/src/PHPMailer.php';
                require 'PHPMailer/src/SMTP.php';

                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

                try {
                    $mail->isSMTP();
                    $mail->Host       = $emailConfig['host'];
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $emailConfig['username'];
                    $mail->Password   = $emailConfig['password'];
                    $mail->SMTPSecure = $emailConfig['encryption'] ?: 'tls';
                    $mail->Port       = $emailConfig['port'];

                    $mail->setFrom(
                        $emailConfig['from_email'] ?: $emailConfig['username'],
                        $emailConfig['from_name']
                    );

                    // Cliente
                    if (!empty($ticket['cliente_email'])) {
                        $mail->addAddress($ticket['cliente_email']);
                    }

                    // Correos internos adicionales (separados por coma)
                    if (!empty($emailConfig['notificar'])) {
                        $extras = array_filter(array_map('trim', explode(',', $emailConfig['notificar'])));
                        foreach ($extras as $to) {
                            if ($to !== '') {
                                $mail->addBCC($to);
                            }
                        }
                    }

                    $mail->isHTML(true);
                    $mail->Subject = "Respuesta a tu ticket #$numero - {$ticket['asunto']}";

                    $cuerpo_html = "
                        <div style='font-family: Arial, sans-serif; background:#f5f7fb; padding:30px;'>
                          <div style=\"max-width:650px;margin:0 auto;background:#ffffff;
                                      border-radius:18px;padding:30px 35px;
                                      box-shadow:0 12px 35px rgba(0,0,0,0.12);\">
                            <div style='text-align:center;margin-bottom:25px;'>
                              <img src='https://www.sansouci.com.do/wp-content/uploads/2020/06/logo-sansouci.png'
                                   alt='Sansouci' style='height:70px;'>
                            </div>

                            <h1 style='color:#003087;font-size:24px;margin-bottom:10px;'>
                              Respuesta a tu ticket
                            </h1>
                            <p style='color:#555;font-size:16px;'>
                              Hemos actualizado tu ticket <strong>#{$numero}</strong>.
                            </p>

                            <div style='margin:20px 0;padding:15px 18px;
                                        background:#eef4ff;border-left:6px solid #003087;
                                        border-radius:10px;'>
                              <p style='margin:0;color:#003087;font-size:15px;font-weight:bold;'>
                                Asunto:
                              </p>
                              <p style='margin:4px 0 0 0;color:#333;font-size:15px;'>
                                ".htmlspecialchars($ticket['asunto'])."
                              </p>
                            </div>

                            <div style='margin:20px 0;padding:15px 18px;
                                        background:#fdf5f5;border-radius:10px;'>
                              <p style='margin:0 0 10px 0;color:#b00020;font-size:15px;font-weight:bold;'>
                                Respuesta del agente:
                              </p>
                              <p style='margin:0;color:#333;font-size:15px;line-height:1.6;'>
                                ".nl2br(htmlspecialchars($respuesta))."
                              </p>
                            </div>

                            <p style='color:#555;font-size:14px;margin-top:25px;'>
                              Estado actual del ticket: <strong>".ucfirst(str_replace('_',' ',$nuevo_estado))."</strong>
                            </p>

                            <p style='margin-top:30px;color:#777;font-size:13px;'>
                              Este correo fue enviado automáticamente desde Sansouci Desk.
                              Si no reconoces esta solicitud, contacta con el equipo de soporte.
                            </p>
                          </div>
                        </div>
                    ";

                    $mail->Body = $cuerpo_html;
                    $mail->AltBody =
                        "Se ha registrado una respuesta a tu ticket #$numero.\n\n" .
                        "Asunto: {$ticket['asunto']}\n\n" .
                        "Respuesta:\n{$respuesta}\n\n" .
                        "Estado actual: " . ucfirst(str_replace('_', ' ', $nuevo_estado));

                    $mail->send();
                } catch (\Exception $e) {
                    // Para depuración: mostramos el error en el banner
                    $mensaje_flash = "Respuesta guardada, pero el correo falló: " . $e->getMessage();
                }
            }

            // Recargar respuestas después de insertar
            $stmt = $pdo->prepare("
                SELECT *
                FROM respuestas
                WHERE ticket_id = ?
                ORDER BY creado_el ASC
            ");
            $stmt->execute([$ticket_id]);
            $respuestas = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($mensaje_flash === '') {
                $mensaje_flash = "Respuesta enviada y ticket actualizado correctamente.";
            }
            $ticket['estado'] = $nuevo_estado;

        } catch (Exception $e) {
            $mensaje_flash = "Ocurrió un error al guardar la respuesta.";
        }
    }
}
?>

<div class="flex flex-col gap-8">
    <!-- Encabezado del ticket -->
    <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
        <div>
            <h1 class="text-3xl font-bold text-blue-900">
                Ticket #<?= htmlspecialchars($numero) ?>
            </h1>
            <p class="text-lg text-gray-600">
                <?= htmlspecialchars($ticket['asunto']) ?>
            </p>
            <p class="text-sm text-gray-500 mt-1">
                Cliente: <?= htmlspecialchars($ticket['cliente_email']) ?>
            </p>
        </div>

        <div class="flex flex-col items-end gap-2">
            <span class="inline-block px-4 py-2 rounded-full text-sm font-bold
                <?= $ticket['estado'] === 'abierto' ? 'bg-green-100 text-green-800' :
                   ($ticket['estado'] === 'cerrado' ? 'bg-gray-400 text-white' :
                                                     'bg-yellow-100 text-yellow-800') ?>">
                Estado: <?= ucfirst(str_replace('_',' ',$ticket['estado'])) ?>
            </span>
            <span class="inline-block px-4 py-2 rounded-full text-sm font-bold
                <?= $ticket['prioridad'] === 'urgente' ? 'bg-red-100 text-red-800' :
                   ($ticket['prioridad'] === 'alta' ? 'bg-orange-100 text-orange-800' :
                                                     'bg-blue-100 text-blue-800') ?>">
                Prioridad: <?= ucfirst($ticket['prioridad']) ?>
            </span>
        </div>
    </div>

    <?php if ($mensaje_flash): ?>
        <div class="bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 p-4 rounded-lg text-lg mb-4">
            <?= htmlspecialchars($mensaje_flash) ?>
        </div>
    <?php endif; ?>

    <!-- Historial de respuestas -->
    <div class="bg-white rounded-2xl shadow-lg p-6 mb-6">
        <h2 class="text-2xl font-bold text-blue-900 mb-4">Historial de respuestas</h2>

        <?php if (empty($respuestas)): ?>
            <p class="text-gray-500 text-lg">Todavía no hay respuestas en este ticket.</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($respuestas as $r): ?>
                    <div class="border border-gray-200 rounded-xl p-4 bg-gray-50">
                        <div class="flex justify-between items-center mb-2">
                            <div class="font-semibold text-blue-900">
                                <?= htmlspecialchars($r['autor'] ?? 'Agente') ?>
                                <?php if (!empty($r['autor_email'])): ?>
                                    <span class="text-sm text-gray-500">
                                        (<?= htmlspecialchars($r['autor_email']) ?>)
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="text-sm text-gray-500">
                                <?= htmlspecialchars($r['creado_el']) ?>
                            </div>
                        </div>
                        <div class="text-gray-800 whitespace-pre-line">
                            <?= nl2br(htmlspecialchars($r['mensaje'])) ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Formulario de respuesta -->
    <div class="bg-white rounded-2xl shadow-2xl p-6">
        <h2 class="text-2xl font-bold text-blue-900 mb-4">Responder al cliente</h2>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-lg font-semibold mb-2">Respuesta</label>
                <textarea name="respuesta" rows="6"
                          class="w-full border-2 rounded-xl p-3 text-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="Escribe la respuesta al cliente..."></textarea>
            </div>

            <div>
                <label class="block text-lg font-semibold mb-2">Estado del ticket</label>
                <select name="estado"
                        class="border-2 rounded-xl p-3 text-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="abierto"     <?= $ticket['estado'] === 'abierto' ? 'selected' : '' ?>>Abierto</option>
                    <option value="en_proceso"  <?= $ticket['estado'] === 'en_proceso' ? 'selected' : '' ?>>En Proceso</option>
                    <option value="cerrado"     <?= $ticket['estado'] === 'cerrado' ? 'selected' : '' ?>>Cerrado</option>
                </select>
            </div>

            <div class="pt-2">
                <button type="submit"
                        class="bg-green-600 hover:bg-green-700 text-white font-bold px-8 py-3 rounded-xl text-lg shadow-lg">
                    Enviar respuesta
                </button>
                <a href="tickets.php"
                   class="ml-4 inline-block text-blue-700 font-semibold">
                    ← Volver a la lista de tickets
                </a>
            </div>
        </form>
    </div>
</div>

<?php
ob_end_flush();
require 'footer.php';
?>
