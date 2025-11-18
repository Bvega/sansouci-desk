<?php
ob_start();

require __DIR__ . '/config.php';
require 'header.php';

// --- Verificar usuario autenticado (por si acaso) ---
if (!isset($user) || !is_array($user) || empty($user['email'])) {
    header('Location: login.php');
    exit();
}

// --- Cargar Tipos de Servicio ---
$tipos_servicio = [];
try {
    $stmtTs = $pdo->query("SELECT nombre FROM tipos_servicio ORDER BY nombre");
    $tipos_servicio = $stmtTs->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
    $tipos_servicio = [];
}

// --- Cargar Agentes (para asignar) ---
$agentes = [];
try {
    $stmtAg = $pdo->query("
        SELECT id, nombre, rol
        FROM users
        WHERE rol IN ('agente','administrador','superadmin')
        ORDER BY nombre
    ");
    $agentes = $stmtAg->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $agentes = [];
}

// --- Manejo de formulario ---
$errors = [];
$successMessage = '';

$cliente_email  = $_POST['cliente_email']  ?? '';
$asunto         = $_POST['asunto']         ?? '';
$mensaje        = $_POST['mensaje']        ?? '';
$prioridad      = $_POST['prioridad']      ?? 'normal';
$tipo_servicio  = $_POST['tipo_servicio']  ?? '';
$agente_id      = $_POST['agente_id']      ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validaciones simples
    if (trim($cliente_email) === '') {
        $errors[] = 'El correo del cliente es obligatorio.';
    } elseif (!filter_var($cliente_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El correo del cliente no tiene un formato válido.';
    }

    if (trim($asunto) === '') {
        $errors[] = 'El asunto es obligatorio.';
    }

    if (trim($mensaje) === '') {
        $errors[] = 'El mensaje es obligatorio.';
    }

    if ($prioridad === '') {
        $prioridad = 'normal';
    }

    if ($tipo_servicio === '' && !empty($tipos_servicio)) {
        $tipo_servicio = $tipos_servicio[0]; // primer tipo por defecto
    }

    if (empty($errors)) {
        try {
            // Insertar ticket
            $sql = "
                INSERT INTO tickets 
                    (cliente_email, asunto, mensaje, estado, prioridad, tipo_servicio, agente_id, creado_el, actualizado_el)
                VALUES
                    (?, ?, ?, 'abierto', ?, ?, ?, NOW(), NOW())
            ";

            $stmt = $pdo->prepare($sql);
            $agente_id_param = $agente_id !== '' ? (int) $agente_id : null;

            $stmt->execute([
                $cliente_email,
                $asunto,
                $mensaje,
                $prioridad,
                $tipo_servicio,
                $agente_id_param
            ]);

            $ticketId = (int) $pdo->lastInsertId();

            // Limpiar campos del formulario tras éxito
            $cliente_email = $asunto = $mensaje = '';
            $prioridad = 'normal';
            $tipo_servicio = $tipo_servicio ?? '';
            $agente_id = '';

            $successMessage = "Ticket creado correctamente (ID: {$ticketId}).";

        } catch (Throwable $e) {
            $errors[] = 'Ocurrió un problema al crear el ticket.';
        }
    }
}
?>

<h1 class="text-4xl font-bold text-blue-900 mb-6">Crear nuevo ticket</h1>
<p class="text-gray-600 mb-8">
    Usa este formulario para registrar manualmente un ticket desde el panel interno.
</p>

<?php if (!empty($errors)): ?>
    <div class="mb-6 px-6 py-4 border border-red-400 bg-red-100 text-red-800 rounded-xl text-lg">
        <ul class="list-disc list-inside">
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($successMessage): ?>
    <div class="mb-6 px-6 py-4 border border-green-400 bg-green-100 text-green-800 rounded-xl text-lg">
        <?= htmlspecialchars($successMessage) ?>
        <div class="mt-2">
            <a href="tickets.php" class="text-blue-800 underline font-semibold">
                Ir a la lista de tickets
            </a>
        </div>
    </div>
<?php endif; ?>

<div class="bg-white rounded-2xl shadow-2xl p-8 max-w-3xl">
    <form method="POST" class="space-y-6">

        <div>
            <label class="block text-lg font-semibold mb-1">Correo del cliente</label>
            <input type="email"
                   name="cliente_email"
                   value="<?= htmlspecialchars($cliente_email) ?>"
                   class="w-full border border-gray-300 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="cliente@empresa.com">
        </div>

        <div>
            <label class="block text-lg font-semibold mb-1">Asunto</label>
            <input type="text"
                   name="asunto"
                   value="<?= htmlspecialchars($asunto) ?>"
                   class="w-full border border-gray-300 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                   placeholder="Ej: Solicitud de acceso, problema con servicio, etc.">
        </div>

        <div>
            <label class="block text-lg font-semibold mb-1">Mensaje / Descripción</label>
            <textarea name="mensaje"
                      rows="5"
                      class="w-full border border-gray-300 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                      placeholder="Describe el problema o solicitud del cliente..."><?= htmlspecialchars($mensaje) ?></textarea>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-lg font-semibold mb-1">Prioridad</label>
                <select name="prioridad"
                        class="w-full border border-gray-300 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="normal"  <?= $prioridad==='normal'?'selected':'' ?>>Normal</option>
                    <option value="alta"    <?= $prioridad==='alta'?'selected':'' ?>>Alta</option>
                    <option value="urgente" <?= $prioridad==='urgente'?'selected':'' ?>>Urgente</option>
                </select>
            </div>

            <div>
                <label class="block text-lg font-semibold mb-1">Tipo de servicio</label>
                <select name="tipo_servicio"
                        class="w-full border border-gray-300 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <?php if (empty($tipos_servicio)): ?>
                        <option value="">General</option>
                    <?php else: ?>
                        <?php foreach ($tipos_servicio as $ts): ?>
                            <option value="<?= htmlspecialchars($ts) ?>"
                                <?= $tipo_servicio===$ts?'selected':'' ?>>
                                <?= htmlspecialchars($ts) ?>
                            </option>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </select>
            </div>
        </div>

        <div>
            <label class="block text-lg font-semibold mb-1">Asignar a agente (opcional)</label>
            <select name="agente_id"
                    class="w-full border border-gray-300 rounded-xl px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">No asignar</option>
                <?php foreach ($agentes as $ag): ?>
                    <option value="<?= (int) $ag['id'] ?>" <?= (string)$agente_id===(string)$ag['id']?'selected':'' ?>>
                        <?= htmlspecialchars($ag['nombre']) ?> (<?= htmlspecialchars($ag['rol']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex flex-wrap items-center gap-4 pt-4">
            <button type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-6 py-3 rounded-xl shadow">
                Crear ticket
            </button>

            <a href="tickets.php"
               class="inline-block px-6 py-3 rounded-xl border border-gray-300 text-gray-700 hover:bg-gray-100">
                Cancelar
            </a>
        </div>
    </form>
</div>

<?php
require 'footer.php';
ob_end_flush();
