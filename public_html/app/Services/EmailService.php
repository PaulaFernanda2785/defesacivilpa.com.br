<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../Libraries/PHPMailer/Exception.php';
require_once __DIR__ . '/../Libraries/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../Libraries/PHPMailer/SMTP.php';

require_once __DIR__ . '/../Core/Env.php';

class EmailService
{
    private PHPMailer $mail;

    public function __construct()
    {
        Env::loadFromCandidates([
            dirname(__DIR__, 3) . '/.env',
            dirname(__DIR__, 2) . '/.env',
        ]);

        $config = require __DIR__ . '/../config/email.php';

        $this->mail = new PHPMailer(true);
        $this->mail->CharSet = 'UTF-8';
        $this->mail->isSMTP();
        $this->mail->Host       = $config['host'];
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = $config['usuario'];
        $this->mail->Password   = $config['senha'];
        $this->mail->SMTPSecure = $config['secure'];
        $this->mail->Port       = (int)$config['port'];

        $this->mail->setFrom(
            $config['from_email'],
            $config['from_nome']
        );
    }

    public function enviar(
        string $para,
        string $assunto,
        string $texto,
        string $html = '',
        ?array $anexo = null
    ): bool {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();

            $this->mail->addAddress($para);
            $this->mail->Subject = $assunto;
            $this->mail->Body    = $html ?: nl2br($texto);
            $this->mail->AltBody = $texto;
            $this->mail->isHTML(true);

            if ($anexo) {
                $this->mail->addStringAttachment(
                    $anexo['bytes'],
                    $anexo['nome'],
                    'base64',
                    'application/pdf'
                );
            }

            return $this->mail->send();

        } catch (Exception $e) {
            error_log('[EMAIL] ' . $e->getMessage());
            return false;
        }
    }
}
