<?php
// Script de prueba interna del UserModel
// NO es parte del sistema final. Solo sirve para verificar que el refactor funciona.

require __DIR__ . '/config.php';        // por si hay cosas globales
require __DIR__ . '/app/bootstrap.php'; // autoload + sesión

use App\Models\UserModel;

// Cambia este email por el que tengas en la tabla `users`
$emailPrueba = 'admin@local.test';

$user = UserModel::findByEmail($emailPrueba);

echo '<pre>';
echo "Probando UserModel::findByEmail('$emailPrueba'):\n\n";
var_dump($user);
echo "\n\n";

if ($user) {
    echo "Probando verifyLogin con contraseña '123456':\n\n";
    $valid = UserModel::verifyLogin($emailPrueba, '123456');
    var_dump($valid ? 'OK (login válido)' : 'FAIL (login inválido)');
} else {
    echo "No se encontró usuario con ese email.\n";
}
echo '</pre>';
