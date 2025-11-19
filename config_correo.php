<?php
ob_start();

require __DIR__ . '/config.php';
require __DIR__ . '/config_email.php';
require 'header.php';

// Solo admin / superadmin
if (!$user || !in_array($user['rol'], ['administrador', 'superadmin'])) {
    header('Location: dashboard.php');
    exit();
}

// Cargar config actual
$currentConfig = loadEmailConfig($pdo);

$errors = [];
$successMessage = '';
$testMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {

        $smtp_host   = trim($_POST['smtp_host']   ?? '');
        $smtp_port   = (int)($_POST['smtp_port']  ?? 587);
        $smtp_user   = trim($_POST['smtp_user']   ?? '');
        $smtp_pass   = $_POST['smtp_pass']        ?? '';
        $smtp_secure = $_POST['smtp_secure']      ?? 'tls';
        $from_email  = trim($_POST['from_email']  ?? '');
        $from_name   = trim($_POST['from_name']   ?? 'Sansouci Desk');

        if ($smtp_host === '') {
            $errors[] = 'El host SMTP es obligatorio.';
        }
        if ($smtp_user === '') {
            $errors[] = 'El usuario SMTP es obligatorio.';
        }
        if ($from_email === '') {
            $errors[] = 'El correo remitente (From) es obligatorio.';
        } elseif (!filter_var($from_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El correo remitente no es válido.';
        }

        // Tomar la config actual como base
        $newConfig = $currentConfig;

        $newConfig['smtp_host']   = $smtp_host;
        $newConfig['smtp_port']   = $smtp_port;
        $newConfig['smtp_user']   = $smtp_user;
        $newConfig['smtp_secure'] = $smtp_secure;
        $newConfig['from_email']  = $from_email;
        $newConfig['from_name']   = $from_name;

        // Solo si escriben algo en password, lo actualizamos
        if ($smtp_pass !== '') {
            $newConfig['smtp_pass'] = $smtp_pass;
        }

        if (empty($errors)) {
            saveEmailConfig($pdo, $newConfig);
            $currentConfig   = loadEmailConfig($pdo);
            $successMessage  = 'Configuración de correo guardada correctamente.';
        }

    } elseif ($action === 'test') {

        $test_email = trim($_POST['test_email'] ?? '');

        if ($test_email === '') {
            $errors[] = 'Debes indicar un correo para la prueba.';
        } elseif (!filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El correo de prueba no es válido.';
        } else {
            $ok = sendSupportMail(
                $test_email,
                'Prueba de configuración Sansouci Desk',
                '<p>Este es un correo de prueba enviado desde Sansouci Desk.</p>'
            );

            if ($ok) {
                $testMessage = 'Correo de prueba enviado correctamente a ' . htmlspecialchars($test_email) . '.';
            } else {
                $errors[] = 'No se pudo enviar el correo de prueba. Revisa los datos SMTP.';
            }
        }
    }
}
?>

<h1 class="text-4xl font-bold text-blue-900 mb-6">Configuración de Correo</h1>
<p class="text-gray-600 mb-8 max-w-3xl">
    Define aquí los parámetros de tu servidor SMTP. Esta configuración se utilizará para enviar
    notificaciones de tickets y respuestas a los clientes.
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
    <div class="mb-4 px-6 py-4 border border-green-400 bg-green-100 text-green-800 rounded-xl text-lg">
        <?= htmlspecialchars($successMessage) ?>
    </div>
<?php endif; ?>

<?php if ($testMessage): ?>
    <div class="mb-4 px-6 py-4 border border-blue-400 bg-blue-100 text-blue-800 rounded-xl text-lg">
        <?= htmlspecialchars($testMessage) ?>
    </div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    <!-- Configuración SMTP -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <h2 class="text-2xl font-bold text-blue-900 mb-4">Servidor SMTP</h2>

            <form method="POST" class="space-y-5">
                <input type="hidden" name="action" value="save">

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">
                        Host SMTP
                    </label>
                    <input
                        type="text"
                        name="smtp_host"
                        value="<?= htmlspecialchars($currentConfig['smtp_host'] ?? '') ?>"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="smtp.tudominio.com">
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">
                            Puerto
                        </label>
                        <input
                            type="number"
                            name="smtp_port"
                            value="<?= htmlspecialchars((string)($currentConfig['smtp_port'] ?? 587)) ?>"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">
                            Seguridad
                        </label>
                        <select
                            name="smtp_secure"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <?php $sec = $currentConfig['smtp_secure'] ?? 'tls'; ?>
                            <option value="tls"  <?= $sec === 'tls'  ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl"  <?= $sec === 'ssl'  ? 'selected' : '' ?>>SSL</option>
                            <option value="none" <?= $sec === 'none' ? 'selected' : '' ?>>Sin cifrado</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">
                            Usuario SMTP
                        </label>
                        <input
                            type="text"
                            name="smtp_user"
                            value="<?= htmlspecialchars($currentConfig['smtp_user'] ?? '') ?>"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="usuario@tudominio.com">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">
                        Contraseña SMTP
                    </label>
                    <input
                        type="password"
                        name="smtp_pass"
                        value=""
                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="••••••••">
                    <p class="mt-1 text-xs text-slate-500">
                        Deja este campo vacío si no deseas cambiar la contraseña actual.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">
                            Correo remitente (From)
                        </label>
                        <input
                            type="email"
                            name="from_email"
                            value="<?= htmlspecialchars($currentConfig['from_email'] ?? '') ?>"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="no-reply@tudominio.com">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">
                            Nombre remitente
                        </label>
                        <input
                            type="text"
                            name="from_name"
                            value="<?= htmlspecialchars($currentConfig['from_name'] ?? 'Sansouci Desk') ?>"
                            class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="pt-4">
                    <button
                        type="submit"
                        class="inline-flex items-center px-6 py-3 rounded-xl bg-blue-700 hover:bg-blue-800 text-white font-semibold text-sm shadow-lg">
                        Guardar configuración
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Envío de prueba -->
    <div>
        <div class="bg-white rounded-2xl shadow-2xl p-6">
            <h2 class="text-xl font-bold text-blue-900 mb-3">Enviar correo de prueba</h2>
            <p class="text-sm text-slate-600 mb-4">
                Envía un correo de prueba usando la configuración actual para verificar que todo esté correcto.
            </p>

            <form method="POST" class="space-y-3">
                <input type="hidden" name="action" value="test">

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">
                        Correo de prueba
                    </label>
                    <input
                        type="email"
                        name="test_email"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="tucorreo@ejemplo.com">
                </div>

                <button
                    type="submit"
                    class="inline-flex items-center px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-sm shadow">
                    Enviar prueba
                </button>
            </form>
        </div>
    </div>
</div>

<?php
require 'footer.php';
ob_end_flush();
