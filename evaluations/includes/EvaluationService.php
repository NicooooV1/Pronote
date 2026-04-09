<?php
declare(strict_types=1);

namespace Evaluations;

use PDO;

/**
 * EvaluationService — QCM & évaluations en ligne.
 *
 * Gère banques de questions, évaluations chronométrées, auto-correction,
 * statistiques par question et push vers le module notes.
 */
class EvaluationService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── Banques de questions ──────────────────────────────────────

    public function createBanque(int $etabId, int $profId, string $titre, ?int $matiereId = null, string $description = ''): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO evaluation_banques (etablissement_id, professeur_id, titre, matiere_id, description) VALUES (:e, :p, :t, :m, :d)");
        $stmt->execute([':e' => $etabId, ':p' => $profId, ':t' => $titre, ':m' => $matiereId, ':d' => $description]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getBanques(int $profId): array
    {
        $stmt = $this->pdo->prepare("SELECT b.*, COUNT(q.id) AS nb_questions FROM evaluation_banques b LEFT JOIN evaluation_questions q ON q.banque_id = b.id WHERE b.professeur_id = :p GROUP BY b.id ORDER BY b.created_at DESC");
        $stmt->execute([':p' => $profId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addQuestion(int $banqueId, string $type, string $enonce, ?array $reponsesPossibles, ?array $reponseCorrecte, float $points = 1.0, string $difficulte = 'moyen', string $explication = ''): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO evaluation_questions (banque_id, type_question, enonce, reponses_possibles, reponse_correcte, points, difficulte, explication) VALUES (:b, :t, :e, :rp, :rc, :pts, :d, :ex)");
        $stmt->execute([':b' => $banqueId, ':t' => $type, ':e' => $enonce, ':rp' => json_encode($reponsesPossibles), ':rc' => json_encode($reponseCorrecte), ':pts' => $points, ':d' => $difficulte, ':ex' => $explication]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getQuestions(int $banqueId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM evaluation_questions WHERE banque_id = :b ORDER BY id");
        $stmt->execute([':b' => $banqueId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Évaluations ───────────────────────────────────────────────

    public function createEvaluation(int $etabId, int $profId, string $titre, ?int $matiereId, string $classe, array $questionsConfig, int $dureeMinutes, string $dateOuverture, string $dateFermeture, string $mode = 'examen', bool $antiTriche = true): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO evaluations_en_ligne (etablissement_id, professeur_id, titre, matiere_id, classe, questions_config, duree_minutes, date_ouverture, date_fermeture, mode, anti_triche) VALUES (:e,:p,:t,:m,:c,:qc,:d,:do,:df,:mo,:at)");
        $stmt->execute([':e' => $etabId, ':p' => $profId, ':t' => $titre, ':m' => $matiereId, ':c' => $classe, ':qc' => json_encode($questionsConfig), ':d' => $dureeMinutes, ':do' => $dateOuverture, ':df' => $dateFermeture, ':mo' => $mode, ':at' => $antiTriche ? 1 : 0]);
        return (int)$this->pdo->lastInsertId();
    }

    public function getEvaluationsClasse(string $classe): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM evaluations_en_ligne WHERE classe = :c AND statut != 'brouillon' ORDER BY date_ouverture DESC");
        $stmt->execute([':c' => $classe]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getEvaluationsProf(int $profId): array
    {
        $stmt = $this->pdo->prepare("SELECT el.*, (SELECT COUNT(*) FROM evaluation_sessions es WHERE es.evaluation_id = el.id AND es.statut = 'soumis') AS nb_soumis, (SELECT COUNT(*) FROM evaluation_sessions es2 WHERE es2.evaluation_id = el.id AND es2.statut = 'corrige') AS nb_corriges FROM evaluations_en_ligne el WHERE el.professeur_id = :p ORDER BY el.created_at DESC");
        $stmt->execute([':p' => $profId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Sessions ──────────────────────────────────────────────────

    public function startSession(int $evaluationId, int $eleveId): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO evaluation_sessions (evaluation_id, eleve_id, date_debut, statut, ip_address, user_agent) VALUES (:e, :s, NOW(), 'en_cours', :ip, :ua) ON DUPLICATE KEY UPDATE date_debut = IF(statut='en_cours', date_debut, NOW()), statut = 'en_cours'");
        $stmt->execute([':e' => $evaluationId, ':s' => $eleveId, ':ip' => $_SERVER['REMOTE_ADDR'] ?? '', ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
        return (int)$this->pdo->lastInsertId();
    }

    public function submitReponse(int $sessionId, int $questionId, $reponse): void
    {
        $this->pdo->prepare("INSERT INTO evaluation_reponses (session_id, question_id, reponse_donnee) VALUES (:s, :q, :r) ON DUPLICATE KEY UPDATE reponse_donnee = VALUES(reponse_donnee)")
            ->execute([':s' => $sessionId, ':q' => $questionId, ':r' => json_encode($reponse)]);
    }

    public function terminerSession(int $sessionId): void
    {
        $this->pdo->prepare("UPDATE evaluation_sessions SET date_fin = NOW(), statut = 'soumis' WHERE id = :id AND statut = 'en_cours'")
            ->execute([':id' => $sessionId]);
    }

    // ─── Auto-correction ───────────────────────────────────────────

    public function autoCorrect(int $sessionId): float
    {
        $reponses = $this->pdo->prepare("SELECT er.*, eq.type_question, eq.reponse_correcte, eq.points FROM evaluation_reponses er JOIN evaluation_questions eq ON er.question_id = eq.id WHERE er.session_id = :s");
        $reponses->execute([':s' => $sessionId]);
        $totalScore = 0;
        $totalPoints = 0;

        foreach ($reponses as $rep) {
            $correcte = json_decode($rep['reponse_correcte'], true);
            $donnee = json_decode($rep['reponse_donnee'], true);
            $points = (float)$rep['points'];
            $totalPoints += $points;
            $isCorrect = false;

            if (in_array($rep['type_question'], ['qcm', 'vrai_faux', 'courte'])) {
                $isCorrect = $this->compareReponses($correcte, $donnee);
                $scored = $isCorrect ? $points : 0;
                $totalScore += $scored;
                $this->pdo->prepare("UPDATE evaluation_reponses SET correct = :c, points_obtenus = :p WHERE id = :id")
                    ->execute([':c' => $isCorrect ? 1 : 0, ':p' => $scored, ':id' => $rep['id']]);
            }
            // Longue/association: manual correction needed
        }

        $this->pdo->prepare("UPDATE evaluation_sessions SET score = :s, note_sur = :ns, statut = 'corrige' WHERE id = :id")
            ->execute([':s' => $totalScore, ':ns' => $totalPoints, ':id' => $sessionId]);
        return $totalScore;
    }

    private function compareReponses($correcte, $donnee): bool
    {
        if (is_array($correcte) && is_array($donnee)) {
            sort($correcte);
            sort($donnee);
            return $correcte === $donnee;
        }
        return mb_strtolower(trim((string)$correcte)) === mb_strtolower(trim((string)$donnee));
    }

    // ─── Statistiques ──────────────────────────────────────────────

    public function calculateStats(int $evaluationId): void
    {
        $questions = $this->pdo->prepare("SELECT DISTINCT eq.id FROM evaluation_questions eq JOIN evaluations_en_ligne el ON JSON_CONTAINS(el.questions_config, CAST(eq.id AS CHAR)) WHERE el.id = :eid");
        // Simplified: compute from existing responses
        $stmt = $this->pdo->prepare("SELECT er.question_id, COUNT(*) AS nb_reponses, SUM(er.correct) AS nb_correct, ROUND(AVG(er.correct)*100,2) AS taux_reussite FROM evaluation_reponses er JOIN evaluation_sessions es ON er.session_id = es.id WHERE es.evaluation_id = :eid AND es.statut = 'corrige' GROUP BY er.question_id");
        $stmt->execute([':eid' => $evaluationId]);
        foreach ($stmt as $row) {
            $this->pdo->prepare("INSERT INTO evaluation_statistiques (evaluation_id, question_id, nb_reponses, nb_correct, taux_reussite) VALUES (:eid, :qid, :nr, :nc, :tr) ON DUPLICATE KEY UPDATE nb_reponses=VALUES(nb_reponses), nb_correct=VALUES(nb_correct), taux_reussite=VALUES(taux_reussite)")
                ->execute([':eid' => $evaluationId, ':qid' => $row['question_id'], ':nr' => $row['nb_reponses'], ':nc' => $row['nb_correct'], ':tr' => $row['taux_reussite']]);
        }
    }

    public function getResultsForClasse(int $evaluationId): array
    {
        $stmt = $this->pdo->prepare("SELECT es.eleve_id, CONCAT(e.prenom,' ',e.nom) AS eleve, es.score, es.note_sur, es.statut, es.date_debut, es.date_fin FROM evaluation_sessions es JOIN eleves e ON es.eleve_id = e.id WHERE es.evaluation_id = :eid ORDER BY e.nom, e.prenom");
        $stmt->execute([':eid' => $evaluationId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Push vers Notes ───────────────────────────────────────────

    public function pushToNotes(int $evaluationId, float $noteSur = 20.0): int
    {
        $eval = $this->pdo->prepare("SELECT * FROM evaluations_en_ligne WHERE id = :id");
        $eval->execute([':id' => $evaluationId]);
        $eval = $eval->fetch(PDO::FETCH_ASSOC);
        if (!$eval) return 0;

        $sessions = $this->pdo->prepare("SELECT * FROM evaluation_sessions WHERE evaluation_id = :eid AND statut = 'corrige'");
        $sessions->execute([':eid' => $evaluationId]);
        $count = 0;

        foreach ($sessions as $session) {
            $note = ($session['note_sur'] > 0) ? round(($session['score'] / $session['note_sur']) * $noteSur, 2) : 0;
            $this->pdo->prepare("INSERT INTO notes (id_eleve, id_matiere, note, note_sur, date_evaluation, type_evaluation, commentaire) VALUES (:eid, :mid, :note, :sur, CURDATE(), 'evaluation_en_ligne', :com)")
                ->execute([':eid' => $session['eleve_id'], ':mid' => $eval['matiere_id'], ':note' => $note, ':sur' => $noteSur, ':com' => 'Évaluation: ' . $eval['titre']]);
            $count++;
        }
        return $count;
    }

    // ─── Import/Export GIFT ────────────────────────────────────────

    public function exportBanqueGift(int $banqueId): string
    {
        $questions = $this->getQuestions($banqueId);
        $gift = '';
        foreach ($questions as $q) {
            $gift .= '::' . substr($q['enonce'], 0, 50) . ':: ' . $q['enonce'] . ' {';
            $reponses = json_decode($q['reponses_possibles'], true) ?? [];
            $correcte = json_decode($q['reponse_correcte'], true);
            foreach ($reponses as $r) {
                $prefix = ($r === $correcte || (is_array($correcte) && in_array($r, $correcte))) ? '=' : '~';
                $gift .= "\n  {$prefix}{$r}";
            }
            $gift .= "\n}\n\n";
        }
        return $gift;
    }

    public function importBanqueGift(int $banqueId, string $giftContent): int
    {
        $count = 0;
        // Simple GIFT parser: each block starts with :: and ends with }
        preg_match_all('/::([^:]*)::\s*(.*?)\s*\{(.*?)\}/s', $giftContent, $matches, PREG_SET_ORDER);
        foreach ($matches as $m) {
            $enonce = trim($m[2]);
            $repBlock = trim($m[3]);
            $reponses = [];
            $correcte = null;
            foreach (explode("\n", $repBlock) as $line) {
                $line = trim($line);
                if (preg_match('/^=(.+)/', $line, $rm)) {
                    $correcte = trim($rm[1]);
                    $reponses[] = $correcte;
                } elseif (preg_match('/^~(.+)/', $line, $rm)) {
                    $reponses[] = trim($rm[1]);
                }
            }
            if ($enonce && !empty($reponses)) {
                $this->addQuestion($banqueId, 'qcm', $enonce, $reponses, $correcte ? [$correcte] : null);
                $count++;
            }
        }
        return $count;
    }
}
