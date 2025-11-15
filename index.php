<?php require 'config.php'; ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sansouci Desk - Portal Clientes</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style> body { background: #001f3f; } </style>
</head>
<body class="text-white">
<div class="min-h-screen flex items-center justify-center">
    <div class="bg-white text-gray-800 p-10 rounded-xl shadow-2xl max-w-2xl">
        <img src="https://www.sansouci.com.do/wp-content/uploads/2020/06/logo-sansouci.png" alt="Sansouci" class="h-16 mx-auto mb-6">
        <h1 class="text-4xl font-bold text-center text-blue-900 mb-8">Portal de Clientes</h1>
        
        <form action="crear_ticket.php" method="POST" class="space-y-6">
            <input type="email" name="email" placeholder="Tu correo" required class="w-full p-4 border rounded-lg">
            <input type="text" name="asunto" placeholder="Asunto" required class="w-full p-4 border rounded-lg">
            <textarea name="mensaje" placeholder="Describe tu solicitud..." required rows="6" class="w-full p-4 border rounded-lg"></textarea>
            <button type="submit" class="w-full bg-blue-900 text-white py-4 rounded-lg hover:bg-blue-800 text-xl">Enviar Solicitud</button>
        </form>
        <p class="text-center mt-6 text-sm">© 2025 Sansouci Puerto de Santo Domingo</p>
    </div>
</div>
</body>
</html>