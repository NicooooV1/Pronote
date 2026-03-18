-- 004: Notes locking, history + discipline workflow columns
-- Notes: verrouillage et historique
ALTER TABLE notes ADD COLUMN IF NOT EXISTS locked TINYINT(1) DEFAULT 0;
ALTER TABLE notes ADD COLUMN IF NOT EXISTS locked_by INT DEFAULT NULL;
ALTER TABLE notes ADD COLUMN IF NOT EXISTS locked_at DATETIME DEFAULT NULL;

CREATE TABLE IF NOT EXISTS note_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    note_id INT NOT NULL,
    note_value DECIMAL(5,2) NOT NULL,
    note_sur DECIMAL(5,2) DEFAULT 20,
    coefficient DECIMAL(3,2) DEFAULT 1,
    commentaire TEXT,
    modified_by INT NOT NULL,
    modified_at DATETIME NOT NULL,
    reason VARCHAR(255) DEFAULT '',
    INDEX idx_note_history_note (note_id),
    INDEX idx_note_history_date (modified_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Discipline: colonnes workflow sur incidents
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS traite_par_id INT DEFAULT NULL;
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS traite_at DATETIME DEFAULT NULL;
ALTER TABLE incidents ADD COLUMN IF NOT EXISTS commentaire_traitement TEXT;

-- Index performance discipline
CREATE INDEX IF NOT EXISTS idx_incidents_statut ON incidents(statut);
CREATE INDEX IF NOT EXISTS idx_incidents_date ON incidents(date_incident);
CREATE INDEX IF NOT EXISTS idx_incidents_eleve ON incidents(eleve_id);
CREATE INDEX IF NOT EXISTS idx_sanctions_eleve ON sanctions(eleve_id);
CREATE INDEX IF NOT EXISTS idx_sanctions_incident ON sanctions(incident_id);
CREATE INDEX IF NOT EXISTS idx_sanctions_date ON sanctions(date_sanction);
