<?php
namespace API\Core;

/**
 * Chargeur de variables d'environnement depuis .env
 */
class EnvLoader
{
    protected $path;
    protected $loaded = false;

    public function __construct($path)
    {
        $this->path = $path;
    }

    /**
     * Charge le fichier .env
     */
    public function load()
    {
        if ($this->loaded) {
            return true;
        }

        $envFile = $this->path . '/.env';

        if (!file_exists($envFile)) {
            throw new \RuntimeException("Impossible de charger la configuration environnement. Le fichier .env est introuvable.");
        }

        if (!is_readable($envFile)) {
            throw new \RuntimeException("Impossible de charger la configuration environnement. Le fichier .env n'est pas accessible en lecture.");
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            // Ignorer les commentaires
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // Parser la ligne KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                
                $key = trim($key);
                $value = trim($value);

                // Supprimer les guillemets autour de la valeur
                $value = trim($value, '"\'');

                // Ne pas écraser les variables déjà définies
                if (!array_key_exists($key, $_ENV) && !getenv($key)) {
                    putenv("$key=$value");
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }

        $this->loaded = true;
        return true;
    }

    /**
     * Vérifie si les variables requises sont définies
     */
    public function validate(array $required)
    {
        $missing = [];

        foreach ($required as $key) {
            if (!getenv($key) && !isset($_ENV[$key])) {
                $missing[] = $key;
            }
        }

        if (!empty($missing)) {
            throw new \RuntimeException(
                "Impossible de charger la configuration environnement. Variables manquantes: " . 
                implode(', ', $missing)
            );
        }

        return true;
    }
}
