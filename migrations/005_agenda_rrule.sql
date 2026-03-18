-- 005: Agenda rrule column + performance indexes
ALTER TABLE evenements ADD COLUMN IF NOT EXISTS rrule VARCHAR(500) DEFAULT NULL COMMENT 'RRULE RFC 5545 pour récurrence';
CREATE INDEX IF NOT EXISTS idx_evenements_date ON evenements(date_debut);
CREATE INDEX IF NOT EXISTS idx_evenements_type ON evenements(type_evenement);
CREATE INDEX IF NOT EXISTS idx_evenements_lieu ON evenements(lieu(100));
