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

            // Guardamos copia para el correo ANTES de limpiar
            $asuntoCorreo  = $_POST['asunto']  ?? '';
            $mensajeCorreo = $_POST['mensaje'] ?? '';
            $emailCorreo   = $_POST['cliente_email'] ?? '';

            // Limpiar campos para el formulario
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
                <p><strong>Asunto:</strong> " . htmlspecialchars($asuntoCorreo, ENT_QUOTES, 'UTF-8') . "</p>
                <p><strong>Descripción enviada:</strong><br>" . nl2br(htmlspecialchars($mensajeCorreo, ENT_QUOTES, 'UTF-8')) . "</p>
                <p>En breve uno de nuestros agentes se pondrá en contacto contigo.</p>
                <p>Atentamente,<br>Sansouci Desk</p>
            ";

            @sendSupportMail(
                trim($emailCorreo),
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
                    <p><strong>Correo cliente:</strong> " . htmlspecialchars($emailCorreo, ENT_QUOTES, 'UTF-8') . "</p>
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
<body class="min-h-screen bg-gradient-to-br from-blue-950 via-blue-900 to-blue-800 flex items-center justify-center px-4 py-8">
    <div class="w-full max-w-3xl">
        <div class="bg-white rounded-3xl shadow-2xl overflow-hidden border border-blue-900/40">
            <!-- Header visual, igual lenguaje que portal_cliente_tickets -->
            <div class="bg-gradient-to-b from-blue-900 to-blue-800 px-8 py-8 text-center">
                <img
                    src="https://www.sansouci.com.do/wp-content/uploads/2020/06/logo-sansouci.png"
                    alt="Sansouci"
                    class="h-20 mx-auto mb-4"
                >
                <h1 class="text-3xl font-extrabold text-white tracking-wide">
                    PORTAL DE CLIENTES
                </h1>
                <p class="text-blue-200 text-sm mt-1">
                    Crear nuevo ticket de soporte
                </p>
            </div>

            <!-- Contenido -->
            <div class="px-6 sm:px-10 py-8 bg-slate-50">
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
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
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
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
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
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white"
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

                <p class="mt-6 text-[11px] text-center text-slate-400">
                    © <?= date('Y') ?> Sansouci Puerto de Santo Domingo
                </p>
            </div>
        </div>
    </div>
</body>
</html>
<?php
ob_end_flush();
