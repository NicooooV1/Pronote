<?php
declare(strict_types=1);

namespace Mediatheque;

use PDO;

/**
 * MediathequeService — Médiathèque Numérique.
 *
 * Contenus numériques (vidéo, audio, PDF), playlists, suivi visionnage,
 * notation/favoris, recommandations, gestion quotas stockage.
 */
class MediathequeService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ─── Contenus ─────────────────────────────────────────────────

    public function uploadContenu(int $etabId, int $profId, string $titre, string $type, string $fichierPath, int $tailleMo, ?int $matiereId = null, ?string $niveau = null, string $description = '', int $dureeSecondes = 0): int
    {
        // Vérifier quota
        if (!$this->verifierQuota($profId, $tailleMo)) {
            throw new \RuntimeException('Quota de stockage dépassé');
        }

        $stmt = $this->pdo->prepare("INSERT INTO mediatheque_contenus (etablissement_id, professeur_id, titre, type_contenu, fichier_path, taille_mo, matiere_id, niveau, description, duree_secondes, statut) VALUES (:eid, :pid, :t, :ty, :fp, :tm, :mid, :n, :d, :ds, 'publie')");
        $stmt->execute([':eid' => $etabId, ':pid' => $profId, ':t' => $titre, ':ty' => $type, ':fp' => $fichierPath, ':tm' => $tailleMo, ':mid' => $matiereId, ':n' => $niveau, ':d' => $description, ':ds' => $dureeSecondes]);

        // Mettre à jour utilisation quota
        $this->pdo->prepare("UPDATE mediatheque_quotas SET utilise_mo = utilise_mo + :t WHERE professeur_id = :pid")
            ->execute([':t' => $tailleMo, ':pid' => $profId]);

        return (int)$this->pdo->lastInsertId();
    }

    public function getContenusByMatiere(int $etabId, int $matiereId, ?string $niveau = null): array
    {
        $sql = "SELECT c.*, CONCAT(p.prenom,' ',p.nom) AS professeur_nom, (SELECT COUNT(*) FROM mediatheque_vues v WHERE v.contenu_id = c.id) AS nb_vues, (SELECT ROUND(AVG(na.note),1) FROM mediatheque_notes_avis na WHERE na.contenu_id = c.id) AS note_moyenne FROM mediatheque_contenus c JOIN professeurs p ON c.professeur_id = p.id WHERE c.etablissement_id = :eid AND c.matiere_id = :mid AND c.statut = 'publie'";
        $params = [':eid' => $etabId, ':mid' => $matiereId];
        if ($niveau) { $sql .= " AND c.niveau = :n"; $params[':n'] = $niveau; }
        $sql .= " ORDER BY c.created_at DESC";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function rechercherContenus(int $etabId, string $query): array
    {
        $stmt = $this->pdo->prepare("SELECT c.*, CONCAT(p.prenom,' ',p.nom) AS professeur_nom, m.nom AS matiere FROM mediatheque_contenus c JOIN professeurs p ON c.professeur_id = p.id LEFT JOIN matieres m ON c.matiere_id = m.id WHERE c.etablissement_id = :eid AND c.statut = 'publie' AND (c.titre LIKE :q OR c.description LIKE :q2 OR m.nom LIKE :q3) ORDER BY c.created_at DESC LIMIT 50");
        $like = "%{$query}%";
        $stmt->execute([':eid' => $etabId, ':q' => $like, ':q2' => $like, ':q3' => $like]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Playlists ────────────────────────────────────────────────

    public function creerPlaylist(int $profId, string $titre, ?int $matiereId = null, ?string $niveau = null, string $description = ''): int
    {
        $stmt = $this->pdo->prepare("INSERT INTO mediatheque_playlists (professeur_id, titre, matiere_id, niveau, description) VALUES (:pid, :t, :mid, :n, :d)");
        $stmt->execute([':pid' => $profId, ':t' => $titre, ':mid' => $matiereId, ':n' => $niveau, ':d' => $description]);
        return (int)$this->pdo->lastInsertId();
    }

    public function ajouterAPlaylist(int $playlistId, int $contenuId, int $ordre = 0): void
    {
        if ($ordre === 0) {
            $max = $this->pdo->prepare("SELECT COALESCE(MAX(ordre),0)+1 FROM mediatheque_playlists_contenus WHERE playlist_id = :pid");
            $max->execute([':pid' => $playlistId]);
            $ordre = (int)$max->fetchColumn();
        }
        $this->pdo->prepare("INSERT IGNORE INTO mediatheque_playlists_contenus (playlist_id, contenu_id, ordre) VALUES (:pid, :cid, :o)")
            ->execute([':pid' => $playlistId, ':cid' => $contenuId, ':o' => $ordre]);
    }

    public function getPlaylistContenus(int $playlistId): array
    {
        $stmt = $this->pdo->prepare("SELECT c.*, pc.ordre FROM mediatheque_contenus c JOIN mediatheque_playlists_contenus pc ON c.id = pc.contenu_id WHERE pc.playlist_id = :pid ORDER BY pc.ordre");
        $stmt->execute([':pid' => $playlistId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Visionnage ───────────────────────────────────────────────

    public function enregistrerVue(int $contenuId, int $userId, string $userType, int $dureeVisionnee = 0, float $progression = 0): void
    {
        $this->pdo->prepare("INSERT INTO mediatheque_vues (contenu_id, user_id, user_type, duree_visionnee, progression, date_vue) VALUES (:cid, :uid, :ut, :dv, :p, NOW()) ON DUPLICATE KEY UPDATE duree_visionnee = GREATEST(duree_visionnee, VALUES(duree_visionnee)), progression = GREATEST(progression, VALUES(progression)), date_vue = NOW()")
            ->execute([':cid' => $contenuId, ':uid' => $userId, ':ut' => $userType, ':dv' => $dureeVisionnee, ':p' => $progression]);
    }

    public function getStatistiquesVisionnage(int $contenuId): array
    {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) AS nb_vues, COUNT(DISTINCT user_id) AS nb_viewers, ROUND(AVG(progression),1) AS progression_moyenne, ROUND(AVG(duree_visionnee),0) AS duree_moyenne_sec FROM mediatheque_vues WHERE contenu_id = :cid");
        $stmt->execute([':cid' => $contenuId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // ─── Notation & Avis ──────────────────────────────────────────

    public function noterContenu(int $contenuId, int $userId, string $userType, int $note, string $commentaire = ''): void
    {
        $this->pdo->prepare("INSERT INTO mediatheque_notes_avis (contenu_id, user_id, user_type, note, commentaire) VALUES (:cid, :uid, :ut, :n, :c) ON DUPLICATE KEY UPDATE note = VALUES(note), commentaire = VALUES(commentaire)")
            ->execute([':cid' => $contenuId, ':uid' => $userId, ':ut' => $userType, ':n' => $note, ':c' => $commentaire]);
    }

    // ─── Favoris ──────────────────────────────────────────────────

    public function ajouterFavori(int $contenuId, int $userId, string $userType): void
    {
        $this->pdo->prepare("INSERT IGNORE INTO mediatheque_favoris (contenu_id, user_id, user_type) VALUES (:cid, :uid, :ut)")
            ->execute([':cid' => $contenuId, ':uid' => $userId, ':ut' => $userType]);
    }

    public function retirerFavori(int $contenuId, int $userId, string $userType): void
    {
        $this->pdo->prepare("DELETE FROM mediatheque_favoris WHERE contenu_id = :cid AND user_id = :uid AND user_type = :ut")
            ->execute([':cid' => $contenuId, ':uid' => $userId, ':ut' => $userType]);
    }

    public function getFavoris(int $userId, string $userType): array
    {
        $stmt = $this->pdo->prepare("SELECT c.*, f.created_at AS date_favori FROM mediatheque_contenus c JOIN mediatheque_favoris f ON c.id = f.contenu_id WHERE f.user_id = :uid AND f.user_type = :ut ORDER BY f.created_at DESC");
        $stmt->execute([':uid' => $userId, ':ut' => $userType]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Recommandations ──────────────────────────────────────────

    public function getRecommandations(int $userId, string $userType, int $limit = 10): array
    {
        // Recommandations basées sur les matières les plus consultées
        $stmt = $this->pdo->prepare("SELECT c.matiere_id, COUNT(*) AS nb FROM mediatheque_vues v JOIN mediatheque_contenus c ON v.contenu_id = c.id WHERE v.user_id = :uid AND v.user_type = :ut AND c.matiere_id IS NOT NULL GROUP BY c.matiere_id ORDER BY nb DESC LIMIT 3");
        $stmt->execute([':uid' => $userId, ':ut' => $userType]);
        $topMatieres = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (empty($topMatieres)) {
            // Contenus populaires
            $stmt = $this->pdo->prepare("SELECT c.*, COUNT(v.id) AS nb_vues FROM mediatheque_contenus c LEFT JOIN mediatheque_vues v ON v.contenu_id = c.id WHERE c.statut = 'publie' GROUP BY c.id ORDER BY nb_vues DESC LIMIT :l");
            $stmt->bindValue(':l', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $placeholders = implode(',', array_fill(0, count($topMatieres), '?'));
        $params = $topMatieres;
        $params[] = $userId;
        $params[] = $userType;

        $stmt = $this->pdo->prepare("SELECT c.* FROM mediatheque_contenus c WHERE c.matiere_id IN ({$placeholders}) AND c.statut = 'publie' AND c.id NOT IN (SELECT contenu_id FROM mediatheque_vues WHERE user_id = ? AND user_type = ?) ORDER BY c.created_at DESC LIMIT {$limit}");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ─── Quotas ───────────────────────────────────────────────────

    public function verifierQuota(int $profId, int $tailleMoAjout = 0): bool
    {
        $stmt = $this->pdo->prepare("SELECT quota_mo, utilise_mo FROM mediatheque_quotas WHERE professeur_id = :pid");
        $stmt->execute([':pid' => $profId]);
        $quota = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$quota) {
            // Créer quota par défaut (500 Mo)
            $this->pdo->prepare("INSERT INTO mediatheque_quotas (professeur_id, quota_mo, utilise_mo) VALUES (:pid, 500, 0)")
                ->execute([':pid' => $profId]);
            return $tailleMoAjout <= 500;
        }

        return ($quota['utilise_mo'] + $tailleMoAjout) <= $quota['quota_mo'];
    }

    public function getQuotaInfo(int $profId): array
    {
        $stmt = $this->pdo->prepare("SELECT quota_mo, utilise_mo FROM mediatheque_quotas WHERE professeur_id = :pid");
        $stmt->execute([':pid' => $profId]);
        $quota = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$quota) return ['quota_mo' => 500, 'utilise_mo' => 0, 'restant_mo' => 500, 'pourcentage' => 0];

        return [
            'quota_mo' => (int)$quota['quota_mo'],
            'utilise_mo' => (int)$quota['utilise_mo'],
            'restant_mo' => (int)$quota['quota_mo'] - (int)$quota['utilise_mo'],
            'pourcentage' => round(($quota['utilise_mo'] / max(1, $quota['quota_mo'])) * 100, 1)
        ];
    }
}
