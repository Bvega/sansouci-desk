<?php
// INICIAR SESIÓN SOLO UNA VEZ
if(session_status() === PHP_SESSION_NONE) {
    session_start();
}
require 'config.php';

// PROTEGER PÁGINAS: SI NO ESTÁ LOGUEADO → LOGIN
$current_page = basename($_SERVER['PHP_SELF']);
if(!isset($_SESSION['user']) && !in_array($current_page, ['login.php', 'logout.php'])){
    header('Location: login.php');
    exit();
}

$user = $_SESSION['user'] ?? null;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sansouci Desk - <?= ucfirst(str_replace('.php', '', $current_page)) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: 'Segoe UI', sans-serif; }
        .sidebar { background: linear-gradient(180deg, #001f3f 0%, #003087 100%); }
        .logo-shadow { filter: drop-shadow(0 0 20px rgba(0, 208, 255, 0.8)); }
        .nav-item { transition: all 0.3s ease; }
        .nav-item:hover { background: rgba(255, 255, 255, 0.15); transform: translateX(8px); }
        .nav-active { background: rgba(255, 255, 255, 0.25); }
    </style>
</head>
<body class="min-h-screen flex">
    <!-- SIDEBAR PROFESIONAL -->
    <div class="sidebar w-80 text-white flex flex-col shadow-2xl">
        <!-- Logo y Título -->
        <div class="p-8 text-center border-b border-blue-800">
            <img src="https://www.sansouci.com.do/wp-content/uploads/2020/06/logo-sansouci.png" 
                 alt="Sansouci" class="h-20 mx-auto logo-shadow">
            <h1 class="text-3xl font-bold mt-4">SANSOUCI DESK</h1>
            <p class="text-blue-200 text-sm">Puerto de Santo Domingo</p>
        </div>

        <!-- Navegación -->
        <nav class="flex-1 p-6 space-y-3">
            <a href="dashboard.php" 
               class="nav-item block py-5 px-8 rounded-xl text-xl font-bold flex items-center <?= $current_page=='dashboard.php'?'nav-active':'' ?>">
                <i class="fas fa-tachometer-alt mr-4 text-2xl"></i> Dashboard
            </a>

            <a href="tickets.php" 
               class="nav-item block py-5 px-8 rounded-xl text-xl font-bold flex items-center <?= $current_page=='tickets.php'?'nav-active':'' ?>">
                <i class="fas fa-ticket-alt mr-4 text-2xl"></i> Tickets
            </a>

            <!-- REPORTES → VISIBLE PARA TODOS -->
            <a href="reportes.php" 
               class="nav-item block py-5 px-8 rounded-xl text-xl font-bold flex items-center <?= $current_page=='reportes.php'?'nav-active':'' ?>">
                <i class="fas fa-chart-bar mr-4 text-2xl"></i> Reportes
            </a>

            <!-- MANTENIMIENTO → SOLO ADMIN Y SUPERADMIN -->
            <?php if($user && in_array($user['rol'], ['administrador', 'superadmin'])): ?>
            <a href="mantenimiento.php" 
               class="nav-item block py-5 px-8 rounded-xl text-xl font-bold flex items-center <?= $current_page=='mantenimiento.php'?'nav-active':'' ?>">
                <i class="fas fa-tools mr-4 text-2xl"></i> Mantenimiento
            </a>
            <?php endif; ?>
                        <!-- ESPACIADOR VISUAL -->
            <div class="my-8"></div>

            <!-- CONFIGURACIÓN DE CORREO -->
            <?php if(in_array($user['rol'],['administrador','superadmin'])): ?>
            <a href="config_correo.php" 
               class="nav-item block py-6 px-10 rounded-2xl text-2xl font-bold flex items-center shadow-lg hover:shadow-2xl transition-all duration-300 
                      <?= $current_page=='config_correo.php'?'bg-gradient-to-r from-yellow-500 to-orange-500 text-white shadow-2xl scale-105':'bg-white text-blue-900 hover:bg-blue-50' ?>">
                <i class="fas fa-envelope-open-text mr-6 text-3xl"></i> 
                <span>Config. Correo</span>
            </a>
            <?php endif; ?>

                        <!-- PLANTILLAS DE RESPUESTA -->
            <?php if(in_array($user['rol'],['administrador','superadmin','agente'])): ?>
            <a href="plantillas.php" 
               class="nav-item block py-6 px-10 rounded-2xl text-2xl font-bold flex items-center shadow-lg hover:shadow-2xl transition-all duration-300 
                      <?= $current_page=='plantillas.php'?'bg-gradient-to-r from-green-500 to-teal-500 text-white shadow-2xl scale-105':'bg-white text-blue-900 hover:bg-blue-50' ?>">
                <i class="fas fa-bolt mr-6 text-3xl"></i> 
                <span>Respuestas Rápidas</span>
            </a>
            <?php endif; ?>

            <!-- ESPACIADOR VISUAL -->
            <div class="my-8"></div>

             
                        <!-- TIPOS DE SERVICIO → SOLO ADMIN Y SUPERADMIN -->
            <?php if($user && in_array($user['rol'], ['administrador', 'superadmin'])): ?>
            <a href="tipos_servicio.php" 
               class="nav-item block py-5 px-8 rounded-xl text-xl font-bold flex items-center <?= $current_page=='tipos_servicio.php'?'nav-active':'' ?>">
                <i class="fas fa-list-alt mr-4 text-2xl"></i> Tipos de Servicio
            </a>
            <?php endif; ?>
                        <!-- ASIGNACIÓN AUTOMÁTICA → SOLO ADMIN Y SUPERADMIN -->
            <?php if($user && in_array($user['rol'], ['administrador', 'superadmin'])): ?>
            <a href="asignacion_tickets.php" 
               class="nav-item block py-5 px-8 rounded-xl text-xl font-bold flex items-center <?= $current_page=='asignacion_tickets.php'?'nav-active':'' ?>">
                <i class="fas fa-user-cog mr-4 text-2xl"></i> Asignación Automática
            </a>
            <?php endif; ?>
        </nav>

        <!-- Usuario y Cerrar Sesión -->
        <div class="p-6 border-t border-blue-800">
            <?php if($user): ?>
            <div class="flex items-center space-x-4 mb-6">
                <div class="bg-white bg-opacity-20 rounded-full w-16 h-16 flex items-center justify-center">
                    <i class="fas fa-user text-3xl"></i>
                </div>
                <div>
                    <p class="font-bold text-xl"><?= htmlspecialchars($user['nombre']) ?></p>
                    <p class="text-sm text-blue-200">
                        <?= $user['rol'] == 'superadmin' ? 'Super Admin' : ucfirst($user['rol']) ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>

            <a href="logout.php" 
               class="block text-center bg-red-600 hover:bg-red-700 py-4 rounded-xl font-bold text-xl transition shadow-lg">
                <i class="fas fa-sign-out-alt mr-3"></i> Cerrar Sesión
            </a>
        </div>
    </div>

    <!-- CONTENIDO PRINCIPAL -->
    <div class="flex-1 p-10">
        <div class="bg-white rounded-3xl shadow-2xl p-10 min-h-screen">
            <!-- Aquí va el contenido de cada página -->