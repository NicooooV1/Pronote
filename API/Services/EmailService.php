<?php
declare(strict_types=1);

namespace API\Services;

use PDO;

/**
 * Service d'envoi d'emails via SMTP
 * 
 * Utilise les sockets PHP natifs (pas de dépendance externe).
 * Configuration stockée en BDD (table smtp_config).
 * Fallback sur mail() si SMTP non configuré.
 */
class EmailService
{
    private PDO $pdo;
    private ?array $config = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── Configuration ───────────────────────────────────────────────────

    /**
     * Charge la configuration SMTP depuis la BDD
     */
    public function getConfig(): array
    {
        if ($this->config === null) {
            try {
                $stmt = $this->pdo->query("SELECT * FROM smtp_config WHERE id = 1 LIMIT 1");
                $this->config = $stmt->fetch(PDO::FETCH_ASSOC) ?: $this->defaultConfig();
            } catch (\PDOException $e) {
                $this->config = $this->defaultConfig();
            }
        }
        return $this->config;
    }

    /**
     * Met à jour la configuration SMTP
     */
    public function updateConfig(array $data): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE smtp_config SET
                    host = ?,
                    port = ?,
                    username = ?,
                    password = ?,
                    encryption = ?,
                    from_address = ?,
                    from_name = ?,
                    reply_to = ?,
                    enabled = ?
                WHERE id = 1
            ");
            $result = $stmt->execute([
                $data['host'] ?? '',
                (int)($data['port'] ?? 587),
                $data['username'] ?? '',
                $data['password'] ?? '',
                $data['encryption'] ?? 'tls',
                $data['from_address'] ?? '',
                $data['from_name'] ?? '',
                $data['reply_to'] ?? null,
                (int)($data['enabled'] ?? 0),
            ]);
            $this->config = null; // Reset cache
            return $result;
        } catch (\PDOException $e) {
            error_log("EmailService::updateConfig error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Teste la connexion SMTP
     * @return array ['success' => bool, 'message' => string]
     */
    public function testConnection(): array
    {
        $cfg = $this->getConfig();
        if (empty($cfg['host'])) {
            return ['success' => false, 'message' => 'Aucun serveur SMTP configuré'];
        }

        try {
            $socket = $this->smtpConnect($cfg);
            $this->smtpCommand($socket, "EHLO " . gethostname());
            $this->smtpCommand($socket, "QUIT");
            fclose($socket);
            return ['success' => true, 'message' => 'Connexion SMTP réussie vers ' . $cfg['host'] . ':' . $cfg['port']];
        } catch (\RuntimeException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    // ─── Envoi ───────────────────────────────────────────────────────────

    /**
     * Vérifie si le service est activé et configuré
     */
    public function isEnabled(): bool
    {
        $cfg = $this->getConfig();
        return !empty($cfg['enabled']) && !empty($cfg['host']);
    }

    /**
     * Envoie un email
     * 
     * @param string|array $to       Destinataire(s)
     * @param string       $subject  Sujet
     * @param string       $bodyHtml Corps HTML
     * @param string       $bodyText Corps texte brut (optionnel, auto-généré si vide)
     * @param array        $options  Options supplémentaires: cc, bcc, reply_to, attachments
     * @return array ['success' => bool, 'message' => string]
     */
    public function send($to, string $subject, string $bodyHtml, string $bodyText = '', array $options = []): array
    {
        $cfg = $this->getConfig();

        if (empty($cfg['enabled']) || empty($cfg['host'])) {
            // Fallback: essayer mail() natif PHP
            return $this->sendViaMail($to, $subject, $bodyHtml, $bodyText);
        }

        return $this->sendViaSmtp($to, $subject, $bodyHtml, $bodyText, $options);
    }

    /**
     * Envoie un email à partir d'un template
     */
    public function sendTemplate($to, string $subject, string $templateName, array $variables = [], array $options = []): array
    {
        $html = $this->renderTemplate($templateName, $variables);
        return $this->send($to, $subject, $html, '', $options);
    }

    // ─── Templates ───────────────────────────────────────────────────────

    /**
     * Rend un template email avec des variables
     */
    public function renderTemplate(string $name, array $vars = []): string
    {
        $templates = [
            'welcome' => $this->templateWelcome(),
            'reset_password' => $this->templateResetPassword(),
            'notification' => $this->templateNotification(),
            'absence' => $this->templateAbsence(),
            'bulletin_ready' => $this->templateBulletinReady(),
            'reunion_invite' => $this->templateReunionInvite(),
            'generic' => $this->templateGeneric(),
        ];

        $html = $templates[$name] ?? $templates['generic'];

        // Remplacer les variables {{key}}
        foreach ($vars as $key => $value) {
            $html = str_replace('{{' . $key . '}}', htmlspecialchars((string)$value), $html);
        }

        // Wrapper dans le layout email
        return $this->wrapInLayout($html, $vars['title'] ?? $name);
    }

    // ─── Implémentation SMTP ─────────────────────────────────────────────

    private function sendViaSmtp($to, string $subject, string $bodyHtml, string $bodyText, array $options): array
    {
        $cfg = $this->getConfig();

        if (empty($bodyText)) {
            $bodyText = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $bodyHtml));
        }

        $recipients = is_array($to) ? $to : [$to];
        $boundary = '----=_Part_' . md5(uniqid((string)mt_rand(), true));
        $fromAddr = $cfg['from_address'] ?: 'noreply@fronote.local';
        $fromName = $cfg['from_name'] ?: 'Fronote';

        // Construire les headers
        $headers = "From: {$fromName} <{$fromAddr}>\r\n";
        $headers .= "To: " . implode(', ', $recipients) . "\r\n";
        if (!empty($options['cc'])) {
            $headers .= "Cc: " . (is_array($options['cc']) ? implode(', ', $options['cc']) : $options['cc']) . "\r\n";
        }
        $replyTo = $options['reply_to'] ?? $cfg['reply_to'] ?? $fromAddr;
        $headers .= "Reply-To: {$replyTo}\r\n";
        $headers .= "Subject: {$subject}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $headers .= "X-Mailer: Fronote/1.0\r\n";
        $headers .= "Date: " . date('r') . "\r\n";

        // Corps multipart
        $body = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($bodyText) . "\r\n\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
        $body .= quoted_printable_encode($bodyHtml) . "\r\n\r\n";
        $body .= "--{$boundary}--\r\n";

        try {
            $socket = $this->smtpConnect($cfg);
            $this->smtpCommand($socket, "EHLO " . gethostname());

            // Authentification
            if (!empty($cfg['username'])) {
                $this->smtpCommand($socket, "AUTH LOGIN");
                $this->smtpCommand($socket, base64_encode($cfg['username']));
                $this->smtpCommand($socket, base64_encode($cfg['password']));
            }

            $this->smtpCommand($socket, "MAIL FROM:<{$fromAddr}>");

            foreach ($recipients as $rcpt) {
                $this->smtpCommand($socket, "RCPT TO:<{$rcpt}>");
            }
            // BCC recipients
            if (!empty($options['bcc'])) {
                $bcc = is_array($options['bcc']) ? $options['bcc'] : [$options['bcc']];
                foreach ($bcc as $bccAddr) {
                    $this->smtpCommand($socket, "RCPT TO:<{$bccAddr}>");
                }
            }

            $this->smtpCommand($socket, "DATA");
            fwrite($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
            $this->smtpReadResponse($socket);

            $this->smtpCommand($socket, "QUIT");
            fclose($socket);

            return ['success' => true, 'message' => 'Email envoyé avec succès'];
        } catch (\RuntimeException $e) {
            error_log("EmailService SMTP error: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur SMTP: ' . $e->getMessage()];
        }
    }

    /**
     * Fallback via mail() natif PHP
     */
    private function sendViaMail($to, string $subject, string $bodyHtml, string $bodyText): array
    {
        $recipients = is_array($to) ? implode(', ', $to) : $to;
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "X-Mailer: Fronote/1.0\r\n";

        $result = @mail($recipients, $subject, $bodyHtml, $headers);
        return [
            'success' => $result,
            'message' => $result ? 'Email envoyé via mail()' : 'Échec mail() — vérifiez la configuration PHP sendmail',
        ];
    }

    /**
     * Connexion socket SMTP
     */
    private function smtpConnect(array $cfg)
    {
        $host = $cfg['host'];
        $port = (int)$cfg['port'];
        $encryption = $cfg['encryption'] ?? 'tls';

        $prefix = '';
        if ($encryption === 'ssl') {
            $prefix = 'ssl://';
        }

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ]);

        $socket = @stream_socket_client(
            "{$prefix}{$host}:{$port}",
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$socket) {
            throw new \RuntimeException("Impossible de se connecter à {$host}:{$port} — {$errstr} ({$errno})");
        }

        $this->smtpReadResponse($socket);

        // STARTTLS pour le chiffrement TLS
        if ($encryption === 'tls') {
            $this->smtpCommand($socket, "EHLO " . gethostname());
            $this->smtpCommand($socket, "STARTTLS");
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException("Échec STARTTLS sur {$host}:{$port}");
            }
        }

        return $socket;
    }

    private function smtpCommand($socket, string $cmd): string
    {
        fwrite($socket, $cmd . "\r\n");
        return $this->smtpReadResponse($socket);
    }

    private function smtpReadResponse($socket): string
    {
        $response = '';
        while ($line = fgets($socket, 512)) {
            $response .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        $code = (int)substr($response, 0, 3);
        if ($code >= 400) {
            throw new \RuntimeException("SMTP erreur {$code}: " . trim($response));
        }
        return $response;
    }

    // ─── Templates HTML ──────────────────────────────────────────────────

    private function wrapInLayout(string $content, string $title): string
    {
        // Charger le nom de l'établissement
        $etabNom = 'Fronote';
        try {
            $stmt = $this->pdo->query("SELECT nom FROM etablissement_info LIMIT 1");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) $etabNom = $row['nom'];
        } catch (\PDOException $e) {}

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width"><title>{$title}</title></head>
<body style="margin:0;padding:0;background:#f4f4f7;font-family:Arial,Helvetica,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f7;padding:20px 0">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.08)">
    <tr><td style="background:linear-gradient(135deg,#667eea,#764ba2);padding:24px;text-align:center">
        <h1 style="margin:0;color:#fff;font-size:22px">{$etabNom}</h1>
        <p style="margin:4px 0 0;color:rgba(255,255,255,.8);font-size:13px">Powered by Fronote</p>
    </td></tr>
    <tr><td style="padding:30px 32px">{$content}</td></tr>
    <tr><td style="background:#f8f9fa;padding:16px 32px;text-align:center;font-size:12px;color:#999">
        <p style="margin:0">&copy; {$etabNom} — <a href="#" style="color:#667eea">Fronote</a></p>
        <p style="margin:4px 0 0">Ceci est un email automatique, merci de ne pas y répondre directement.</p>
    </td></tr>
</table>
</td></tr></table>
</body></html>
HTML;
    }

    private function templateWelcome(): string
    {
        return '<h2 style="color:#333;margin:0 0 16px">Bienvenue sur Fronote !</h2>
<p>Bonjour <strong>{{prenom}} {{nom}}</strong>,</p>
<p>Votre compte a été créé avec succès. Voici vos informations de connexion :</p>
<div style="background:#f0f4ff;border-left:4px solid #667eea;padding:12px 16px;margin:16px 0;border-radius:4px">
    <p style="margin:4px 0"><strong>Identifiant :</strong> {{identifiant}}</p>
    <p style="margin:4px 0"><strong>Mot de passe :</strong> {{mot_de_passe}}</p>
    <p style="margin:4px 0"><strong>Profil :</strong> {{profil}}</p>
</div>
<p style="color:#e53e3e;font-size:13px">⚠️ Changez votre mot de passe lors de votre première connexion.</p>
<p style="text-align:center;margin-top:24px"><a href="{{login_url}}" style="display:inline-block;padding:12px 32px;background:#667eea;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold">Se connecter</a></p>';
    }

    private function templateResetPassword(): string
    {
        return '<h2 style="color:#333;margin:0 0 16px">Réinitialisation du mot de passe</h2>
<p>Bonjour <strong>{{prenom}} {{nom}}</strong>,</p>
<p>Une demande de réinitialisation a été effectuée pour votre compte. Votre nouveau mot de passe temporaire est :</p>
<div style="background:#fff5f5;border-left:4px solid #e53e3e;padding:12px 16px;margin:16px 0;border-radius:4px;text-align:center">
    <p style="font-size:18px;font-weight:bold;letter-spacing:2px;margin:0">{{nouveau_mdp}}</p>
</div>
<p>Changez-le dès votre prochaine connexion.</p>
<p style="font-size:12px;color:#999">Si vous n\'êtes pas à l\'origine de cette demande, contactez immédiatement l\'administration.</p>';
    }

    private function templateNotification(): string
    {
        return '<h2 style="color:#333;margin:0 0 16px">{{titre}}</h2>
<p>Bonjour <strong>{{prenom}}</strong>,</p>
<div style="background:#f8f9fa;padding:16px;border-radius:6px;margin:16px 0">{{{contenu}}}</div>
<p style="text-align:center;margin-top:24px"><a href="{{lien}}" style="display:inline-block;padding:10px 28px;background:#667eea;color:#fff;text-decoration:none;border-radius:6px">Voir les détails</a></p>';
    }

    private function templateAbsence(): string
    {
        return '<h2 style="color:#333;margin:0 0 16px">Signalement d\'absence</h2>
<p>Bonjour,</p>
<p>Nous vous informons de l\'absence de <strong>{{eleve_nom}}</strong> :</p>
<div style="background:#fff8e1;border-left:4px solid #ed8936;padding:12px 16px;margin:16px 0;border-radius:4px">
    <p style="margin:4px 0"><strong>Date :</strong> {{date_absence}}</p>
    <p style="margin:4px 0"><strong>Motif :</strong> {{motif}}</p>
    <p style="margin:4px 0"><strong>Statut :</strong> {{statut}}</p>
</div>
<p>Merci de fournir un justificatif si ce n\'est pas déjà fait.</p>';
    }

    private function templateBulletinReady(): string
    {
        return '<h2 style="color:#333;margin:0 0 16px">Bulletin disponible</h2>
<p>Bonjour <strong>{{prenom}}</strong>,</p>
<p>Le bulletin de <strong>{{eleve_nom}}</strong> pour la période <strong>{{periode}}</strong> est désormais disponible.</p>
<p style="text-align:center;margin-top:24px"><a href="{{lien}}" style="display:inline-block;padding:12px 32px;background:#48bb78;color:#fff;text-decoration:none;border-radius:6px;font-weight:bold">Consulter le bulletin</a></p>';
    }

    private function templateReunionInvite(): string
    {
        return '<h2 style="color:#333;margin:0 0 16px">Invitation à une réunion</h2>
<p>Bonjour <strong>{{prenom}}</strong>,</p>
<p>Vous êtes invité(e) à la réunion suivante :</p>
<div style="background:#f0f4ff;border-left:4px solid #667eea;padding:12px 16px;margin:16px 0;border-radius:4px">
    <p style="margin:4px 0"><strong>Objet :</strong> {{objet}}</p>
    <p style="margin:4px 0"><strong>Date :</strong> {{date_reunion}}</p>
    <p style="margin:4px 0"><strong>Heure :</strong> {{heure}}</p>
    <p style="margin:4px 0"><strong>Lieu :</strong> {{lieu}}</p>
</div>
<p style="text-align:center;margin-top:24px"><a href="{{lien}}" style="display:inline-block;padding:10px 28px;background:#667eea;color:#fff;text-decoration:none;border-radius:6px">Confirmer ma présence</a></p>';
    }

    private function templateGeneric(): string
    {
        return '<h2 style="color:#333;margin:0 0 16px">{{titre}}</h2>
<p>Bonjour <strong>{{prenom}}</strong>,</p>
<div style="margin:16px 0">{{contenu}}</div>';
    }

    private function defaultConfig(): array
    {
        return [
            'host' => '', 'port' => 587, 'username' => '', 'password' => '',
            'encryption' => 'tls', 'from_address' => '', 'from_name' => '',
            'reply_to' => null, 'enabled' => 0,
        ];
    }
}
