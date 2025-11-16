<?php
require __DIR__ . '/config.php';
require __DIR__ . '/app/bootstrap.php';

use App\Models\UserModel;

$pageTitle = 'Iniciar sesión';
$errors = [];

// Ruta del dashboard
define('ADMIN_DASHBOARD_PATH', 'dashboard.php');

// Procesar envío
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = isset($_POST['email']) ? trim($_POST['email']) : '';
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'Por favor, complete ambos campos.';
    } else {
        $user = UserModel::verifyLogin($email, $password);

        if (!$user) {
            $errors[] = 'Correo o contraseña incorrectos.';
        } else {
            // Nueva sesión
            $_SESSION['user_id']    = $user['id'] ?? null;
            $_SESSION['user_name']  = $user['nombre'] ?? '';
            $_SESSION['user_email'] = $user['email'] ?? '';
            $_SESSION['user_role']  = $user['rol'] ?? '';

            // Compatibilidad
            $_SESSION['user'] = [
                'id'     => $user['id'] ?? null,
                'nombre' => $user['nombre'] ?? '',
                'email'  => $user['email'] ?? '',
                'rol'    => $user['rol'] ?? '',
            ];

            header('Location: ' . ADMIN_DASHBOARD_PATH);
            exit;
        }
    }
}

include __DIR__ . '/header.php';
?>

<h2 class="text-2xl font-bold mb-6">Acceso al Panel</h2>

<?php if (!empty($errors)): ?>
    <div class="mb-4 p-3 rounded bg-red-100 text-red-800">
        <?php foreach ($errors as $error): ?>
            <div><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="post" action="login.php" class="space-y-4 max-w-md">
    <div>
        <label for="email" class="block font-semibold mb-1">Correo electrónico</label>
        <input
            type="email"
            id="email"
            name="email"
            required
            class="w-full border rounded px-3 py-2"
            value="<?= isset($email) ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : '' ?>"
        >
    </div>

    <div>
        <label for="password" class="block font-semibold mb-1">Contraseña</label>
        <input
            type="password"
            id="password"
            name="password"
            required
            class="w-full border rounded px-3 py-2"
        >
    </div>

    <button type="submit" class="px-4 py-2 rounded bg-blue-600 text-white font-semibold hover:bg-blue-700">
        Iniciar sesión
    </button>
</form>

<?php include __DIR__ . '/footer.php'; ?>
