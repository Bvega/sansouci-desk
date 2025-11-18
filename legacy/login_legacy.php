<?php
session_start();
require 'config.php';

if(isset($_SESSION['user'])){
    header('Location: dashboard.php');
    exit();
}

$error = '';

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if(empty($email) || empty($password)){
        $error = "Completa ambos campos";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if($user && password_verify($password, $user['password'])){
            $_SESSION['user'] = [
                'id' => $user['id'],
                'nombre' => $user['nombre'],
                'email' => $user['email'],
                'rol' => $user['rol']
            ];
            header('Location: dashboard.php');
            exit();
        } else {
            $error = "Credenciales incorrectas";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sansouci Desk - Acceso</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: linear-gradient(135deg, #001f3f 0%, #003087 100%); }
        .card { backdrop-filter: blur(12px); background: rgba(255, 255, 255, 0.95); }
        .input-focus:focus { outline: none; box-shadow: 0 0 0 3px rgba(0, 48, 135, 0.3); }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    <div class="card rounded-2xl shadow-2xl w-full max-w-md border border-white border-opacity-20">
        <!-- Logo y Título -->
        <div class="text-center pt-10 pb-8 px-8">
            <img src="https://www.sansouci.com.do/wp-content/uploads/2020/06/logo-sansouci.png" 
                 alt="Sansouci" class="h-20 mx-auto mb-4">
            <h1 class="text-3xl font-bold text-blue-900">SANSOUCI DESK</h1>
            <p class="text-blue-700 text-sm mt-2">Puerto de Santo Domingo</p>
        </div>

        <!-- Formulario -->
        <div class="px-10 pb-12">
            <h2 class="text-2xl font-bold text-center text-gray-800 mb-8">Iniciar Sesión</h2>
            
            <?php if($error): ?>
            <div class="bg-red-50 border border-red-300 text-red-700 px-4 py-3 rounded-lg mb-6 text-sm">
                <i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="space-y-6">
                <div>
                    <div class="relative">
                        <i class="fas fa-user absolute left-4 top-4 text-blue-600"></i>
                        <input type="email" name="email" required autofocus
                               class="w-full pl-12 pr-4 py-4 border border-gray-300 rounded-lg input-focus text-gray-800 placeholder-gray-500"
                               placeholder="Correo electrónico"
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>
                </div>

                <div>
                    <div class="relative">
                        <i class="fas fa-lock absolute left-4 top-4 text-blue-600"></i>
                        <input type="password" name="password" required
                               class="w-full pl-12 pr-4 py-4 border border-gray-300 rounded-lg input-focus text-gray-800"
                               placeholder="Contraseña">
                    </div>
                </div>

                <button type="submit" 
                        class="w-full bg-gradient-to-r from-blue-900 to-blue-700 text-white py-4 rounded-lg font-bold text-lg hover:from-blue-800 hover:to-blue-600 transition shadow-lg">
                    <i class="fas fa-sign-in-alt mr-2"></i> INGRESAR
                </button>
            </form>

            <div class="mt-8 text-center text-sm text-gray-600">
                <p>¿Problemas de acceso?</p>
                <a href="mailto:soporte@sansouci.com.do" class="text-blue-700 font-semibold hover:underline">
                    Contacta al administrador
                </a>
            </div>

            <div class="mt-10 text-center text-xs text-gray-500 border-t pt-6">
                <p>© 2025 Sansouci Puerto de Santo Domingo</p>
                <p class="mt-1 text-blue-900 font-bold">Sistema Empresarial v5.0</p>
            </div>
        </div>
    </div>
</body>
</html>