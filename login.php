<?php
// login.php

require __DIR__ . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si ya está logueado, ir directo al dashboard
if (isset($_SESSION['user']) && is_array($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit();
}

$errors = [];
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

// Manejo de login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (trim($email) === '' || trim($password) === '') {
        $errors[] = 'Correo y contraseña son obligatorios.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $userRow = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userRow) {
            $hash = $userRow['password'] ?? '';

            $valid = false;

            // 1) password_hash
            if ($hash && password_get_info($hash)['algo'] !== 0) {
                $valid = password_verify($password, $hash);
            }

            // 2) texto plano
            if (!$valid && $password === $hash) {
                $valid = true;
            }

            // 3) md5 heredado
            if (!$valid && $hash === md5($password)) {
                $valid = true;
            }

            if ($valid) {
                $_SESSION['user'] = [
                    'id'     => $userRow['id'],
                    'nombre' => $userRow['nombre'] ?? $userRow['email'],
                    'email'  => $userRow['email'],
                    'rol'    => $userRow['rol'] ?? 'agente',
                ];

                header('Location: dashboard.php');
                exit();
            } else {
                $errors[] = 'Credenciales inválidas. Verifica correo y contraseña.';
            }
        } else {
            $errors[] = 'Usuario no encontrado.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ingresar - Sansouci Desk</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; }
    </style>
</head>
<body class="min-h-screen bg-slate-900 flex items-center justify-center px-4">
    <div class="w-full max-w-md">
        <div class="bg-white rounded-3xl shadow-2xl p-8 sm:p-10">
            <div class="mb-6 text-center">
                <img
                    src="https://www.sansouci.com.do/wp-content/uploads/2020/06/logo-sansouci.png"
                    alt="Sansouci"
                    class="h-16 mx-auto mb-3"
                >
                <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 mb-1">
                    Sansouci Desk
                </h1>
                <p class="text-sm text-slate-500">
                    Panel interno de atención a tickets
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

            <form method="POST" class="space-y-5 mb-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">
                        Correo
                    </label>
                    <input
                        type="email"
                        name="email"
                        value="<?= htmlspecialchars($email) ?>"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="tu.correo@empresa.com"
                        required
                    >
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">
                        Contraseña
                    </label>
                    <input
                        type="password"
                        name="password"
                        class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        placeholder="••••••••"
                        required
                    >
                </div>

                <button
                    type="submit"
                    class="w-full inline-flex items-center justify-center rounded-xl bg-blue-700 hover:bg-blue-800 text-white font-semibold py-2.5 text-sm shadow-lg"
                >
                    Iniciar sesión
                </button>
            </form>

            <!-- Botón de acceso al Portal de Clientes -->
            <a
                href="portal_cliente.php"
                class="w-full inline-flex items-center justify-center rounded-xl border border-slate-300 text-slate-700 font-semibold py-2.5 text-sm hover:bg-slate-50 transition"
            >
                Acceder al Portal de Clientes
            </a>

            <p class="mt-4 text-[11px] text-center text-slate-400">
                © <?= date('Y') ?> Sansouci Puerto de Santo Domingo
            </p>
        </div>
    </div>
</body>
</html>
