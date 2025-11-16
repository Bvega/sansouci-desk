<?php

namespace App\Models;

use PDO;

class TicketModel
{
    /**
     * Obtiene la conexión PDO usada por el proyecto (la que define bootstrap.php).
     *
     * @return PDO
     */
    protected static function getPdo(): PDO
    {
        global $pdo;

        if (!$pdo instanceof PDO) {
            throw new \RuntimeException('La conexión PDO no está disponible en TicketModel.');
        }

        return $pdo;
    }

    /**
     * Devuelve la lista de tickets visibles para el usuario actual
     * (filtrados por rol y por estado).
     *
     * @param array       $user   ['id' => ..., 'rol' => ...]
     * @param string|null $estado 'abierto', 'en_proceso', 'cerrado' o null
     * @return array
     */
    public static function getTicketsForUser(array $user, ?string $estado = null): array
    {
        $pdo = self::getPdo();

        $where  = 'WHERE 1=1';
        $params = [];

        // Si es agente: solo sus tickets
        if (($user['rol'] ?? null) === 'agente') {
            $where    .= ' AND t.agente_id = ?';
            $params[] = $user['id'];
        }

        // Filtro por estado
        if ($estado !== null && $estado !== '') {
            $where    .= ' AND t.estado = ?';
            $params[] = $estado;
        }

        $sql = "
            SELECT t.*, u.nombre AS agente_nombre
            FROM tickets t
            LEFT JOIN users u ON t.agente_id = u.id
            $where
            ORDER BY t.creado_el DESC
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Elimina tickets y sus respuestas asociadas.
     * Devuelve cuántos tickets se eliminaron.
     *
     * @param int[] $ids
     * @return int
     */
    public static function deleteTicketsAndResponses(array $ids): int
    {
        if (empty($ids)) {
            return 0;
        }

        $pdo = self::getPdo();

        // Aseguramos que todos sean enteros
        $ids = array_map('intval', $ids);

        // Placeholders ?, ?, ?, ...
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        try {
            $pdo->beginTransaction();

            // Borrar respuestas relacionadas
            $stmtResp = $pdo->prepare("DELETE FROM respuestas WHERE ticket_id IN ($placeholders)");
            $stmtResp->execute($ids);

            // Borrar tickets
            $stmtTickets = $pdo->prepare("DELETE FROM tickets WHERE id IN ($placeholders)");
            $stmtTickets->execute($ids);
            $deleted = $stmtTickets->rowCount();

            $pdo->commit();
            return $deleted;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }
}
