<?php
ob_start();
require 'header.php'; // trae $pdo y $user desde nuestro header moderno

// Solo admin / superadmin pueden ver esta pantalla
if (!$user || !in_array($user['rol'], ['administrador', 'superadmin'])) {
    header('Location: dashboard.php');
    exit();
}

// ---------------------------
// 1. Cargar configuración actual
// ---------------------------

$defaults = [
    'id'                    => 1,
    'activo'                => 0,
    'modo'                  => 'balanceado', // balanceado|secuencial
    'max_por_agente'        => 20,
    'max_abiertos_cliente'  => 10,
    'notificar_correo'      => 0,
    'correo_alerta'         => '',
];

$config = $defaults;

try {
    $stmt = $pdo->query("SELECT * FROM config_asignacion WHERE id = 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $config = array_merge($config, $row);
    }
} catch (Exception $e) {
    // si la tabla o columnas no existen, ya lo ajustaremos luego
}

// ---------------------------
// 2. Guardar cambios (POST)
// ---------------------------

$mensaje = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activo               = isset($_POST['activo']) ? 1 : 0;
    $modo                 = ($_POST['modo'] ?? 'balanceado') === 'secuencial' ? 'secuencial' : 'balanceado';
    $max_por_agente       = max(1, (int)($_POST['max_por_agente'] ?? 20));
    $max_abiertos_cliente = max(1, (int)($_POST['max_abiertos_cliente'] ?? 10));
    $notificar_correo     = isset($_POST['notificar_correo']) ? 1 : 0;
    $correo_alerta        = trim($_POST['correo_alerta'] ?? '');

    try {
        // Usamos INSERT ... ON DUPLICATE KEY para mantener siempre el registro id=1
        $stmt = $pdo->prepare("
            INSERT INTO config_asignacion
                (id, activo, modo, max_por_agente, max_abiertos_cliente, notificar_correo, correo_alerta)
            VALUES
                (1, :activo, :modo, :max_por_agente, :max_abiertos_cliente, :notificar_correo, :correo_alerta)
            ON DUPLICATE KEY UPDATE
                activo               = VALUES(activo),
                modo                 = VALUES(modo),
                max_por_agente       = VALUES(max_por_agente),
                max_abiertos_cliente = VALUES(max_abiertos_cliente),
                notificar_correo     = VALUES(notificar_correo),
                correo_alerta        = VALUES(correo_alerta)
        ");

        $stmt->execute([
            ':activo'               => $activo,
            ':modo'                 => $modo,
            ':max_por_agente'       => $max_por_agente,
            ':max_abiertos_cliente' => $max_abiertos_cliente,
            ':notificar_correo'     => $notificar_correo,
            ':correo_alerta'        => $correo_alerta,
        ]);

        // refrescamos valores en memoria
        $config = array_merge($config, [
            'activo'               => $activo,
            'modo'                 => $modo,
            'max_por_agente'       => $max_por_agente,
            'max_abiertos_cliente' => $max_abiertos_cliente,
            'notificar_correo'     => $notificar_correo,
            'correo_alerta'        => $correo_alerta,
        ]);

        $mensaje = 'Configuración de asignación guardada correctamente.';
    } catch (Exception $e) {
        $error = 'Ocurrió un error al guardar la configuración. Revisaremos la estructura de la tabla.';
    }
}

?>
<div class="flex flex-col gap-8">
    <div>
        <h1 class="text-3xl font-bold text-blue-900 mb-2">
            Asignación automática de tickets
        </h1>
        <p class="text-gray-600 text-lg max-w-3xl">
            Define cómo se asignan los tickets creados desde el portal de clientes (y, si quieres,
            también los creados internamente). Esta configuración se utilizará por el motor de
            asignación automática cuando se cree un nuevo ticket.
        </p>
    </div>

    <?php if ($mensaje): ?>
        <div class="bg-green-100 border-l-4 border-green-600 text-green-800 p-4 rounded-lg text-lg">
            <?= htmlspecialchars($mensaje) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="bg-red-100 border-l-4 border-red-600 text-red-800 p-4 rounded-lg text-lg">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-4xl">
        <form method="POST" class="space-y-8">

            <!-- Activar / desactivar motor -->
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h2 class="text-2xl font-bold text-blue-900">Estado del motor</h2>
                    <p class="text-gray-600">
                        Si está desactivado, los tickets no se asignarán automáticamente.
                    </p>
                </div>
                <label class="inline-flex items-center cursor-pointer">
                    <input type="checkbox" name="activo" class="sr-only" <?= $config['activo'] ? 'checked' : '' ?>>
                    <span class="w-14 h-8 flex items-center bg-gray-300 rounded-full p-1 transition
                                 <?= $config['activo'] ? 'bg-green-500' : '' ?>">
                        <span class="bg-white w-6 h-6 rounded-full shadow-md transform transition
                                     <?= $config['activo'] ? 'translate-x-6' : '' ?>"></span>
                    </span>
                    <span class="ml-3 text-lg font-semibold text-gray-800">
                        <?= $config['activo'] ? 'Activado' : 'Desactivado' ?>
                    </span>
                </label>
            </div>

            <hr class="border-gray-200">

            <!-- Modo de asignación -->
            <div class="space-y-3">
                <h2 class="text-2xl font-bold text-blue-900">Modo de asignación</h2>
                <p class="text-gray-600">
                    Define la lógica de reparto de tickets entre los agentes.
                </p>

                <div class="grid md:grid-cols-2 gap-4">
                    <label class="border rounded-2xl p-4 flex gap-3 cursor-pointer
                                   <?= $config['modo']==='balanceado' ? 'border-blue-500 bg-blue-50' : 'border-gray-200' ?>">
                        <input type="radio" name="modo" value="balanceado"
                               class="mt-1"
                               <?= $config['modo']==='balanceado' ? 'checked' : '' ?>>
                        <div>
                            <div class="font-semibold text-lg">Balanceado por carga</div>
                            <p class="text-sm text-gray-600">
                                Asigna el nuevo ticket al agente con menos tickets abiertos.
                            </p>
                        </div>
                    </label>

                    <label class="border rounded-2xl p-4 flex gap-3 cursor-pointer
                                   <?= $config['modo']==='secuencial' ? 'border-blue-500 bg-blue-50' : 'border-gray-200' ?>">
                        <input type="radio" name="modo" value="secuencial"
                               class="mt-1"
                               <?= $config['modo']==='secuencial' ? 'checked' : '' ?>>
                        <div>
                            <div class="font-semibold text-lg">Secuencial (round-robin)</div>
                            <p class="text-sm text-gray-600">
                                Reparte los tickets de forma rotativa según el orden de los agentes activos.
                            </p>
                        </div>
                    </label>
                </div>
            </div>

            <hr class="border-gray-200">

            <!-- Límites -->
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-lg font-semibold mb-2">
                        Máx. tickets abiertos por agente
                    </label>
                    <input
                        type="number"
                        name="max_por_agente"
                        min="1"
                        class="w-full border-2 rounded-xl p-3 text-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        value="<?= (int)$config['max_por_agente'] ?>"
                    >
                    <p class="text-sm text-gray-500 mt-1">
                        Cuando un agente alcance este límite, se evitará asignarle más tickets.
                    </p>
                </div>

                <div>
                    <label class="block text-lg font-semibold mb-2">
                        Máx. tickets abiertos por cliente
                    </label>
                    <input
                        type="number"
                        name="max_abiertos_cliente"
                        min="1"
                        class="w-full border-2 rounded-xl p-3 text-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        value="<?= (int)$config['max_abiertos_cliente'] ?>"
                    >
                    <p class="text-sm text-gray-500 mt-1">
                        Útil para limitar el número de solicitudes abiertas de un mismo cliente.
                    </p>
                </div>
            </div>

            <hr class="border-gray-200">

            <!-- Notificaciones internas -->
            <div class="space-y-3">
                <h2 class="text-2xl font-bold text-blue-900">Notificaciones internas</h2>
                <p class="text-gray-600">
                    Opcionalmente puedes enviar un correo de alerta cuando la asignación automática
                    no encuentre agente disponible o se alcance algún límite.
                </p>

                <label class="inline-flex items-center cursor-pointer mb-3">
                    <input type="checkbox" name="notificar_correo" class="w-5 h-5 mr-2"
                           <?= $config['notificar_correo'] ? 'checked' : '' ?>>
                    <span class="text-lg text-gray-800">
                        Enviar correo de alerta
                    </span>
                </label>

                <div>
                    <label class="block text-lg font-semibold mb-2">
                        Correo(s) de alerta
                    </label>
                    <input
                        type="text"
                        name="correo_alerta"
                        class="w-full border-2 rounded-xl p-3 text-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="ej: soporte@sansouci.com, it@sansouci.com"
                        value="<?= htmlspecialchars($config['correo_alerta']) ?>"
                    >
                    <p class="text-sm text-gray-500 mt-1">
                        Puedes indicar varios correos separados por coma. Solo se usan si la opción
                        de alerta está activada.
                    </p>
                </div>
            </div>

            <div class="pt-4 flex items-center gap-4">
                <button
                    type="submit"
                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-8 py-3 rounded-xl text-lg shadow-lg"
                >
                    Guardar configuración
                </button>

                <a href="dashboard.php" class="text-blue-700 font-semibold">
                    ← Volver al dashboard
                </a>
            </div>
        </form>
    </div>
</div>

<?php
ob_end_flush();
require 'footer.php';
?>
