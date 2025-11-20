<?php
ob_start();
require __DIR__ . '/config.php';
require __DIR__ . '/config_email.php';

// --- Manejo de formulario ---
$errors = [];
$successMessage = '';

$cliente_email = $_POST['cliente_email'] ?? '';
$asunto        = $_POST['asunto'] ?? '';
$mensaje       = $_POST['mensaje'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validaciones básicas
    if (trim($cliente_email) === '') {
        $errors[] = 'El correo electrónico es obligatorio.';
    } elseif (!filter_var($cliente_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El correo electrónico no tiene un formato válido.';
    }

    if (trim($asunto) === '') {
        $errors[] = 'El asunto es obligatorio.';
    }

    if (trim($mensaje) === '') {
        $errors[] = 'La descripción de la solicitud es obligatoria.';
    }

    if (empty($errors)) {
        try {
            // Insertar ticket como "abierto" y prioridad "normal"
            $sql = "
                INSERT INTO tickets
                    (cliente_email, asunto, mensaje, estado, prioridad, tipo_servicio, creado_el, actualizado_el)
                VALUES
                    (?, ?, ?, 'abierto', 'normal', 'Consulta General', NOW(), NOW())
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $cliente_email,
                $asunto,
                $mensaje
            ]);

            $ticketId  = (int) $pdo->lastInsertId();
            $ticketRef = 'TCK-' . str_pad($ticketId, 5, '0', STR_PAD_LEFT);

            // Limpiar campos
            $cliente_email = $asunto = $mensaje = '';

            $successMessage = "Tu solicitud ha sido enviada correctamente. Número de referencia: {$ticketRef}.";

            // ==============================
            //  NOTIFICACIONES POR CORREO
            // ==============================

            // 1) Correo al cliente
            $subjectCliente = "Hemos recibido tu solicitud ({$ticketRef})";

            $bodyCliente = "
                <p>Hola,</p>
                <p>Hemos recibido tu solicitud y está siendo procesada por nuestro equipo de soporte.</p>
                <p><strong>Número de referencia:</strong> {$ticketRef}</p>
                <p><strong>Asunto:</strong> " . htmlspecialchars($asunto, ENT_QUOTES, 'UTF-8') . "</p>
                <p>En breve uno de nuestros agentes se pondrá en contacto contigo.</p>
                <p>Atentamente,<br>Sansouci Desk</p>
            ";

            // Aunque el ticket ya está creado, estas variables se limpiaron;
            // usamos las originales antes de limpiar, así que movemos esto
            // ARRIBA SI QUEREMOS MOSTRARLOS TAL CUAL.
            // Para este ejemplo, asumimos que asunto/mensaje anteriores eran válidos.

            // RECONSTRUIMOS asunto/mensaje de forma segura para el correo:
            $asuntoCorreo  = $_POST['asunto']  ?? '';
            $mensajeCorreo = $_POST['mensaje'] ?? '';

            $bodyCliente = "
                <p>Hola,</p>
                <p>Hemos recibido tu solicitud y está siendo procesada por nuestro equipo de soporte.</p>
                <p><strong>Número de referencia:</strong> {$ticketRef}</p>
                <p><strong>Asunto:</strong> " . htmlspecialchars($asuntoCorreo, ENT_QUOTES, 'UTF-8') . "</p>
                <p><strong>Descripción enviada:</strong><br>" . nl2br(htmlspecialchars($mensajeCorreo, ENT_QUOTES, 'UTF-8')) . "</p>
                <p>En breve uno de nuestros agentes se pondrá en contacto contigo.</p>
                <p>Atentamente,<br>Sansouci Desk</p>
            ";

            // No detenemos el flujo si falla el correo; solo intentamos.
            @sendSupportMail(
                trim($_POST['cliente_email'] ?? ''),
                $subjectCliente,
                $bodyCliente
            );

            // 2) Correo interno a la cuenta configurada
            $cfg = loadEmailConfig($pdo);
            $internalTo = $cfg['from_email'] ?: ($cfg['smtp_user'] ?? '');

            if ($internalTo) {
                $subjectInterno = "Nuevo ticket creado ({$ticketRef})";

                $bodyInterno = "
                    <p>Se ha creado un nuevo ticket desde el portal de clientes.</p>
                    <p><strong>Número de referencia:</strong> {$ticketRef}</p>
                    <p><strong>Correo cliente:</strong> " . htmlspecialchars($_POST['cliente_email'] ?? '', ENT_QUOTES, 'UTF-8') . "</p>
                    <p><strong>Asunto:</strong> " . htmlspecialchars($asuntoCorreo, ENT_QUOTES, 'UTF-8') . "</p>
                    <p><strong>Mensaje:</strong><br>" . nl2br(htmlspecialchars($mensajeCorreo, ENT_QUOTES, 'UTF-8')) . "</p>
                    <p>Revisa el panel de tickets para gestionarlo.</p>
                ";

                @sendSupportMail(
                    $internalTo,
                    $subjectInterno,
                    $bodyInterno
                );
            }

        } catch (Throwable $e) {
            $errors[] = 'Ocurrió un problema al enviar tu solicitud. Inténtalo nuevamente más tarde.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Portal de Clientes - Sansouci Desk</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-slate-900 flex items-center justify-center px-4">
    <div class="w-full max-w-xl">
        <div class="bg-white rounded-3xl shadow-2xl p-8 sm:p-10">
            <div class="mb-6 text-center">
                <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 mb-1">
                    Portal de Clientes
                </h1>
                <p class="text-sm text-slate-500">
                    Envía tu solicitud y nuestro equipo la atenderá.
                </p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="mb-4 px-4 py-3 rounded-xl bg-red-100 text-red-800 text-sm">
                    <ul class="list-disc list-inside">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($successMessage): ?>
                <div class="mb-4 px-4 py-3 rounded-xl bg-green-100 text-green-800 text-sm">
                    <?= htmlspecialchars($successMessage) ?>
                </div>
            <?php endif; ?>

            <form method="POST" class="space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">
                        Tu correo
                    </label>
                    <input
                        type="email"
                        name="cliente_email"
                        value="<?= htmlspecialchars($cliente_email) ?>"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="tu.correo@ejemplo.com"
                        required
                    >
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">
                        Asunto
                    </label>
                    <input
                        type="text"
                        name="asunto"
                        value="<?= htmlspecialchars($asunto) ?>"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Ej: Consulta, solicitud de acceso, incidencia..."
                        required
                    >
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">
                        Describe tu solicitud
                    </label>
                    <textarea
                        name="mensaje"
                        rows="5"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="Incluye todos los detalles relevantes para que podamos ayudarte mejor."
                        required
                    ><?= htmlspecialchars($mensaje) ?></textarea>
                </div>

                <button
                    type="submit"
                    class="w-full mt-2 inline-flex items-center justify-center rounded-xl bg-blue-700 hover:bg-blue-800 text-white font-semibold py-2.5 text-sm shadow-lg"
                >
                    Enviar Solicitud
                </button>
            </form>

            <p class="mt-4 text-[11px] text-center text-slate-400">
                © <?= date('Y') ?> Sansouci Puerto de Santo Domingo
            </p>
        </div>
    </div>
</body>
</html>
<?php
ob_end_flush();

