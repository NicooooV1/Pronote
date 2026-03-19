<?php
/**
 * Protection CSRF pour la messagerie
 *
 * Ce fichier charge l'implémentation CSRF centralisée de l'API.
 * Toutes les fonctions (csrf_token, csrf_field, csrf_meta, csrf_validate,
 * csrf_verify, isAjaxRequest) sont définies dans API/core.php et délèguent
 * à API/Security/CSRF.php (pattern Token Bucket).
 *
 * Ce fichier est conservé pour rétro-compatibilité des includes existants.
 */

require_once __DIR__ . '/../../API/core.php';
