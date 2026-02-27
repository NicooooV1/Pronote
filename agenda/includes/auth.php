<?php
/**
 * Authentification pour le module Agenda
 * Charge l'API centralisée (toutes les fonctions auth viennent du Bridge)
 * + fonctions spécifiques de permissions agenda
 */
require_once __DIR__ . '/../../API/core.php';

// Fonctions spécifiques au module Agenda (permissions sur les événements)

if (!function_exists('canViewAllEvents')) {
    /**
     * Vérifier si l'utilisateur a le droit de consulter tous les événements
     */
    function canViewAllEvents() {
        return isAdmin() || isVieScolaire();
    }
}

if (!function_exists('canEditEvent')) {
    /**
     * Vérifier si l'utilisateur peut modifier un événement spécifique
     */
    function canEditEvent($evenement) {
        if (canViewAllEvents()) {
            return true;
        }
        if (isTeacher()) {
            return ($evenement['createur'] ?? '') === getUserFullName();
        }
        return false;
    }
}

if (!function_exists('canDeleteEvent')) {
    /**
     * Vérifier si l'utilisateur peut supprimer un événement spécifique
     */
    function canDeleteEvent($evenement) {
        return canEditEvent($evenement);
    }
}

if (!function_exists('canViewEvent')) {
    /**
     * Vérifier si l'utilisateur peut voir un événement spécifique
     */
    function canViewEvent($evenement) {
        if (canViewAllEvents()) {
            return true;
        }

        if (($evenement['createur'] ?? '') === getUserFullName()) {
            return true;
        }

        $role = getUserRole();
        switch ($evenement['visibilite'] ?? 'public') {
            case 'public':
                return true;
            case 'professeurs':
                return $role === 'professeur';
            case 'eleves':
                return $role === 'eleve';
            default:
                if (strpos($evenement['visibilite'] ?? '', 'classes:') === 0) {
                    return in_array($role, ['professeur', 'eleve']);
                }
                return false;
        }
    }
}

if (!function_exists('canManageAgendaEvents')) {
    function canManageAgendaEvents() {
        return isTeacher() || isAdmin() || isVieScolaire();
    }
}
?>