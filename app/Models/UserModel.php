<?php

namespace App\Models;

use App\Core\Database;
use PDO;

/**
 * UserModel
 *
 * Encapsula el acceso a la tabla `users`.
 * De momento solo necesitamos operaciones básicas para login,
 * pero luego podemos extenderlo (crear, editar, cambiar rol, etc.).
 */
class UserModel
{
    /**
     * Devuelve un usuario por email o null si no existe.
     *
     * @param string $email
     * @return array|null
     */
    public static function findByEmail(string $email): ?array
    {
        $pdo = Database::getConnection();

        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    /**
     * Verifica credenciales de login (email + password).
     * Devuelve el usuario si es válido o null si falla.
     *
     * @param string $email
     * @param string $password
     * @return array|null
     */
    public static function verifyLogin(string $email, string $password): ?array
    {
        $user = self::findByEmail($email);

        if (!$user) {
            return null;
        }

        if (!isset($user['password'])) {
            return null;
        }

        if (!password_verify($password, $user['password'])) {
            return null;
        }

        return $user;
    }
}
