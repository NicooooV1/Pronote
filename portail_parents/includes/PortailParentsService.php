<?php
declare(strict_types=1);

namespace PortailParents;

use PDO;

/**
 * PortailParentsService — Portail Parents avancé.
 *
 * Vue consolidée enfant, e-signature documents, autorisations de sortie QR,
 * calendrier ICS, historique paiements et préférences.
 */
class PortailParentsService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── Vue consolidée ───────────────────────────────────────────

    public function getResumeEnfant(int $parentId, int $eleveId): array
    {
        $eleve = $this->pdo->prepare("SELECT id, nom, prenom, classe, date_naissance, photo FROM eleves WHERE id = :eid");
        $eleve->execute([':eid' => $eleveId]);
        $eleve = $eleve->fetch(PDO::FETCH_ASSOC);
        if (!$eleve) return [];

        // Dernières notes
        $notes = $this->pdo->prepare("SELECT n.note, n.note_sur, n.date_evaluation, m.nom AS matiere FROM notes n JOIN matieres m ON n.id_matiere = m.id WHERE n.id_eleve = :eid ORDER BY n.date_evaluation DESC LIMIT 10");
        $notes->execute([':eid' => $eleveId]);

        // Absences récentes
        $absences = $this->pdo->prepare("SELECT date_absence, motif, justifiee FROM absences WHERE id_eleve = :eid ORDER BY date_absence DESC LIMIT 10");
        $absences->execute([':eid' => $eleveId]);

        // Incidents
        $incidents = $this->pdo->prepare("SELECT type, description, date_incident FROM incidents WHERE eleve_id = :eid ORDER BY date_incident DESC LIMIT 5");
        $incidents->execute([':eid' => $eleveId]);

        // Emploi du temps du jour
        $edt = $this->pdo->prepare("SELECT e.heure_debut, e.heure_fin, m.nom AS matiere, e.salle, CONCAT(p.prenom,' ',p.nom) AS professeur FROM emploi_du_temps e JOIN matieres m ON e.id_matiere = m.id LEFT JOIN professeurs p ON e.id_professeur = p.id WHERE e.classe = :c AND e.jour = LOWER(DAYNAME(CURDATE())) ORDER BY e.heure_debut");
        $edt->execute([':c' => $eleve['classe']]);

        // Moyenne générale
        $moyenne = $this->pdo->prepare("SELECT ROUND(AVG(note),2) AS moyenne FROM notes WHERE id_eleve = :eid");
        $moyenne->execute([':eid' => $eleveId]);

        return [
            'eleve' => $eleve,
            'notes_recentes' => $notes->fetchAll(PDO::FETCH_ASSOC),
            'absences_recentes' => $absences->fetchAll(PDO::FETCH_ASSOC),
            'incidents_recents' => $incidents->fetchAll(PDO::FETCH_ASSOC),
            'emploi_du_temps_jour' => $edt->fetchAll(PDO::FETCH_ASSOC),
            'moyenne_generale' => $moyenne->fetchColumn()
        ];
    }

    public function getEnfants(int $parentId): array
    {
        $stmt = $this->pdo->prepare("SELECT e.id, e.nom, e.prenom, e.classe, e.photo FROM eleves e JOIN eleve_parents ep ON e.id = ep.eleve_id WHERE ep.parent_id = :pid AND e.actif = 1 ORDER BY e.prenom");
        $stmt->execute([':pid' => $parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Documents à signer ───────────────────────────────────────

    public function getDocumentsASigner(int $parentId, int $etabId): array
    {
        $stmt = $this->pdo->prepare("SELECT d.*, (SELECT COUNT(*) FROM portail_parents_signatures_doc s WHERE s.document_id = d.id AND s.parent_id = :pid) AS deja_signe FROM portail_parents_documents_a_signer d WHERE d.etablissement_id = :eid AND d.date_limite >= CURDATE() ORDER BY d.obligatoire DESC, d.date_limite ASC");
        $stmt->execute([':pid' => $parentId, ':eid' => $etabId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function signerDocument(int $documentId, int $parentId, int $eleveId, int $signatureId): void
    {
        $this->pdo->prepare("INSERT INTO portail_parents_signatures_doc (document_id, parent_id, eleve_id, signature_id, signe_le) VALUES (:did, :pid, :eid, :sid, NOW()) ON DUPLICATE KEY UPDATE signature_id = VALUES(signature_id), signe_le = NOW()")
            ->execute([':did' => $documentId, ':pid' => $parentId, ':eid' => $eleveId, ':sid' => $signatureId]);
    }

    // ─── Autorisations de sortie ──────────────────────────────────

    public function creerAutorisationSortie(int $etabId, int $parentId, int $eleveId, string $motif, string $dateDebut, string $dateFin): int
    {
        $token = bin2hex(random_bytes(16));
        $stmt = $this->pdo->prepare("INSERT INTO portail_parents_autorisations (etablissement_id, parent_id, eleve_id, type, motif, date_debut, date_fin, qr_token, statut) VALUES (:eid, :pid, :elid, 'sortie_anticipee', :m, :dd, :df, :t, 'demandee')");
        $stmt->execute([':eid' => $etabId, ':pid' => $parentId, ':elid' => $eleveId, ':m' => $motif, ':dd' => $dateDebut, ':df' => $dateFin, ':t' => $token]);
        return (int)$this->pdo->lastInsertId();
    }

    public function genererQrSortie(int $autorisationId): ?string
    {
        $stmt = $this->pdo->prepare("SELECT qr_token, statut FROM portail_parents_autorisations WHERE id = :id");
        $stmt->execute([':id' => $autorisationId]);
        $auth = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$auth || $auth['statut'] !== 'approuvee') return null;
        return $auth['qr_token'];
    }

    public function validerQrSortie(string $qrToken): array
    {
        $stmt = $this->pdo->prepare("SELECT a.*, CONCAT(e.prenom,' ',e.nom) AS eleve_nom, e.classe FROM portail_parents_autorisations a JOIN eleves e ON a.eleve_id = e.id WHERE a.qr_token = :t AND a.statut = 'approuvee' AND a.date_debut <= NOW() AND a.date_fin >= NOW()");
        $stmt->execute([':t' => $qrToken]);
        $auth = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$auth) return ['valide' => false, 'raison' => 'Token invalide ou expiré'];

        $this->pdo->prepare("UPDATE portail_parents_autorisations SET statut = 'utilisee' WHERE id = :id")
            ->execute([':id' => $auth['id']]);

        return ['valide' => true, 'eleve' => $auth['eleve_nom'], 'classe' => $auth['classe'], 'motif' => $auth['motif']];
    }

    public function approuverAutorisation(int $autorisationId, int $approuvePar): void
    {
        $this->pdo->prepare("UPDATE portail_parents_autorisations SET statut = 'approuvee', approuve_par = :ap WHERE id = :id AND statut = 'demandee'")
            ->execute([':ap' => $approuvePar, ':id' => $autorisationId]);
    }

    public function getAutorisations(int $parentId, ?int $eleveId = null): array
    {
        $sql = "SELECT a.*, CONCAT(e.prenom,' ',e.nom) AS eleve_nom FROM portail_parents_autorisations a JOIN eleves e ON a.eleve_id = e.id WHERE a.parent_id = :pid";
        $params = [':pid' => $parentId];
        if ($eleveId) { $sql .= " AND a.eleve_id = :eid"; $params[':eid'] = $eleveId; }
        $sql .= " ORDER BY a.created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Calendrier ICS ───────────────────────────────────────────

    public function getCalendrierIcs(int $parentId, int $eleveId): string
    {
        $eleve = $this->pdo->prepare("SELECT nom, prenom, classe FROM eleves WHERE id = :eid");
        $eleve->execute([':eid' => $eleveId]);
        $eleve = $eleve->fetch(PDO::FETCH_ASSOC);

        $ics = "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//Fronote//Portail Parents//FR\r\nCALSCALE:GREGORIAN\r\nX-WR-CALNAME:Fronote - {$eleve['prenom']} {$eleve['nom']}\r\n";

        // Événements agenda
        $events = $this->pdo->prepare("SELECT titre, description, date_debut, date_fin, lieu FROM agenda WHERE (cible_type = 'classe' AND cible_id = :c) OR cible_type = 'tous' ORDER BY date_debut");
        $events->execute([':c' => $eleve['classe']]);
        foreach ($events as $ev) {
            $uid = md5($ev['titre'] . $ev['date_debut']);
            $dtStart = date('Ymd\THis', strtotime($ev['date_debut']));
            $dtEnd = $ev['date_fin'] ? date('Ymd\THis', strtotime($ev['date_fin'])) : $dtStart;
            $ics .= "BEGIN:VEVENT\r\nUID:{$uid}@fronote\r\nDTSTART:{$dtStart}\r\nDTEND:{$dtEnd}\r\nSUMMARY:{$ev['titre']}\r\nDESCRIPTION:{$ev['description']}\r\nLOCATION:{$ev['lieu']}\r\nEND:VEVENT\r\n";
        }

        // Réunions parents
        $reunions = $this->pdo->prepare("SELECT titre, date_reunion, lieu FROM reunions WHERE classe = :c AND date_reunion >= CURDATE() ORDER BY date_reunion");
        $reunions->execute([':c' => $eleve['classe']]);
        foreach ($reunions as $r) {
            $uid = md5('reunion' . $r['titre'] . $r['date_reunion']);
            $dt = date('Ymd\THis', strtotime($r['date_reunion']));
            $ics .= "BEGIN:VEVENT\r\nUID:{$uid}@fronote\r\nDTSTART:{$dt}\r\nSUMMARY:Réunion: {$r['titre']}\r\nLOCATION:{$r['lieu']}\r\nEND:VEVENT\r\n";
        }

        $ics .= "END:VCALENDAR\r\n";
        return $ics;
    }

    // ─── Historique paiements ─────────────────────────────────────

    public function getHistoriquePaiements(int $parentId): array
    {
        $stmt = $this->pdo->prepare("SELECT f.id, f.reference, f.montant, f.statut, f.date_emission, f.date_echeance, f.description FROM factures f WHERE f.parent_id = :pid ORDER BY f.date_emission DESC");
        $stmt->execute([':pid' => $parentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Préférences ──────────────────────────────────────────────

    public function getPreferences(int $parentId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM portail_parents_preferences WHERE parent_id = :pid");
        $stmt->execute([':pid' => $parentId]);
        $prefs = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$prefs) {
            return ['notifications_resume_quotidien' => 1, 'notifications_notes' => 1, 'notifications_absences' => 1, 'langue' => 'fr'];
        }
        return $prefs;
    }

    public function updatePreferences(int $parentId, array $prefs): void
    {
        $this->pdo->prepare("INSERT INTO portail_parents_preferences (parent_id, notifications_resume_quotidien, notifications_notes, notifications_absences, langue) VALUES (:pid, :nr, :nn, :na, :l) ON DUPLICATE KEY UPDATE notifications_resume_quotidien=VALUES(notifications_resume_quotidien), notifications_notes=VALUES(notifications_notes), notifications_absences=VALUES(notifications_absences), langue=VALUES(langue)")
            ->execute([
                ':pid' => $parentId,
                ':nr' => $prefs['notifications_resume_quotidien'] ?? 1,
                ':nn' => $prefs['notifications_notes'] ?? 1,
                ':na' => $prefs['notifications_absences'] ?? 1,
                ':l' => $prefs['langue'] ?? 'fr'
            ]);
    }
}
