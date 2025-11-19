<?php
// config_email.php
// Helper central para enviar correos usando PHPMailer + configuración en BD

require __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/PHPMailer/src/Exception.php';
require __DIR__ . '/PHPMailer/src/PHPMailer.php';
require __DIR__ . '/PHPMailer/src/SMTP.php';

/**
 * Carga la configuración SMTP desde la tabla config_email (fila id = 1).
 */
function loadEmailConfig(PDO $pdo): array
{
    $stmt = $pdo->query("SELECT * FROM config_email WHERE id = 1 LIMIT 1");
    $row  = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    return [
        'smtp_host'   => $row['smtp_host']   ?? '',
        'smtp_port'   => (int)($row['smtp_port']   ?? 587),
        'smtp_user'   => $row['smtp_user']   ?? '',
        'smtp_pass'   => $row['smtp_pass']   ?? '',
        'smtp_secure' => $row['smtp_secure'] ?? 'tls', // tls|ssl|none
        'from_email'  => $row['from_email']  ?? '',
        'from_name'   => $row['from_name']   ?? 'Sansouci Desk',
    ];
}

/**
 * Guarda la configuración SMTP en la tabla config_email (fila fija id = 1).
 */
function saveEmailConfig(PDO $pdo, array $data): void
{
    $sql = "
        REPLACE INTO config_email
            (id, smtp_host, smtp_port, smtp_user, smtp_pass, smtp_secure, from_email, from_name)
        VALUES
            (1, ?, ?, ?, ?, ?, ?, ?)
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $data['smtp_host'],
        (int)$data['smtp_port'],
        $data['smtp_user'],
        $data['smtp_pass'],
        $data['smtp_secure'],
        $data['from_email'],
        $data['from_name'],
    ]);
}

/**
 * Crea una instancia de PHPMailer configurada con los datos de BD.
 */
function createMailerFromConfig(PDO $pdo): PHPMailer
{
    $cfg = loadEmailConfig($pdo);

    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = $cfg['smtp_host'];
    $mail->Port       = $cfg['smtp_port'];
    $mail->SMTPAuth   = true;
    $mail->Username   = $cfg['smtp_user'];
    $mail->Password   = $cfg['smtp_pass'];

    if ($cfg['smtp_secure'] === 'ssl') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($cfg['smtp_secure'] === 'tls') {
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $mail->SMTPSecure = false;
        $mail->SMTPAutoTLS = false;
    }

    $mail->CharSet = 'UTF-8';
    $mail->setFrom($cfg['from_email'] ?: $cfg['smtp_user'], $cfg['from_name'] ?: 'Sansouci Desk');

    return $mail;
}

/**
 * Helper sencillo para enviar un correo de soporte.
 */
function sendSupportMail(
    string $toEmail,
    string $subject,
    string $htmlBody,
    ?string $replyToEmail = null,
    ?string $replyToName  = null
): bool {
    global $pdo;

    try {
        $mail = createMailerFromConfig($pdo);

        $mail->clearAllRecipients();
        $mail->addAddress($toEmail);

        if ($replyToEmail) {
            $mail->addReplyTo($replyToEmail, $replyToName ?: $replyToEmail);
        }

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        $mail->AltBody = strip_tags($htmlBody);

        return $mail->send();
    } catch (Exception $e) {
        return false;
    }
}
