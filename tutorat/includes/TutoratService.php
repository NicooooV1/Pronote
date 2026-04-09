<?php
declare(strict_types=1);

namespace Tutorat;

use PDO;

/**
 * TutoratService — Tutorat & Entraide entre élèves.
 *
 * Matching algorithmique (top/bottom quartile), planning séances,
 * gamification (badges, XP, leaderboard), suivi progression.
 */
class TutoratService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── Matching ─────────────────────────────────────────────────

    public function autoMatch(int $etabId, int $matiereId, string $classe): array
    {
        // Récupérer moyennes par élève pour la matière
        $stmt = $this->pdo->prepare("SELECT e.id, e.nom, e.prenom, ROUND(AVG(n.note),2) AS moyenne FROM eleves e JOIN notes n ON n.id_eleve = e.id WHERE e.classe = :c AND n.id_matiere = :m AND e.actif = 1 GROUP BY e.id ORDER BY moyenne DESC");
        $stmt->execute([':c' => $classe, ':m' => $matiereId]);
        $eleves = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($eleves) < 4) return [];

        $total = count($eleves);
        $q1 = (int)ceil($total * 0.25);
        $topQuartile = array_slice($eleves, 0, $q1);
        $bottomQuartile = array_slice($eleves, -$q1);

        $pairs = [];
        $maxPairs = min(count($topQuartile), count($bottomQuartile));
        for ($i = 0; $i < $maxPairs; $i++) {
            $pairs[] = [
                'tuteur' => $topQuartile[$i],
                'tutore' => $bottomQuartile[$i],
                'matiere_id' => $matiereId
            ];
        }

        return $pairs;
    }

    public function proposerPair(int $etabId, int $tuteurId, int $tutoreId, int $matiereId, int $validePar): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO tutorat_pairs (etablissement_id, tuteur_id, tutore_id, matiere_id, statut, valide_par) VALUES (:eid, :tid, :toid, :mid, 'actif', :vp)");
        $stmt->execute([':eid' => $etabId, ':tid' => $tuteurId, ':toid' => $tutoreId, ':mid' => $matiereId, ':vp' => $validePar]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getPairs(int $etabId, ?string $classe = null): array
    {
        $sql = "SELECT p.*, CONCAT(t1.prenom,' ',t1.nom) AS tuteur_nom, CONCAT(t2.prenom,' ',t2.nom) AS tutore_nom, m.nom AS matiere, t1.classe FROM tutorat_pairs p JOIN eleves t1 ON p.tuteur_id = t1.id JOIN eleves t2 ON p.tutore_id = t2.id JOIN matieres m ON p.matiere_id = m.id WHERE p.etablissement_id = :eid AND p.statut = 'actif'";
        $params = [':eid' => $etabId];
        if ($classe) { $sql .= " AND t1.classe = :c"; $params[':c'] = $classe; }
        $sql .= " ORDER BY t1.classe, m.nom";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Demandes ─────────────────────────────────────────────────

    public function creerDemande(int $eleveId, int $matiereId, string $description = ''): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO tutorat_demandes (eleve_id, matiere_id, description, statut) VALUES (:eid, :mid, :d, 'en_attente')");
        $stmt->execute([':eid' => $eleveId, ':mid' => $matiereId, ':d' => $description]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getDemandes(int $etabId, string $statut = 'en_attente'): array
    {
        $stmt = $this->pdo->prepare("SELECT d.*, CONCAT(e.prenom,' ',e.nom) AS eleve_nom, e.classe, m.nom AS matiere FROM tutorat_demandes d JOIN eleves e ON d.eleve_id = e.id JOIN matieres m ON d.matiere_id = m.id WHERE e.actif = 1 AND d.statut = :s ORDER BY d.created_at DESC");
        $stmt->execute([':s' => $statut]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function traiterDemande(int $demandeId, string $action, ?int $pairId = null): void
    {
        $statut = $action === 'accepter' ? 'acceptee' : 'refusee';
        $this->pdo->prepare("UPDATE tutorat_demandes SET statut = :s, pair_id = :pid WHERE id = :id")
            ->execute([':s' => $statut, ':pid' => $pairId, ':id' => $demandeId]);
    }

    // ─── Séances ──────────────────────────────────────────────────

    public function planifierSession(int $pairId, string $dateSession, string $heureDebut, string $heureFin, ?int $salleId = null, string $sujet = ''): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO tutorat_sessions (pair_id, date_session, heure_debut, heure_fin, salle_id, sujet, statut) VALUES (:pid, :d, :hd, :hf, :sid, :s, 'planifiee')");
        $stmt->execute([':pid' => $pairId, ':d' => $dateSession, ':hd' => $heureDebut, ':hf' => $heureFin, ':sid' => $salleId, ':s' => $sujet]);
        return (int)$this->pdo->lastInsertId();
    }

    public function redigerCompteRendu(int $sessionId, string $compteRendu, int $noteSatisfaction, string $difficultes = ''): void
    {
        $this->pdo->prepare("UPDATE tutorat_sessions SET compte_rendu = :cr, note_satisfaction = :ns, difficultes = :d, statut = 'terminee' WHERE id = :id")
            ->execute([':cr' => $compteRendu, ':ns' => $noteSatisfaction, ':d' => $difficultes, ':id' => $sessionId]);

        // Attribuer XP au tuteur
        $pair = $this->pdo->prepare("SELECT tuteur_id FROM tutorat_pairs p JOIN tutorat_sessions s ON s.pair_id = p.id WHERE s.id = :sid");
        $pair->execute([':sid' => $sessionId]);
        $tuteurId = $pair->fetchColumn();
        if ($tuteurId) {
            $this->ajouterXP($tuteurId, 10);
        }
    }

    public function getSessionsPair(int $pairId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM tutorat_sessions WHERE pair_id = :pid ORDER BY date_session DESC");
        $stmt->execute([':pid' => $pairId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Progression ──────────────────────────────────────────────

    public function calculerAmelioration(int $pairId): array
    {
        $pair = $this->pdo->prepare("SELECT tutore_id, matiere_id, created_at FROM tutorat_pairs WHERE id = :id");
        $pair->execute([':id' => $pairId]);
        $p = $pair->fetch(PDO::FETCH_ASSOC);
        if (!$p) return [];

        $avant = $this->pdo->prepare("SELECT ROUND(AVG(note),2) AS moyenne FROM notes WHERE id_eleve = :eid AND id_matiere = :mid AND date_evaluation < :d");
        $avant->execute([':eid' => $p['tutore_id'], ':mid' => $p['matiere_id'], ':d' => $p['created_at']]);

        $apres = $this->pdo->prepare("SELECT ROUND(AVG(note),2) AS moyenne FROM notes WHERE id_eleve = :eid AND id_matiere = :mid AND date_evaluation >= :d");
        $apres->execute([':eid' => $p['tutore_id'], ':mid' => $p['matiere_id'], ':d' => $p['created_at']]);

        $moyAvant = (float)($avant->fetchColumn() ?: 0);
        $moyApres = (float)($apres->fetchColumn() ?: 0);

        $nbSessions = $this->pdo->prepare("SELECT COUNT(*) FROM tutorat_sessions WHERE pair_id = :pid AND statut = 'terminee'");
        $nbSessions->execute([':pid' => $pairId]);

        return [
            'moyenne_avant' => $moyAvant,
            'moyenne_apres' => $moyApres,
            'progression' => round($moyApres - $moyAvant, 2),
            'nb_sessions' => (int)$nbSessions->fetchColumn()
        ];
    }

    // ─── Gamification ─────────────────────────────────────────────

    private function ajouterXP(int $eleveId, int $xp): void
    {
        $this->pdo->prepare("UPDATE tutorat_eleve_badges SET xp = xp + :xp WHERE eleve_id = :eid")
            ->execute([':xp' => $xp, ':eid' => $eleveId]);

        // Insert if not exists
        $exists = $this->pdo->prepare("SELECT COUNT(*) FROM tutorat_eleve_badges WHERE eleve_id = :eid");
        $exists->execute([':eid' => $eleveId]);
        if ($exists->fetchColumn() == 0) {
            $this->pdo->prepare("INSERT INTO tutorat_eleve_badges (eleve_id, xp) VALUES (:eid, :xp)")
                ->execute([':eid' => $eleveId, ':xp' => $xp]);
        }
    }

    public function attribuerBadge(int $eleveId, int $badgeId): void
    {
        $this->pdo->prepare("INSERT IGNORE INTO tutorat_eleve_badges (eleve_id, badge_id, date_obtention) VALUES (:eid, :bid, NOW())")
            ->execute([':eid' => $eleveId, ':bid' => $badgeId]);
    }

    public function getLeaderboard(int $etabId, int $limit = 20): array
    {
        $stmt = $this->pdo->prepare("SELECT eb.eleve_id, CONCAT(e.prenom,' ',e.nom) AS eleve, e.classe, eb.xp, (SELECT COUNT(*) FROM tutorat_sessions ts JOIN tutorat_pairs tp ON ts.pair_id = tp.id WHERE tp.tuteur_id = eb.eleve_id AND ts.statut = 'terminee') AS nb_sessions FROM tutorat_eleve_badges eb JOIN eleves e ON eb.eleve_id = e.id WHERE e.actif = 1 ORDER BY eb.xp DESC LIMIT :l");
        $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getBadgesEleve(int $eleveId): array
    {
        $stmt = $this->pdo->prepare("SELECT b.nom, b.description, b.icone, eb.date_obtention FROM tutorat_eleve_badges eb JOIN tutorat_badges b ON eb.badge_id = b.id WHERE eb.eleve_id = :eid ORDER BY eb.date_obtention DESC");
        $stmt->execute([':eid' => $eleveId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Attestation ──────────────────────────────────────────────

    public function genererAttestationData(int $tuteurId): array
    {
        $eleve = $this->pdo->prepare("SELECT nom, prenom, classe FROM eleves WHERE id = :id");
        $eleve->execute([':id' => $tuteurId]);
        $eleve = $eleve->fetch(PDO::FETCH_ASSOC);

        $stats = $this->pdo->prepare("SELECT COUNT(DISTINCT tp.tutore_id) AS nb_tutores, COUNT(ts.id) AS nb_sessions, SUM(TIMESTAMPDIFF(MINUTE, CONCAT(ts.date_session,' ',ts.heure_debut), CONCAT(ts.date_session,' ',ts.heure_fin))) AS total_minutes FROM tutorat_pairs tp LEFT JOIN tutorat_sessions ts ON ts.pair_id = tp.id AND ts.statut = 'terminee' WHERE tp.tuteur_id = :tid AND tp.statut = 'actif'");
        $stats->execute([':tid' => $tuteurId]);

        $matieres = $this->pdo->prepare("SELECT DISTINCT m.nom FROM tutorat_pairs tp JOIN matieres m ON tp.matiere_id = m.id WHERE tp.tuteur_id = :tid AND tp.statut = 'actif'");
        $matieres->execute([':tid' => $tuteurId]);

        return [
            'eleve' => $eleve,
            'stats' => $stats->fetch(PDO::FETCH_ASSOC),
            'matieres' => $matieres->fetchAll(PDO::FETCH_COLUMN)
        ];
    }
}
