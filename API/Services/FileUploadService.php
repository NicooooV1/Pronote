<?php
/**
 * FileUploadService — Gestion centralisée des fichiers uploadés.
 *
 * Remplace les implémentations dupliquées :
 *   messagerie/core/uploader.php, cahierdetextes/includes/FileUploader.php,
 *   absences/includes/FileUploadHandler.php
 *
 * Validation MIME réelle (finfo), extension, taille.
 * Nommage sécurisé (random_bytes), sous-répertoires par module + date.
 *
 * Utilisation :
 *   require_once __DIR__ . '/../../API/core.php';
 *   $uploader = new \API\Services\FileUploadService('devoirs');
 *   $result   = $uploader->upload($fileArray);
 */

namespace API\Services;

class FileUploadService
{
    /**
     * Configurations prédéfinies par contexte (module).
     * max_size en octets, max_files = limite par requête.
     */
    public const CONTEXTS = [
        'messagerie' => [
            'max_size'      => 5 * 1024 * 1024,
            'max_files'     => 10,
            'allowed_types' => [
                'application/pdf',
                'image/jpeg', 'image/png', 'image/webp', 'image/gif',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'text/plain',
            ],
            'allowed_ext'   => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'doc', 'docx', 'txt'],
            'subdir'        => 'messagerie',
        ],
        'devoirs' => [
            'max_size'      => 10 * 1024 * 1024,
            'max_files'     => 5,
            'allowed_types' => [
                'application/pdf',
                'image/jpeg', 'image/png', 'image/webp',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain',
            ],
            'allowed_ext'   => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'txt'],
            'subdir'        => 'devoirs',
        ],
        'justificatifs' => [
            'max_size'      => 5 * 1024 * 1024,
            'max_files'     => 5,
            'allowed_types' => [
                'application/pdf',
                'image/jpeg', 'image/png', 'image/webp', 'image/gif',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
            'allowed_ext'   => ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'doc', 'docx'],
            'subdir'        => 'justificatifs',
        ],
    ];

    private string $baseDir;
    private array  $config;

    /**
     * @param string $context  Clé de CONTEXTS ('messagerie', 'devoirs', 'justificatifs')
     */
    public function __construct(string $context = 'messagerie')
    {
        $this->config  = self::CONTEXTS[$context] ?? self::CONTEXTS['messagerie'];
        $this->baseDir = rtrim(
            getenv('UPLOADS_PATH') ?: (defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 2) . '/') . 'uploads',
            '/'
        );
    }

    // ─── Upload unique ──────────────────────────────────────────────────────

    /**
     * Upload un fichier unique.
     *
     * @param  array $file  Élément de $_FILES (name, type, tmp_name, error, size)
     * @return array        ['success' => bool, ...métadonnées ou 'error' => string]
     */
    public function upload(array $file): array
    {
        // Erreur PHP
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => self::getUploadError($file['error'] ?? UPLOAD_ERR_NO_FILE)];
        }

        // Taille
        if ($file['size'] > $this->config['max_size']) {
            return ['success' => false, 'error' => 'Fichier trop volumineux (max ' . self::formatBytes($this->config['max_size']) . ')'];
        }

        // Extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $this->config['allowed_ext'], true)) {
            return ['success' => false, 'error' => "Extension non autorisée : .$ext"];
        }

        // MIME réel (finfo)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $this->config['allowed_types'], true)) {
            return ['success' => false, 'error' => "Type MIME non autorisé : $mime"];
        }

        // Nom sécurisé + sous-dossier daté
        $safeName = bin2hex(random_bytes(16)) . '.' . $ext;
        $subdir   = $this->config['subdir'] . '/' . date('Y/m');
        $fullDir  = $this->baseDir . '/' . $subdir;

        if (!is_dir($fullDir) && !mkdir($fullDir, 0755, true)) {
            return ['success' => false, 'error' => 'Impossible de créer le répertoire de destination'];
        }

        if (!move_uploaded_file($file['tmp_name'], $fullDir . '/' . $safeName)) {
            return ['success' => false, 'error' => 'Erreur lors du déplacement du fichier'];
        }

        return [
            'success'      => true,
            'nom_original' => $file['name'],
            'nom_stockage' => $safeName,
            'chemin'       => $subdir . '/' . $safeName,   // relatif à uploads/
            'type_mime'    => $mime,
            'taille'       => (int) $file['size'],
        ];
    }

    // ─── Upload multiple ────────────────────────────────────────────────────

    /**
     * Upload plusieurs fichiers (formulaire avec name="fichiers[]").
     *
     * @param  array $files  Tableau multi-fichier de $_FILES
     * @return array         Liste de résultats individuels
     */
    public function uploadMultiple(array $files): array
    {
        if (!isset($files['name']) || !is_array($files['name'])) {
            return [];
        }

        $results = [];
        $count   = min(count($files['name']), $this->config['max_files']);

        for ($i = 0; $i < $count; $i++) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $single = [
                'name'     => $files['name'][$i],
                'type'     => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error'    => $files['error'][$i],
                'size'     => $files['size'][$i],
            ];
            $results[] = $this->upload($single);
        }

        return $results;
    }

    // ─── Gestion des fichiers ───────────────────────────────────────────────

    /**
     * Supprime un fichier par son chemin relatif.
     */
    public function delete(string $relativePath): bool
    {
        $fullPath = $this->baseDir . '/' . $relativePath;
        $realPath = realpath($fullPath);
        $realBase = realpath($this->baseDir);

        if (!$realPath || !$realBase || strpos($realPath, $realBase) !== 0) {
            return false;
        }

        return is_file($realPath) && unlink($realPath);
    }

    /**
     * Chemin absolu d'un fichier à partir de son chemin relatif.
     */
    public function getFullPath(string $relativePath): string
    {
        return $this->baseDir . '/' . $relativePath;
    }

    /**
     * Vérifie si le fichier existe.
     */
    public function exists(string $relativePath): bool
    {
        return is_file($this->getFullPath($relativePath));
    }

    /**
     * Sert le fichier au navigateur (téléchargement sécurisé).
     * Termine le script.
     */
    public function serve(string $relativePath, ?string $displayName = null): void
    {
        $full = $this->getFullPath($relativePath);
        $real = realpath($full);
        $base = realpath($this->baseDir);

        if (!$real || !$base || strpos($real, $base) !== 0 || !is_file($real)) {
            http_response_code(404);
            exit('Fichier introuvable');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($real);
        $name  = $displayName ?: basename($relativePath);
        $inline = in_array($mime, ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf']);

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . filesize($real));
        header('Content-Disposition: ' . ($inline ? 'inline' : 'attachment') . '; filename="' . $name . '"');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');

        readfile($real);
        exit;
    }

    // ─── Utilitaires statiques ──────────────────────────────────────────────

    /**
     * Message d'erreur PHP pour upload.
     */
    public static function getUploadError(int $code): string
    {
        $messages = [
            UPLOAD_ERR_INI_SIZE   => 'Fichier trop volumineux (limite serveur)',
            UPLOAD_ERR_FORM_SIZE  => 'Fichier trop volumineux (limite formulaire)',
            UPLOAD_ERR_PARTIAL    => 'Téléchargement incomplet',
            UPLOAD_ERR_NO_FILE    => 'Aucun fichier envoyé',
            UPLOAD_ERR_NO_TMP_DIR => 'Répertoire temporaire manquant',
            UPLOAD_ERR_CANT_WRITE => 'Impossible d\'écrire sur le disque',
            UPLOAD_ERR_EXTENSION  => 'Extension PHP a bloqué l\'upload',
        ];
        return $messages[$code] ?? 'Erreur de téléchargement inconnue';
    }

    /**
     * Taille lisible.
     */
    public static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) return $bytes . ' o';
        if ($bytes < 1048576) return round($bytes / 1024) . ' Ko';
        return round($bytes / 1048576, 1) . ' Mo';
    }

    /**
     * Icône Font Awesome selon le type MIME.
     */
    public static function getFileIcon(string $mime): string
    {
        if (strpos($mime, 'image/') === 0) return 'file-image';
        if (strpos($mime, 'pdf') !== false) return 'file-pdf';
        if (strpos($mime, 'word') !== false || strpos($mime, 'document') !== false) return 'file-word';
        if (strpos($mime, 'excel') !== false || strpos($mime, 'spreadsheet') !== false) return 'file-excel';
        if (strpos($mime, 'text/') === 0) return 'file-alt';
        return 'file';
    }

    /**
     * Retourne la configuration courante.
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Retourne le répertoire de base des uploads.
     */
    public function getBaseDir(): string
    {
        return $this->baseDir;
    }
}
