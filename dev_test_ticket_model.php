<?php
require __DIR__ . '/config.php';
require __DIR__ . '/app/bootstrap.php';

use App\Models\TicketModel;

echo "Probando TicketModel::getTicketsForUser()<br><br>";

$user = [
    'id'  => $_SESSION['user_id'] ?? 1,
    'rol' => $_SESSION['user_role'] ?? 'superadmin',
];

$tickets = TicketModel::getTicketsForUser($user, null);

echo '<pre>';
print_r(array_slice($tickets, 0, 3));
echo '</pre>';
