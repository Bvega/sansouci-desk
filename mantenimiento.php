<?php
ob_start();
require 'header.php'; // ya tenemos $pdo y $user

// Solo admin / superadmin
if (!$user || !in_array($user['rol'], ['administrador', 'superadmin'])) {
    ?>
    <div class="bg-white rounded-3xl shadow-2xl p-10">
        <h1 class="text-3xl font-bold text-blue-900 mb-4">Mantenimiento del sistema</h1>
        <p class="text-lg text-gray-600">
            Esta sección solo está disponible para usuarios con rol de
            <strong>administrador</strong> o <strong>superadmin</strong>.
        </p>
    </div>
    <?php
    ob_end_flush();
    require 'footer.php';
    exit;
}

/* =========================
   1. RESUMEN DE TICKETS
   ========================= */

// Tickets totales y por estado (últimos 30 días)
$hoy = date('Y-m-d H:i:s');
$hace_30 = date('Y-m-d H:i:s', strtotime('-30 days'));

$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS total,
        SUM(estado = 'abierto')      AS abiertos,
        SUM(estado = 'en_proceso')   AS en_proceso,
        SUM(estado = 'cerrado')      AS cerrados
    FROM tickets
    WHERE creado_el BETWEEN ? AND ?
");
$stmt->execute([$hace_30, $hoy]);
$stats_tickets = $stmt->fetch(PDO::FETCH_ASSOC) ?: [
    'total'      => 0,
    'abiertos'   => 0,
    'en_proceso' => 0,
    'cerrados'   => 0,
];

/* =========================
   2. USUARIOS Y ROLES
   ========================= */

$total_usuarios   = 0;
$total_agentes    = 0;
$total_admins     = 0;
$total_superadmin = 0;

$stmt = $pdo->query("
    SELECT rol, COUNT(*) AS c
    FROM users
    GROUP BY rol
");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

foreach ($roles as $r) {
    $total_usuarios += (int)$r['c'];
    switch ($r['rol']) {
        case 'agente':
            $total_agentes += (int)$r['c'];
            break;
        case 'administrador':
            $total_admins += (int)$r['c'];
            break;
        case 'superadmin':
            $total_superadmin += (int)$r['c'];
            break;
    }
}

/* =========================
   3. CONFIGURACIÓN DE CORREO
   ========================= */

$config_email = [];
try {
    $stmt = $pdo->query("SELECT * FROM config_email WHERE id = 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $config_email = $row;
    }
} catch (Exception $e) {
    // si algo falla, dejamos $config_email como array vacío
}

$smtp_host  = $config_email['smtp_host']  ?? 'No configurado';
$smtp_user  = $config_email['smtp_user']  ?? '—';
$smtp_port  = $config_email['smtp_port']  ?? '—';
$activado   = (int)($config_email['activado'] ?? 0); // <- aquí evitamos el warning
$correo_ok  = $activado === 1;

/* =========================
   4. CATÁLOGOS
   ========================= */

// Tipos de servicio
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM tipos_servicio");
    $total_tipos = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    $total_tipos = 0;
}

// Plantillas de respuesta rápida
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM plantillas_respuesta");
    $total_plantillas = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    $total_plantillas = 0;
}

// Reglas de asignación automática
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM config_asignacion");
    $total_reglas_asignacion = (int)$stmt->fetchColumn();
} catch (Exception $e) {
    $total_reglas_asignacion = 0;
}
?>

<div class="flex flex-col gap-10">

    <!-- Título -->
    <div>
        <h1 class="text-4xl font-extrabold text-blue-900 mb-2">
            Mantenimiento del sistema
        </h1>
        <p class="text-lg text-gray-600">
            Resumen rápido del estado de <strong>Sansouci Desk</strong> y sus componentes principales.
        </p>
    </div>

    <!-- Tarjetas de Tickets -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white rounded-2xl shadow-xl p-6 border-t-4 border-blue-700">
            <p class="text-sm text-gray-500">Tickets totales (últimos 30 días)</p>
            <p class="text-3xl font-extrabold text-blue-900 mt-2">
                <?= (int)$stats_tickets['total'] ?>
            </p>
        </div>
        <div class="bg-white rounded-2xl shadow-xl p-6 border-t-4 border-green-600">
            <p class="text-sm text-gray-500">Abiertos</p>
            <p class="text-3xl font-extrabold text-green-700 mt-2">
                <?= (int)$stats_tickets['abiertos'] ?>
            </p>
        </div>
        <div class="bg-white rounded-2xl shadow-xl p-6 border-t-4 border-yellow-500">
            <p class="text-sm text-gray-500">En proceso</p>
            <p class="text-3xl font-extrabold text-yellow-600 mt-2">
                <?= (int)$stats_tickets['en_proceso'] ?>
            </p>
        </div>
        <div class="bg-white rounded-2xl shadow-xl p-6 border-t-4 border-gray-500">
            <p class="text-sm text-gray-500">Cerrados</p>
            <p class="text-3xl font-extrabold text-gray-700 mt-2">
                <?= (int)$stats_tickets['cerrados'] ?>
            </p>
        </div>
    </div>

    <!-- Usuarios y roles + Configuración -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

        <!-- Usuarios y roles -->
        <div class="bg-white rounded-2xl shadow-xl p-6">
            <h2 class="text-2xl font-bold text-blue-900 mb-4">Usuarios y roles</h2>

            <p class="text-sm text-gray-500 mb-4">
                Resumen de usuarios registrados en el sistema.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="bg-blue-50 rounded-xl p-4">
                    <p class="text-xs text-gray-500">Total usuarios</p>
                    <p class="text-2xl font-bold text-blue-900 mt-1">
                        <?= $total_usuarios ?>
                    </p>
                </div>
                <div class="bg-green-50 rounded-xl p-4">
                    <p class="text-xs text-gray-500">Agentes</p>
                    <p class="text-2xl font-bold text-green-700 mt-1">
                        <?= $total_agentes ?>
                    </p>
                </div>
                <div class="bg-yellow-50 rounded-xl p-4">
                    <p class="text-xs text-gray-500">Administradores</p>
                    <p class="text-2xl font-bold text-yellow-700 mt-1">
                        <?= $total_admins ?>
                    </p>
                </div>
                <div class="bg-purple-50 rounded-xl p-4">
                    <p class="text-xs text-gray-500">Superadmins</p>
                    <p class="text-2xl font-bold text-purple-700 mt-1">
                        <?= $total_superadmin ?>
                    </p>
                </div>
            </div>
        </div>

        <!-- Configuración y catálogos -->
        <div class="bg-white rounded-2xl shadow-xl p-6">
            <h2 class="text-2xl font-bold text-blue-900 mb-4">Configuración y catálogos</h2>

            <!-- SMTP -->
            <div class="mb-4">
                <p class="text-sm font-semibold text-gray-700 mb-1">Configuración de correo SMTP</p>
                <p class="text-sm text-gray-600">
                    Host: <strong><?= htmlspecialchars($smtp_host) ?></strong><br>
                    Usuario: <strong><?= htmlspecialchars($smtp_user) ?></strong><br>
                    Puerto: <strong><?= htmlspecialchars($smtp_port) ?></strong><br>
                    Estado: 
                    <?php if ($correo_ok): ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-green-100 text-green-800">
                            ACTIVO
                        </span>
                    <?php else: ?>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-red-100 text-red-700">
                            INACTIVO
                        </span>
                    <?php endif; ?>
                </p>
                <a href="config_correo.php" class="inline-block mt-2 text-sm text-blue-700 font-semibold">
                    Configurar correo →
                </a>
            </div>

            <!-- Tipos, plantillas, reglas -->
            <div class="mt-6 space-y-3 text-sm text-gray-700">
                <div class="flex items-center justify-between">
                    <span>Tipos de servicio configurados</span>
                    <span class="font-bold">
                        <?= $total_tipos ?> tipo(s)
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span>Plantillas de respuestas rápidas</span>
                    <span class="font-bold">
                        <?= $total_plantillas ?> plantilla(s)
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span>Reglas de asignación automática</span>
                    <span class="font-bold">
                        <?= $total_reglas_asignacion ?> regla(s)
                    </span>
                </div>

                <div class="mt-4 flex flex-wrap gap-2 text-xs">
                    <a href="tipos_servicio.php"
                       class="px-3 py-1 rounded-full bg-blue-50 text-blue-700 font-semibold">
                        Ver tipos de servicio
                    </a>
                    <a href="plantillas.php"
                       class="px-3 py-1 rounded-full bg-blue-50 text-blue-700 font-semibold">
                        Ver plantillas
                    </a>
                    <a href="asignacion_tickets.php"
                       class="px-3 py-1 rounded-full bg-blue-50 text-blue-700 font-semibold">
                        Ver asignación automática
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Notas de mantenimiento -->
    <div class="bg-white rounded-2xl shadow-xl p-6">
        <h2 class="text-2xl font-bold text-blue-900 mb-3">Notas de mantenimiento</h2>
        <ul class="list-disc pl-6 text-sm text-gray-700 space-y-1">
            <li>Revisa periódicamente los tipos de servicio y reglas de asignación automática.</li>
            <li>Verifica que la configuración de correo SMTP esté activa y usando una cuenta válida.</li>
            <li>Usa la sección de <strong>Reportes</strong> para analizar el volumen de tickets y desempeño por agente.</li>
            <li>Considera exportar la base de datos desde phpMyAdmin como respaldo periódico.</li>
        </ul>
    </div>
</div>

<?php
ob_end_flush();
require 'footer.php';
?>
