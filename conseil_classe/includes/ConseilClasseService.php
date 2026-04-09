<?php
declare(strict_types=1);

namespace ConseilClasse;

use PDO;

/**
 * ConseilClasseService — Conseils de classe numériques.
 *
 * Planification, préparation automatique, déroulé structuré, votes électroniques,
 * appréciations collaboratives, PV automatique et push vers bulletins.
 */
class ConseilClasseService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── Planification ─────────────────────────────────────────────

    public function planifierConseil(int $etabId, string $classeId, int $periodeId, string $annee, string $dateConseil, string $lieu, int $presidentId, string $presidentType, int $secretaireId, string $secretaireType): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO conseil_classe_sessions (etablissement_id, classe_id, periode_id, annee_scolaire, date_conseil, lieu, president_id, president_type, secretaire_id, secretaire_type) VALUES (:e,:c,:p,:a,:d,:l,:pi,:pt,:si,:st)");
        $stmt->execute([':e' => $etabId, ':c' => $classeId, ':p' => $periodeId, ':a' => $annee, ':d' => $dateConseil, ':l' => $lieu, ':pi' => $presidentId, ':pt' => $presidentType, ':si' => $secretaireId, ':st' => $secretaireType]);
        $sessionId = (int)$this->pdo->lastInsertId();

        // Auto-populate eleve discussions
        $eleves = $this->pdo->prepare("SELECT id FROM eleves WHERE classe = :c AND actif = 1 ORDER BY nom, prenom");
        $eleves->execute([':c' => $classeId]);
        $ordre = 1;
        foreach ($eleves as $e) {
            $this->pdo->prepare("INSERT INTO conseil_classe_eleve_discussions (session_id, eleve_id, ordre) VALUES (:s, :e, :o)")
                ->execute([':s' => $sessionId, ':e' => $e['id'], ':o' => $ordre++]);
        }

        return $sessionId;
    }

    public function getSessions(string $classeId, ?string $annee = null): array
    {
        $sql = "SELECT * FROM conseil_classe_sessions WHERE classe_id = :c";
        $params = [':c' => $classeId];
        if ($annee) { $sql .= " AND annee_scolaire = :a"; $params[':a'] = $annee; }
        $sql .= " ORDER BY date_conseil DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Préparation ───────────────────────────────────────────────

    public function getPreparationClasse(int $sessionId): array
    {
        $session = $this->pdo->prepare("SELECT * FROM conseil_classe_sessions WHERE id = :id");
        $session->execute([':id' => $sessionId]);
        $session = $session->fetch(PDO::FETCH_ASSOC);

        $classeId = $session['classe_id'];
        $stats = $this->pdo->prepare("SELECT COUNT(DISTINCT e.id) AS nb_eleves, ROUND(AVG(n.note),2) AS moyenne_classe, (SELECT COUNT(*) FROM absences a JOIN eleves e2 ON a.id_eleve = e2.id WHERE e2.classe = :c AND a.justifiee = 0) AS absences_nj_total FROM eleves e LEFT JOIN notes n ON n.id_eleve = e.id WHERE e.classe = :c2 AND e.actif = 1");
        $stats->execute([':c' => $classeId, ':c2' => $classeId]);

        return ['session' => $session, 'stats_classe' => $stats->fetch(PDO::FETCH_ASSOC)];
    }

    public function getResumeEleve(int $sessionId, int $eleveId): array
    {
        $eleve = $this->pdo->prepare("SELECT id, nom, prenom, classe FROM eleves WHERE id = :id");
        $eleve->execute([':id' => $eleveId]);
        $eleve = $eleve->fetch(PDO::FETCH_ASSOC);

        $moyennes = $this->pdo->prepare("SELECT m.nom AS matiere, ROUND(AVG(n.note),2) AS moyenne FROM notes n JOIN matieres m ON n.id_matiere = m.id WHERE n.id_eleve = :eid GROUP BY m.id ORDER BY m.nom");
        $moyennes->execute([':eid' => $eleveId]);

        $absences = $this->pdo->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN justifiee = 0 THEN 1 ELSE 0 END) AS non_justifiees FROM absences WHERE id_eleve = :eid");
        $absences->execute([':eid' => $eleveId]);

        $incidents = $this->pdo->prepare("SELECT COUNT(*) AS total FROM incidents WHERE eleve_id = :eid");
        $incidents->execute([':eid' => $eleveId]);

        $discussion = $this->pdo->prepare("SELECT * FROM conseil_classe_eleve_discussions WHERE session_id = :sid AND eleve_id = :eid");
        $discussion->execute([':sid' => $sessionId, ':eid' => $eleveId]);

        return ['eleve' => $eleve, 'moyennes' => $moyennes->fetchAll(PDO::FETCH_ASSOC), 'absences' => $absences->fetch(PDO::FETCH_ASSOC), 'incidents' => $incidents->fetch(PDO::FETCH_ASSOC), 'discussion' => $discussion->fetch(PDO::FETCH_ASSOC)];
    }

    // ─── Déroulé du conseil ────────────────────────────────────────

    public function demarrerConseil(int $sessionId): void
    {
        $this->pdo->prepare("UPDATE conseil_classe_sessions SET statut = 'en_cours' WHERE id = :id")->execute([':id' => $sessionId]);
    }

    public function enregistrerAppreciation(int $discussionId, string $appreciation, string $avisPropose = 'aucun'): void
    {
        $this->pdo->prepare("UPDATE conseil_classe_eleve_discussions SET appreciation = :app, avis_propose = :avis WHERE id = :id")
            ->execute([':app' => $appreciation, ':avis' => $avisPropose, ':id' => $discussionId]);
    }

    public function ajouterCommentaireDelegue(int $discussionId, string $commentaire, string $typeDelegue): void
    {
        $col = $typeDelegue === 'parent' ? 'commentaire_delegue_parent' : 'commentaire_delegue_eleve';
        $this->pdo->prepare("UPDATE conseil_classe_eleve_discussions SET {$col} = :com WHERE id = :id")
            ->execute([':com' => $commentaire, ':id' => $discussionId]);
    }

    // ─── Votes ─────────────────────────────────────────────────────

    public function proposerAvis(int $discussionId, string $avis): void
    {
        $this->pdo->prepare("UPDATE conseil_classe_eleve_discussions SET avis_propose = :a WHERE id = :id")
            ->execute([':a' => $avis, ':id' => $discussionId]);
    }

    public function voter(int $discussionId, int $voterId, string $voterType, string $vote): void
    {
        $this->pdo->prepare("INSERT INTO conseil_classe_votes (discussion_id, voter_id, voter_type, vote) VALUES (:d, :v, :vt, :vo) ON DUPLICATE KEY UPDATE vote = VALUES(vote)")
            ->execute([':d' => $discussionId, ':v' => $voterId, ':vt' => $voterType, ':vo' => $vote]);

        // Recalculate counts
        foreach (['pour', 'contre', 'abstention'] as $v) {
            $cnt = $this->pdo->prepare("SELECT COUNT(*) FROM conseil_classe_votes WHERE discussion_id = :d AND vote = :v");
            $cnt->execute([':d' => $discussionId, ':v' => $v]);
            $this->pdo->prepare("UPDATE conseil_classe_eleve_discussions SET avis_vote_{$v} = :c WHERE id = :id")
                ->execute([':c' => $cnt->fetchColumn(), ':id' => $discussionId]);
        }

        // Set final avis if majority
        $disc = $this->pdo->prepare("SELECT avis_propose, avis_vote_pour, avis_vote_contre FROM conseil_classe_eleve_discussions WHERE id = :id");
        $disc->execute([':id' => $discussionId]);
        $d = $disc->fetch(PDO::FETCH_ASSOC);
        if ($d['avis_vote_pour'] > $d['avis_vote_contre']) {
            $this->pdo->prepare("UPDATE conseil_classe_eleve_discussions SET avis_final = avis_propose WHERE id = :id")->execute([':id' => $discussionId]);
        }
    }

    // ─── Clôture ───────────────────────────────────────────────────

    public function terminerConseil(int $sessionId): void
    {
        $this->pdo->prepare("UPDATE conseil_classe_sessions SET statut = 'termine' WHERE id = :id")->execute([':id' => $sessionId]);
    }

    public function enregistrerSynthese(int $sessionId, string $synthese, string $pointsPositifs = '', string $pointsAmelioration = '', string $decisions = ''): void
    {
        $this->pdo->prepare("INSERT INTO conseil_classe_synthese (session_id, synthese_generale, points_positifs, points_amelioration, decisions) VALUES (:s, :sg, :pp, :pa, :d) ON DUPLICATE KEY UPDATE synthese_generale=VALUES(synthese_generale), points_positifs=VALUES(points_positifs), points_amelioration=VALUES(points_amelioration), decisions=VALUES(decisions)")
            ->execute([':s' => $sessionId, ':sg' => $synthese, ':pp' => $pointsPositifs, ':pa' => $pointsAmelioration, ':d' => $decisions]);
    }

    // ─── Push vers Bulletins ───────────────────────────────────────

    public function pousserVersBulletins(int $sessionId): int
    {
        $discussions = $this->pdo->prepare("SELECT eleve_id, appreciation, avis_final FROM conseil_classe_eleve_discussions WHERE session_id = :s AND appreciation IS NOT NULL");
        $discussions->execute([':s' => $sessionId]);
        $count = 0;

        $session = $this->pdo->prepare("SELECT periode_id FROM conseil_classe_sessions WHERE id = :id");
        $session->execute([':id' => $sessionId]);
        $periodeId = $session->fetchColumn();

        foreach ($discussions as $d) {
            $this->pdo->prepare("UPDATE bulletins SET appreciation_conseil = :app, avis_conseil = :avis WHERE eleve_id = :eid AND periode_id = :pid")
                ->execute([':app' => $d['appreciation'], ':avis' => $d['avis_final'], ':eid' => $d['eleve_id'], ':pid' => $periodeId]);
            $count++;
        }
        return $count;
    }

    // ─── Génération PV ─────────────────────────────────────────────

    public function genererPV(int $sessionId): array
    {
        $prep = $this->getPreparationClasse($sessionId);
        $participants = $this->pdo->prepare("SELECT p.*, CASE WHEN p.user_type = 'professeur' THEN (SELECT CONCAT(pr.prenom,' ',pr.nom) FROM professeurs pr WHERE pr.id = p.user_id) ELSE CONCAT(p.user_type,'#',p.user_id) END AS nom_complet FROM conseil_classe_participants p WHERE p.session_id = :s");
        $participants->execute([':s' => $sessionId]);

        $discussions = $this->pdo->prepare("SELECT d.*, CONCAT(e.prenom,' ',e.nom) AS eleve FROM conseil_classe_eleve_discussions d JOIN eleves e ON d.eleve_id = e.id WHERE d.session_id = :s ORDER BY d.ordre");
        $discussions->execute([':s' => $sessionId]);

        $synthese = $this->pdo->prepare("SELECT * FROM conseil_classe_synthese WHERE session_id = :s");
        $synthese->execute([':s' => $sessionId]);

        return [
            'session' => $prep['session'],
            'stats' => $prep['stats_classe'],
            'participants' => $participants->fetchAll(PDO::FETCH_ASSOC),
            'discussions' => $discussions->fetchAll(PDO::FETCH_ASSOC),
            'synthese' => $synthese->fetch(PDO::FETCH_ASSOC)
        ];
    }
}
