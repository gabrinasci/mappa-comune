<?php
require_once __DIR__ . '/impostazioni.php';
require_once __DIR__ . '/../vendor/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/src/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

/** @return array{ok: bool, errore: ?string} */
function inviaEmail(string $destinatarioEmail, string $destinatarioNome, string $oggetto, string $corpoHtml): array
{
    $host = impostazione('smtp_host');
    if (!$host) {
        return ['ok' => false, 'errore' => 'SMTP non configurato: vai su Impostazioni SMTP.'];
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = $host;
        $mail->Port = (int) impostazione('smtp_porta', '587');
        $mail->SMTPAuth = true;
        $mail->Username = impostazione('smtp_utente', '');
        $mail->Password = impostazione('smtp_password', '');

        $sicurezza = impostazione('smtp_sicurezza', 'tls');
        if ($sicurezza === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($sicurezza === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPAuth = (bool) impostazione('smtp_utente');
            $mail->SMTPSecure = false;
            $mail->SMTPAutoTLS = false;
        }

        $mail->CharSet = 'UTF-8';
        $mail->setFrom(
            impostazione('smtp_mittente_email', $mail->Username ?: 'noreply@example.it'),
            impostazione('smtp_mittente_nome', 'Mappa Servizi Comunali')
        );
        $mail->addAddress($destinatarioEmail, $destinatarioNome);
        $mail->isHTML(true);
        $mail->Subject = $oggetto;
        $mail->Body = $corpoHtml;
        $mail->AltBody = trim(strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $corpoHtml)));

        $mail->send();
        return ['ok' => true, 'errore' => null];
    } catch (PHPMailerException $e) {
        return ['ok' => false, 'errore' => $mail->ErrorInfo ?: $e->getMessage()];
    }
}
