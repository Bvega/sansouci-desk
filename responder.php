<?php
ob_start();

require __DIR__ . '/config.php';
require __DIR__ . '/app/bootstrap.php';

use App\Models\TicketModel;

require 'header.php'; // abre el layout y carga $user

// --- Verificar sesión de usuario de forma amigable ---
if (!isset($user) || !is_array($user) || empty($user['email'])) {
    header('Location: login.php');
    exit();
}

// --- Validar ticket_id ---
if (!isset($_GET['ticket_id']) || !ctype_digit($_GET['ticket_id'])) {
    echo '<div class="p-12 text-center text-red-600 text-2xl font-bold bg-white rounded-3xl shadow-2xl">
            Ticket no válido
          </div>';
    require 'footer.php';
    ob_end_flush();
    exit();
}

$ticketId = (int) $_GET['ticket_id'];

// --- Cargar ticket desde el modelo ---
$ticket = TicketModel::findByIdWithAgent($ticketId);

if (!$ticket) {
    echo '<div class="p-12 text-center text-red-600 text-2xl font-bold bg-white rounded-3xl shadow-2xl">
            Ticket no encontrado
          </div>';
    require 'footer.php';
    ob_end_flush();
    exit();
}

$numero = $ticket['numero'] ?? ('TCK-' . str_pad($ticket['id'], 5, '0', STR_PAD_LEFT));

// --- Cargar respuestas existentes ---
$respuestas = TicketModel::getResponsesForTicket($ticketId);

// --- Config de correo (defaults + DB) ---
$config_email = [
    'smtp_host'            => '',
    'smtp_port'            => 587,
    'smtp_usuario'         => '',
    'smtp_clave'           => '',
    'smtp_encriptacion'    => 'tls',
    'smtp_from_email'      => 'soporte@sansouci.com.do',
    'smtp_from_name'       => 'Sansouci Desk',
    'correos_notificacion' => '',
    'activado'             => 0,
];

try {
    $stmt = $pdo->query("SELECT * FROM config_email WHERE id = 1");
    $row  = $stmt->fetch(PDO::FETCH_ASSOC);
    if (is_array($row)) {
        $config_email = array_merge($config_email, $row);
    }
} catch (\Throwable $e) {
    // No romper la página si la tabla/config no existe aún.
}

// --- Procesar envío de respuesta ---
$mensajeFlash = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['respuesta'])) {
    $respuesta = trim($_POST['respuesta'] ?? '');

    if ($respuesta === '') {
        $mensajeFlash = 'La respuesta no puede estar vacía';
    } else {
        try {
            // Guardar respuesta en la tabla
            $stmt = $pdo->prepare("
                INSERT INTO respuestas (ticket_id, mensaje, autor, autor_email, creado_el)
                VALUES (?, ?, 'agente', ?, NOW())
            ");
            $stmt->execute([$ticketId, $respuesta, $user['email']]);

            // Actualizar fecha de actualización del ticket
            $stmt = $pdo->prepare("UPDATE tickets SET actualizado_el = NOW() WHERE id = ?");
            $stmt->execute([$ticketId]);

            // Enviar correo solo si hay configuración válida y está activado
            if (!empty($config_email['smtp_usuario']) &&
                !empty($config_email['smtp_clave']) &&
                !empty($config_email['activado'])) {

                require __DIR__ . '/phpmailer/src/Exception.php';
                require __DIR__ . '/phpmailer/src/PHPMailer.php';
                require __DIR__ . '/phpmailer/src/SMTP.php';

                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

                try {
                    $mail->isSMTP();
                    $mail->Host       = $config_email['smtp_host'];
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $config_email['smtp_usuario'];
                    $mail->Password   = $config_email['smtp_clave'];

                    if (!empty($config_email['smtp_encriptacion']) && $config_email['smtp_encriptacion'] !== 'none') {
                        $mail->SMTPSecure = $config_email['smtp_encriptacion'];
                    }

                    $mail->Port    = (int) $config_email['smtp_port'];
                    $mail->CharSet = 'UTF-8';

                    $fromEmail = $config_email['smtp_from_email'] ?: $config_email['smtp_usuario'];
                    $fromName  = $config_email['smtp_from_name'] ?: 'Sansouci Desk';

                    $mail->setFrom($fromEmail, $fromName);
                    $mail->addReplyTo($fromEmail, 'Sansouci Desk');
                    $mail->addAddress($ticket['cliente_email']);

                    // BCC adicionales
                    $destinos = array_filter(array_map('trim', explode(',', $config_email['correos_notificacion'] ?? '')));
                    foreach ($destinos as $to) {
                        if ($to && $to !== $ticket['cliente_email']) {
                            $mail->addBCC($to);
                        }
                    }

                    $mail->isHTML(true);
                    $mail->Subject = "Re: Ticket #{$numero} - {$ticket['asunto']}";

                    $bodyHtml = "
                        <div style='font-family:Segoe UI,Arial,sans-serif;font-size:14px;color:#111'>
                            <h2 style='color:#003087'>Respuesta a tu ticket {$numero}</h2>
                            <p><strong>Asunto:</strong> " . htmlspecialchars($ticket['asunto']) . "</p>
                            <p><strong>Mensaje original:</strong><br>" . nl2br(htmlspecialchars($ticket['mensaje'])) . "</p>
                            <hr style='margin:16px 0'>
                            <p><strong>Respuesta del agente:</strong><br>" . nl2br(htmlspecialchars($respuesta)) . "</p>
                            <p style='margin-top:24px'>Puedes responder a este correo si necesitas más ayuda.</p>
                        </div>
                    ";

                    $bodyText = "Respuesta a tu ticket {$numero}\n\n"
                              . "Asunto: {$ticket['asunto']}\n\n"
                              . "Mensaje original:\n{$ticket['mensaje']}\n\n"
                              . "Respuesta del agente:\n{$respuesta}\n";

                    $mail->Body    = $bodyHtml;
                    $mail->AltBody = $bodyText;

                    $mail->send();
                } catch (\Throwable $e) {
                    // Si falla el correo, no rompemos la app.
                }
            }

            // Reload de respuestas para incluir la nueva
            $respuestas   = TicketModel::getResponsesForTicket($ticketId);
            $mensajeFlash = 'Respuesta enviada correctamente';
        } catch (\Throwable $e) {
            $mensajeFlash = 'Ocurrió un problema al guardar la respuesta.';
        }
    }
}
?>

<h1 class="text-4xl font-bold text-blue-900 mb-6">
    Ticket #<?= htmlspecialchars($numero) ?>
</h1>
<p class="text-lg text-gray-600 mb-10">
    Cliente: <strong><?= htmlspecialchars($ticket['cliente_email']) ?></strong>
    &nbsp;·&nbsp;
    Estado: <strong><?= htmlspecialchars(ucfirst(str_replace('_',' ',$ticket['estado']))) ?></strong>
    &nbsp;·&nbsp;
    Prioridad: <strong><?= htmlspecialchars(ucfirst($ticket['prioridad'])) ?></strong>
</p>

<?php if ($mensajeFlash): ?>
    <div class="mb-8 px-6 py-4 rounded-xl text-lg font-semibold
                <?= str_starts_with($mensajeFlash, 'Respuesta enviada') ? 'bg-green-100 text-green-800 border border-green-400' : 'bg-yellow-100 text-yellow-800 border border-yellow-400' ?>">
        <?= htmlspecialchars($mensajeFlash) ?>
    </div>
<?php endif; ?>

<!-- Detalle del ticket -->
<div class="bg-white rounded-2xl shadow-lg mb-10 p-6">
    <h2 class="text-2xl font-bold text-gray-900 mb-4">Detalle del ticket</h2>
    <p class="mb-2"><strong>Asunto:</strong> <?= htmlspecialchars($ticket['asunto']) ?></p>
    <p class="mb-4"><strong>Mensaje del cliente:</strong><br><?= nl2br(htmlspecialchars($ticket['mensaje'])) ?></p>
    <p class="text-sm text-gray-500">
        Creado el <?= date('d/m/Y H:i', strtotime($ticket['creado_el'])) ?>
        <?php if (!empty($ticket['agente_nombre'])): ?>
            &nbsp;·&nbsp; Asignado a <?= htmlspecialchars($ticket['agente_nombre']) ?>
        <?php endif; ?>
    </p>
</div>

<!-- Formulario de respuesta -->
<div class="bg-white rounded-2xl shadow-lg mb-10 p-6">
    <h2 class="text-2xl font-bold text-gray-900 mb-4">Responder al cliente</h2>
    <form method="POST" class="space-y-4">
        <div>
            <label class="block mb-2 font-semibold text-gray-700">Respuesta</label>
            <textarea name="respuesta" rows="5"
                      class="w-full border border-gray-300 rounded-xl p-3 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="Escribe aquí tu respuesta para el cliente..."></textarea>
        </div>

        <!-- Respuestas rápidas -->
        <div class="mt-6">
            <h3 class="font-semibold text-gray-800 mb-2">Respuestas rápidas</h3>
            <div class="flex flex-wrap gap-2">
                <?php
                try {
                    $stmtPlant = $pdo->query("SELECT * FROM plantillas_respuesta ORDER BY titulo");
                    while ($p = $stmtPlant->fetch(PDO::FETCH_ASSOC)): ?>
                        <button type="button"
                                onclick="aplicarPlantilla(`<?= htmlspecialchars(addslashes($p['mensaje']), ENT_QUOTES) ?>`)"
                                class="px-3 py-2 text-sm rounded-full border border-blue-300 text-blue-800 bg-blue-50 hover:bg-blue-100">
                            <?= htmlspecialchars($p['titulo']) ?>
                        </button>
                <?php
                    endwhile;
                } catch (\Throwable $e) {
                    // si no hay tabla o falla, simplemente no mostramos nada
                }
                ?>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-4 mt-6">
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-xl shadow">
                Enviar respuesta
            </button>
            <a href="tickets.php"
               class="inline-block px-6 py-3 rounded-xl border border-gray-300 text-gray-700 hover:bg-gray-100">
               Volver a tickets
            </a>
        </div>
    </form>
</div>

<!-- Historial de respuestas -->
<div class="bg-white rounded-2xl shadow-lg p-6">
    <h2 class="text-2xl font-bold text-gray-900 mb-4">Historial de respuestas</h2>

    <?php if (empty($respuestas)): ?>
        <p class="text-gray-500 text-base">Aún no hay respuestas registradas para este ticket.</p>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($respuestas as $r): ?>
                <div class="border border-gray-200 rounded-xl p-4">
                    <div class="flex items-center justify-between mb-2">
                        <div>
                            <span class="font-bold">
                                <?= $r['autor'] === 'agente' ? 'Agente' : 'Cliente' ?>
                            </span>
                            <?php if ($r['autor'] === 'agente' && !empty($r['autor_email'])): ?>
                                <span class="text-sm text-gray-500">
                                    (<?= htmlspecialchars($r['autor_email']) ?>)
                                </span>
                            <?php endif; ?>
                        </div>
                        <span class="text-sm text-gray-500">
                            <?= date('d/m/Y H:i', strtotime($r['creado_el'])) ?>
                        </span>
                    </div>
                    <p class="text-gray-800 text-base leading-relaxed">
                        <?= nl2br(htmlspecialchars($r['mensaje'])) ?>
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<script>
function aplicarPlantilla(mensaje) {
    const textarea = document.querySelector('textarea[name="respuesta"]');
    if (!textarea) return;
    textarea.value = mensaje.replace(/\\n/g, '\n');
    textarea.focus();
}
</script>

<?php
require 'footer.php';
ob_end_flush();
