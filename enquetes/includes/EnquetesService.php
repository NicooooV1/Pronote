<?php
declare(strict_types=1);

namespace Enquetes;

use PDO;

/**
 * EnquetesService — Enquêtes & Satisfaction.
 *
 * Builder enquêtes multi-pages, distribution ciblée, mode anonyme,
 * baromètre climat scolaire, rapports automatisés, NPS.
 */
class EnquetesService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── Création ─────────────────────────────────────────────────

    public function creerEnquete(int $etabId, int $createurId, string $titre, string $description, string $type = 'custom', array $cibleRoles = [], array $cibleClasses = [], bool $anonyme = false, string $dateOuverture = '', string $dateFermeture = ''): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO enquetes (etablissement_id, titre, description, type, cible_roles, cible_classes, anonyme, date_ouverture, date_fermeture, statut, creee_par) VALUES (:eid, :t, :d, :ty, :cr, :cc, :a, :do, :df, 'brouillon', :cb)");
        $stmt->execute([':eid' => $etabId, ':t' => $titre, ':d' => $description, ':ty' => $type, ':cr' => json_encode($cibleRoles), ':cc' => json_encode($cibleClasses), ':a' => $anonyme ? 1 : 0, ':do' => $dateOuverture, ':df' => $dateFermeture, ':cb' => $createurId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function ajouterPage(int $enqueteId, string $titre, string $description = '', int $ordre = 0): int
    {
        if ($ordre === 0) {
            $max = $this->pdo->prepare("SELECT COALESCE(MAX(ordre),0)+1 FROM enquete_pages WHERE enquete_id = :eid");
            $max->execute([':eid' => $enqueteId]);
            $ordre = (int)$max->fetchColumn();
        }
        $stmt = $this->pdo->prepare("INSERT INTO enquete_pages (enquete_id, titre, description, ordre) VALUES (:eid, :t, :d, :o)");
        $stmt->execute([':eid' => $enqueteId, ':t' => $titre, ':d' => $description, ':o' => $ordre]);
        return (int)$this->pdo->lastInsertId();
    }

    public function ajouterQuestion(int $pageId, string $type, string $enonce, bool $obligatoire = false, ?array $options = null, int $ordre = 0): int
    {
        if ($ordre === 0) {
            $max = $this->pdo->prepare("SELECT COALESCE(MAX(ordre),0)+1 FROM enquete_questions WHERE page_id = :pid");
            $max->execute([':pid' => $pageId]);
            $ordre = (int)$max->fetchColumn();
        }
        $stmt = $this->pdo->prepare("INSERT INTO enquete_questions (page_id, type, enonce, obligatoire, options, ordre) VALUES (:pid, :t, :e, :o, :opt, :ord)");
        $stmt->execute([':pid' => $pageId, ':t' => $type, ':e' => $enonce, ':o' => $obligatoire ? 1 : 0, ':opt' => json_encode($options), ':ord' => $ordre]);
        return (int)$this->pdo->lastInsertId();
    }

    public function publierEnquete(int $enqueteId): void
    {
        $this->pdo->prepare("UPDATE enquetes SET statut = 'ouverte' WHERE id = :id AND statut = 'brouillon'")
            ->execute([':id' => $enqueteId]);
    }

    public function fermerEnquete(int $enqueteId): void
    {
        $this->pdo->prepare("UPDATE enquetes SET statut = 'fermee' WHERE id = :id AND statut = 'ouverte'")
            ->execute([':id' => $enqueteId]);
    }

    public function getEnquete(int $enqueteId): ?array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM enquetes WHERE id = :id");
        $stmt->execute([':id' => $enqueteId]);
        $enquete = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$enquete) return null;

        $pages = $this->pdo->prepare("SELECT * FROM enquete_pages WHERE enquete_id = :eid ORDER BY ordre");
        $pages->execute([':eid' => $enqueteId]);
        $enquete['pages'] = [];

        foreach ($pages as $page) {
            $questions = $this->pdo->prepare("SELECT * FROM enquete_questions WHERE page_id = :pid ORDER BY ordre");
            $questions->execute([':pid' => $page['id']]);
            $page['questions'] = $questions->fetchAll(PDO::FETCH_ASSOC);
            $enquete['pages'][] = $page;
        }

        return $enquete;
    }

    public function getEnquetesOuvertes(int $etabId, string $userType): array
    {
        $stmt = $this->pdo->prepare("SELECT id, titre, description, type, date_ouverture, date_fermeture, anonyme FROM enquetes WHERE etablissement_id = :eid AND statut = 'ouverte' AND (cible_roles IS NULL OR JSON_CONTAINS(cible_roles, :role)) ORDER BY date_fermeture ASC");
        $stmt->execute([':eid' => $etabId, ':role' => json_encode($userType)]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Participation ────────────────────────────────────────────

    public function participerEnquete(int $enqueteId, int $userId, string $userType): int
    {
        $enquete = $this->pdo->prepare("SELECT anonyme FROM enquetes WHERE id = :id");
        $enquete->execute([':id' => $enqueteId]);
        $anonyme = (bool)$enquete->fetchColumn();

        $hash = hash('sha256', $enqueteId . ':' . $userId . ':' . $userType . ':' . random_bytes(8));

        $stmt = $this->pdo->prepare("INSERT INTO enquete_participations (enquete_id, participant_hash, user_id, user_type, completed) VALUES (:eid, :h, :uid, :ut, 0)");
        $stmt->execute([
            ':eid' => $enqueteId,
            ':h' => $hash,
            ':uid' => $anonyme ? null : $userId,
            ':ut' => $anonyme ? null : $userType
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function soumettreReponses(int $participationId, array $reponses): void
    {
        foreach ($reponses as $questionId => $valeur) {
            $texte = is_string($valeur) ? $valeur : null;
            $numero = is_numeric($valeur) ? (float)$valeur : null;
            $json = is_array($valeur) ? $valeur : null;

            $this->pdo->prepare("INSERT INTO enquete_reponses (participation_id, question_id, valeur_texte, valeur_numero, valeur_json) VALUES (:pid, :qid, :vt, :vn, :vj)")
                ->execute([':pid' => $participationId, ':qid' => $questionId, ':vt' => $texte, ':vn' => $numero, ':vj' => $json ? json_encode($json) : null]);
        }

        $this->pdo->prepare("UPDATE enquete_participations SET completed = 1, date_soumission = NOW() WHERE id = :id")
            ->execute([':id' => $participationId]);
    }

    public function aDejaParticipe(int $enqueteId, int $userId, string $userType): bool
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM enquete_participations WHERE enquete_id = :eid AND user_id = :uid AND user_type = :ut AND completed = 1");
        $stmt->execute([':eid' => $enqueteId, ':uid' => $userId, ':ut' => $userType]);
        return $stmt->fetchColumn() > 0;
    }

    // ─── Résultats ────────────────────────────────────────────────

    public function getResultats(int $enqueteId): array
    {
        $enquete = $this->getEnquete($enqueteId);
        if (!$enquete) return [];

        $nbParticipations = $this->pdo->prepare("SELECT COUNT(*) FROM enquete_participations WHERE enquete_id = :eid AND completed = 1");
        $nbParticipations->execute([':eid' => $enqueteId]);
        $total = (int)$nbParticipations->fetchColumn();

        $resultats = ['enquete' => $enquete['titre'], 'nb_participations' => $total, 'questions' => []];

        foreach ($enquete['pages'] as $page) {
            foreach ($page['questions'] as $q) {
                $qResult = ['id' => $q['id'], 'enonce' => $q['enonce'], 'type' => $q['type']];

                if (in_array($q['type'], ['likert', 'nps', 'nombre'])) {
                    $stats = $this->pdo->prepare("SELECT COUNT(*) AS nb, ROUND(AVG(valeur_numero),2) AS moyenne, MIN(valeur_numero) AS min_val, MAX(valeur_numero) AS max_val, ROUND(STDDEV(valeur_numero),2) AS ecart_type FROM enquete_reponses WHERE question_id = :qid AND valeur_numero IS NOT NULL");
                    $stats->execute([':qid' => $q['id']]);
                    $qResult['stats'] = $stats->fetch(PDO::FETCH_ASSOC);

                    // Distribution
                    $dist = $this->pdo->prepare("SELECT valeur_numero AS valeur, COUNT(*) AS nb FROM enquete_reponses WHERE question_id = :qid AND valeur_numero IS NOT NULL GROUP BY valeur_numero ORDER BY valeur_numero");
                    $dist->execute([':qid' => $q['id']]);
                    $qResult['distribution'] = $dist->fetchAll(PDO::FETCH_ASSOC);
                } elseif (in_array($q['type'], ['choix_unique', 'choix_multiple'])) {
                    $dist = $this->pdo->prepare("SELECT valeur_texte AS choix, COUNT(*) AS nb FROM enquete_reponses WHERE question_id = :qid AND valeur_texte IS NOT NULL GROUP BY valeur_texte ORDER BY nb DESC");
                    $dist->execute([':qid' => $q['id']]);
                    $qResult['distribution'] = $dist->fetchAll(PDO::FETCH_ASSOC);
                } elseif ($q['type'] === 'texte') {
                    $textes = $this->pdo->prepare("SELECT valeur_texte FROM enquete_reponses WHERE question_id = :qid AND valeur_texte IS NOT NULL AND valeur_texte != '' LIMIT 100");
                    $textes->execute([':qid' => $q['id']]);
                    $qResult['reponses_texte'] = $textes->fetchAll(PDO::FETCH_COLUMN);
                }

                $resultats['questions'][] = $qResult;
            }
        }

        return $resultats;
    }

    // ─── NPS ──────────────────────────────────────────────────────

    public function calculerNPS(int $enqueteId, int $questionId): array
    {
        $stmt = $this->pdo->prepare("SELECT valeur_numero FROM enquete_reponses r JOIN enquete_participations p ON r.participation_id = p.id WHERE p.enquete_id = :eid AND r.question_id = :qid AND r.valeur_numero IS NOT NULL AND p.completed = 1");
        $stmt->execute([':eid' => $enqueteId, ':qid' => $questionId]);
        $scores = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($scores)) return ['nps' => 0, 'promoteurs' => 0, 'passifs' => 0, 'detracteurs' => 0, 'total' => 0];

        $total = count($scores);
        $promoteurs = count(array_filter($scores, fn($s) => $s >= 9));
        $detracteurs = count(array_filter($scores, fn($s) => $s <= 6));
        $passifs = $total - $promoteurs - $detracteurs;

        $nps = round((($promoteurs - $detracteurs) / $total) * 100, 1);

        return ['nps' => $nps, 'promoteurs' => $promoteurs, 'passifs' => $passifs, 'detracteurs' => $detracteurs, 'total' => $total];
    }

    // ─── Baromètre climat scolaire ────────────────────────────────

    public function genererBarometre(int $enqueteId): array
    {
        $dimensions = [
            'sentiment_securite' => [],
            'qualite_apprentissages' => [],
            'relations_pairs' => [],
            'relations_adultes' => [],
            'sentiment_appartenance' => [],
            'climat_general' => []
        ];

        // Récupérer toutes les questions avec leurs tags/options
        $questions = $this->pdo->prepare("SELECT q.id, q.enonce, q.options FROM enquete_questions q JOIN enquete_pages p ON q.page_id = p.id WHERE p.enquete_id = :eid ORDER BY p.ordre, q.ordre");
        $questions->execute([':eid' => $enqueteId]);

        foreach ($questions as $q) {
            $opts = json_decode($q['options'] ?? '{}', true);
            $dim = $opts['dimension'] ?? 'climat_general';
            if (!isset($dimensions[$dim])) $dim = 'climat_general';

            $avg = $this->pdo->prepare("SELECT ROUND(AVG(valeur_numero),2) FROM enquete_reponses WHERE question_id = :qid AND valeur_numero IS NOT NULL");
            $avg->execute([':qid' => $q['id']]);
            $val = $avg->fetchColumn();
            if ($val !== false) $dimensions[$dim][] = (float)$val;
        }

        $barometre = [];
        foreach ($dimensions as $dim => $scores) {
            $barometre[$dim] = empty($scores) ? null : round(array_sum($scores) / count($scores), 2);
        }

        $nonNull = array_filter($barometre, fn($v) => $v !== null);
        $barometre['score_global'] = empty($nonNull) ? null : round(array_sum($nonNull) / count($nonNull), 2);

        return $barometre;
    }

    // ─── Comparaison annuelle ─────────────────────────────────────

    public function comparerAnnees(int $etabId, string $type = 'climat_scolaire'): array
    {
        $stmt = $this->pdo->prepare("SELECT e.id, e.titre, YEAR(e.date_ouverture) AS annee FROM enquetes e WHERE e.etablissement_id = :eid AND e.type = :t AND e.statut IN ('fermee','archivee') ORDER BY e.date_ouverture");
        $stmt->execute([':eid' => $etabId, ':t' => $type]);
        $enquetes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $comparaison = [];
        foreach ($enquetes as $enq) {
            $barometre = $this->genererBarometre($enq['id']);
            $comparaison[] = [
                'annee' => $enq['annee'],
                'titre' => $enq['titre'],
                'barometre' => $barometre
            ];
        }

        return $comparaison;
    }

    // ─── Taux de réponse ──────────────────────────────────────────

    public function getTauxReponse(int $enqueteId): array
    {
        $enquete = $this->pdo->prepare("SELECT cible_roles, cible_classes, etablissement_id FROM enquetes WHERE id = :id");
        $enquete->execute([':id' => $enqueteId]);
        $enq = $enquete->fetch(PDO::FETCH_ASSOC);

        $participations = $this->pdo->prepare("SELECT COUNT(*) FROM enquete_participations WHERE enquete_id = :eid AND completed = 1");
        $participations->execute([':eid' => $enqueteId]);
        $nbReponses = (int)$participations->fetchColumn();

        // Estimation cible
        $cibleClasses = json_decode($enq['cible_classes'] ?? '[]', true);
        $nbCible = 0;
        if (!empty($cibleClasses)) {
            $placeholders = implode(',', array_fill(0, count($cibleClasses), '?'));
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM eleves WHERE classe IN ({$placeholders}) AND actif = 1");
            $stmt->execute($cibleClasses);
            $nbCible = (int)$stmt->fetchColumn();
        }

        return [
            'nb_reponses' => $nbReponses,
            'nb_cible' => $nbCible,
            'taux' => $nbCible > 0 ? round(($nbReponses / $nbCible) * 100, 1) : null
        ];
    }
}
