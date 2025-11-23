<?php
ob_start();

require __DIR__ . '/config.php';
require 'header.php';

// Solo admin / superadmin
if (!$user || !in_array($user['rol'], ['administrador', 'superadmin'])) {
    header('Location: dashboard.php');
    exit();
}

// --- Cargar tipos de servicio ---
$tipos_servicio = [];
try {
    $stmtTs = $pdo->query("SELECT nombre FROM tipos_servicio ORDER BY nombre");
    $tipos_servicio = $stmtTs->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
    $tipos_servicio = [];
}

// --- Cargar agentes (agente / admin / superadmin) ---
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

// --- Manejar acciones (crear / eliminar regla) ---
$mensaje = '';
$tipo_msj = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'crear') {
        $tipo_servicio_sel = $_POST['tipo_servicio'] ?? '';
        $agente_id_sel     = $_POST['agente_id']     ?? '';

        if ($tipo_servicio_sel === '' || $agente_id_sel === '') {
            $mensaje = 'Debes seleccionar un tipo de servicio y un agente.';
            $tipo_msj = 'warning';
        } else {
            try {
                $stmtIns = $pdo->prepare("
                    INSERT INTO asignacion_tickets (tipo_servicio, agente_id)
                    VALUES (?, ?)
                    ON DUPLICATE KEY UPDATE agente_id = VALUES(agente_id), creado_el = NOW()
                ");
                $stmtIns->execute([
                    $tipo_servicio_sel,
                    (int)$agente_id_sel
                ]);

                $mensaje = 'Regla de asignación guardada correctamente.';
                $tipo_msj = 'success';
            } catch (Throwable $e) {
                $mensaje = 'Ocurrió un problema al guardar la regla de asignación.';
                $tipo_msj = 'error';
            }
        }
    }

    if ($accion === 'eliminar' && isset($_POST['regla_id'])) {
        $regla_id = (int) $_POST['regla_id'];

        try {
            $stmtDel = $pdo->prepare("DELETE FROM asignacion_tickets WHERE id = ?");
            $stmtDel->execute([$regla_id]);

            $mensaje = 'Regla eliminada correctamente.';
            $tipo_msj = 'success';
        } catch (Throwable $e) {
            $mensaje = 'Ocurrió un problema al eliminar la regla.';
            $tipo_msj = 'error';
        }
    }
}

// --- Cargar reglas actuales ---
$reglas = [];
try {
    $stmtReg = $pdo->query("
        SELECT a.id, a.tipo_servicio, a.agente_id, a.creado_el,
               u.nombre AS agente_nombre, u.rol AS agente_rol
        FROM asignacion_tickets a
        LEFT JOIN users u ON a.agente_id = u.id
        ORDER BY a.tipo_servicio
    ");
    $reglas = $stmtReg->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $reglas = [];
}

// Helper para estilos de mensajes
function class_mensaje(string $tipo): string {
    switch ($tipo) {
        case 'success':
            return 'bg-green-100 border-green-400 text-green-800';
        case 'error':
            return 'bg-red-100 border-red-400 text-red-800';
        case 'warning':
            return 'bg-yellow-100 border-yellow-400 text-yellow-800';
        default:
            return 'bg-blue-100 border-blue-400 text-blue-800';
    }
}
?>

<h1 class="text-4xl font-bold text-blue-900 mb-6">Asignación automática de tickets</h1>
<p class="text-gray-600 mb-8 max-w-3xl">
    Aquí puedes definir qué agente recibirá automáticamente los tickets según el
    <strong>tipo de servicio</strong>. Cuando se cree un ticket con un tipo de servicio
    que tenga una regla configurada, se asignará de forma automática al agente indicado.
</p>

<?php if ($mensaje): ?>
    <div class="mb-6 px-6 py-4 border rounded-xl text-lg <?= class_mensaje($tipo_msj) ?>">
        <?= htmlspecialchars($mensaje) ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Formulario de nueva regla -->
    <div>
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <h2 class="text-2xl font-bold text-blue-900 mb-4">Crear / actualizar regla</h2>
            <p class="text-sm text-gray-500 mb-4">
                Selecciona un <strong>tipo de servicio</strong> y el <strong>agente</strong> que
                recibirá sus tickets. Si el tipo de servicio ya tiene una regla, se actualizará.
            </p>

            <form method="POST" class="space-y-5">
                <input type="hidden" name="accion" value="crear">

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">
                        Tipo de servicio
                    </label>
                    <select
                        name="tipo_servicio"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">Selecciona un tipo...</option>
                        <?php foreach ($tipos_servicio as $ts): ?>
                            <option value="<?= htmlspecialchars($ts) ?>">
                                <?= htmlspecialchars($ts) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">
                        Agente
                    </label>
                    <select
                        name="agente_id"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                    >
                        <option value="">Selecciona un agente...</option>
                        <?php foreach ($agentes as $ag): ?>
                            <option value="<?= (int)$ag['id'] ?>">
                                <?= htmlspecialchars($ag['nombre']) ?> (<?= htmlspecialchars($ag['rol']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="pt-4">
                    <button
                        type="submit"
                        class="inline-flex items-center px-6 py-3 rounded-xl bg-blue-700 hover:bg-blue-800 text-white font-semibold text-sm shadow-lg"
                    >
                        Guardar regla
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Listado de reglas -->
    <div>
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <h2 class="text-2xl font-bold text-blue-900 mb-4">Reglas configuradas</h2>

            <?php if (empty($reglas)): ?>
                <p class="text-gray-500 text-sm">
                    Todavía no hay reglas de asignación. Crea la primera usando el formulario de la izquierda.
                </p>
            <?php else: ?>
                <div class="space-y-3 max-h-[420px] overflow-y-auto pr-2">
                    <?php foreach ($reglas as $r): ?>
                        <div class="border border-blue-100 rounded-xl px-4 py-3 bg-blue-50 flex items-center justify-between gap-4">
                            <div>
                                <div class="font-bold text-blue-900">
                                    <?= htmlspecialchars($r['tipo_servicio']) ?>
                                </div>
                                <div class="text-sm text-gray-600">
                                    Agente:
                                    <?php if (!empty($r['agente_nombre'])): ?>
                                        <?= htmlspecialchars($r['agente_nombre']) ?>
                                        (<?= htmlspecialchars($r['agente_rol']) ?>)
                                    <?php else: ?>
                                        <span class="text-red-600 font-semibold">[Agente no encontrado]</span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-xs text-gray-500">
                                    Configurada el <?= htmlspecialchars($r['creado_el']) ?>
                                </div>
                            </div>
                            <form method="POST" onsubmit="return confirm('¿Eliminar esta regla?');">
                                <input type="hidden" name="accion" value="eliminar">
                                <input type="hidden" name="regla_id" value="<?= (int)$r['id'] ?>">
                                <button
                                    type="submit"
                                    class="px-3 py-2 rounded-full bg-red-600 hover:bg-red-700 text-white text-xs font-semibold shadow"
                                >
                                    Eliminar
                                </button>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require 'footer.php';
ob_end_flush();
