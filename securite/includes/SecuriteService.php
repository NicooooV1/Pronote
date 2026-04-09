<?php
declare(strict_types=1);

namespace Securite;

use PDO;

/**
 * SecuriteService — Sécurité & Plans d'Urgence.
 *
 * PPMS, exercices évacuation avec timer/check zones, registre dangers,
 * alertes urgence (WebPush+SMS), niveaux Vigipirate, contacts urgence.
 */
class SecuriteService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── Plans (PPMS) ─────────────────────────────────────────────

    public function creerPlan(int $etabId, string $titre, string $type, string $contenu, string $procedugesJson = '[]', string $responsable = ''): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO securite_plans (etablissement_id, titre, type, contenu, procedures, responsable, statut) VALUES (:eid, :t, :ty, :c, :p, :r, 'actif')");
        $stmt->execute([':eid' => $etabId, ':t' => $titre, ':ty' => $type, ':c' => $contenu, ':p' => $procedugesJson, ':r' => $responsable]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getPlans(int $etabId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM securite_plans WHERE etablissement_id = :eid AND statut = 'actif' ORDER BY type, titre");
        $stmt->execute([':eid' => $etabId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updatePlan(int $planId, string $contenu, string $proceduresJson): void
    {
        $this->pdo->prepare("UPDATE securite_plans SET contenu = :c, procedures = :p, updated_at = NOW() WHERE id = :id")
            ->execute([':c' => $contenu, ':p' => $proceduresJson, ':id' => $planId]);
    }

    // ─── Exercices ────────────────────────────────────────────────

    public function planifierExercice(int $etabId, int $planId, string $type, string $datePrevue, string $description = ''): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO securite_exercices (etablissement_id, plan_id, type_exercice, date_prevue, description, statut) VALUES (:eid, :pid, :t, :d, :desc, 'planifie')");
        $stmt->execute([':eid' => $etabId, ':pid' => $planId, ':t' => $type, ':d' => $datePrevue, ':desc' => $description]);
        return (int)$this->pdo->lastInsertId();
    }

    public function demarrerExercice(int $exerciceId): void
    {
        $this->pdo->prepare("UPDATE securite_exercices SET statut = 'en_cours', heure_debut = NOW() WHERE id = :id AND statut = 'planifie'")
            ->execute([':id' => $exerciceId]);
    }

    public function securiserZone(int $exerciceId, int $zoneId, string $observations = '', int $manquants = 0): void
    {
        $this->pdo->prepare("UPDATE securite_zones SET securisee = 1, observations = :o, nb_manquants = :m, heure_securisation = NOW() WHERE id = :zid AND exercice_id = :eid")
            ->execute([':o' => $observations, ':m' => $manquants, ':zid' => $zoneId, ':eid' => $exerciceId]);
    }

    public function terminerExercice(int $exerciceId, string $bilanGlobal = '', int $nbEvacues = 0): void
    {
        $this->pdo->prepare("UPDATE securite_exercices SET statut = 'termine', heure_fin = NOW(), bilan_global = :b, nb_evacues = :n WHERE id = :id AND statut = 'en_cours'")
            ->execute([':b' => $bilanGlobal, ':n' => $nbEvacues, ':id' => $exerciceId]);
    }

    public function getExercices(int $etabId, ?string $annee = null): array
    {
        $sql = "SELECT ex.*, p.titre AS plan_titre, p.type AS plan_type FROM securite_exercices ex LEFT JOIN securite_plans p ON ex.plan_id = p.id WHERE ex.etablissement_id = :eid";
        $params = [':eid' => $etabId];
        if ($annee) { $sql .= " AND YEAR(ex.date_prevue) = :a"; $params[':a'] = $annee; }
        $sql .= " ORDER BY ex.date_prevue DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function genererRapportExercice(int $exerciceId): array
    {
        $exercice = $this->pdo->prepare("SELECT ex.*, p.titre AS plan_titre FROM securite_exercices ex LEFT JOIN securite_plans p ON ex.plan_id = p.id WHERE ex.id = :id");
        $exercice->execute([':id' => $exerciceId]);
        $ex = $exercice->fetch(PDO::FETCH_ASSOC);

        $zones = $this->pdo->prepare("SELECT * FROM securite_zones WHERE exercice_id = :eid ORDER BY nom");
        $zones->execute([':eid' => $exerciceId]);

        $tempsTotal = null;
        if ($ex['heure_debut'] && $ex['heure_fin']) {
            $debut = strtotime($ex['heure_debut']);
            $fin = strtotime($ex['heure_fin']);
            $tempsTotal = $fin - $debut; // secondes
        }

        return [
            'exercice' => $ex,
            'zones' => $zones->fetchAll(PDO::FETCH_ASSOC),
            'temps_total_secondes' => $tempsTotal,
            'temps_total_minutes' => $tempsTotal ? round($tempsTotal / 60, 1) : null
        ];
    }

    // ─── Zones ────────────────────────────────────────────────────

    public function creerZonesExercice(int $exerciceId, array $nomsZones): void
    {
        $stmt = $this->pdo->prepare("INSERT INTO securite_zones (exercice_id, nom, securisee) VALUES (:eid, :nom, 0)");
        foreach ($nomsZones as $nom) {
            $stmt->execute([':eid' => $exerciceId, ':nom' => $nom]);
        }
    }

    // ─── Registre dangers ─────────────────────────────────────────

    public function signalerDanger(int $etabId, int $signalePar, string $signaleParType, string $lieu, string $description, string $gravite = 'moyen'): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO securite_incidents_registre (etablissement_id, signale_par, signale_par_type, lieu, description, gravite, statut) VALUES (:eid, :sp, :spt, :l, :d, :g, 'signale')");
        $stmt->execute([':eid' => $etabId, ':sp' => $signalePar, ':spt' => $signaleParType, ':l' => $lieu, ':d' => $description, ':g' => $gravite]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getRegistreDangers(int $etabId, ?string $statut = null): array
    {
        $sql = "SELECT * FROM securite_incidents_registre WHERE etablissement_id = :eid";
        $params = [':eid' => $etabId];
        if ($statut) { $sql .= " AND statut = :s"; $params[':s'] = $statut; }
        $sql .= " ORDER BY gravite DESC, created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function traiterDanger(int $dangerId, string $action, int $traitePar): void
    {
        $this->pdo->prepare("UPDATE securite_incidents_registre SET statut = 'traite', action_corrective = :a, traite_par = :tp, date_traitement = NOW() WHERE id = :id")
            ->execute([':a' => $action, ':tp' => $traitePar, ':id' => $dangerId]);
    }

    // ─── Alertes urgence ──────────────────────────────────────────

    public function envoyerAlerteUrgence(int $etabId, string $type, string $message, string $instructions): array
    {
        // Log l'alerte
        $this->pdo->prepare("INSERT INTO securite_incidents_registre (etablissement_id, lieu, description, gravite, statut) VALUES (:eid, 'Établissement', :d, 'critique', 'en_cours')")
            ->execute([':eid' => $etabId, ':d' => "ALERTE URGENCE [{$type}]: {$message}"]);

        // Retourne les données pour envoi via WebPush/SMS (appelant gère l'envoi)
        return [
            'type' => $type,
            'message' => $message,
            'instructions' => $instructions,
            'timestamp' => date('Y-m-d H:i:s'),
            'etablissement_id' => $etabId
        ];
    }

    // ─── Vigipirate ───────────────────────────────────────────────

    public function setNiveauVigipirate(int $etabId, string $niveau, string $mesures = '', int $definePar = 0): void
    {
        $this->pdo->prepare("INSERT INTO securite_vigipirate (etablissement_id, niveau, mesures, defini_par) VALUES (:eid, :n, :m, :dp) ON DUPLICATE KEY UPDATE niveau=VALUES(niveau), mesures=VALUES(mesures), defini_par=VALUES(defini_par), updated_at=NOW()")
            ->execute([':eid' => $etabId, ':n' => $niveau, ':m' => $mesures, ':dp' => $definePar]);
    }

    public function getNiveauVigipirate(int $etabId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM securite_vigipirate WHERE etablissement_id = :eid ORDER BY updated_at DESC LIMIT 1");
        $stmt->execute([':eid' => $etabId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    // ─── Contacts urgence ─────────────────────────────────────────

    public function getContactsUrgence(int $etabId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM securite_contacts_urgence WHERE etablissement_id = :eid ORDER BY priorite ASC, nom");
        $stmt->execute([':eid' => $etabId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ajouterContactUrgence(int $etabId, string $nom, string $fonction, string $telephone, string $email = '', int $priorite = 10): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO securite_contacts_urgence (etablissement_id, nom, fonction, telephone, email, priorite) VALUES (:eid, :n, :f, :t, :e, :p)");
        $stmt->execute([':eid' => $etabId, ':n' => $nom, ':f' => $fonction, ':t' => $telephone, ':e' => $email, ':p' => $priorite]);
        return (int)$this->pdo->lastInsertId();
    }
}
