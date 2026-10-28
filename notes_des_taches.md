ALTER TABLE `eleve` ADD `matricule` VARCHAR(20) NOT NULL AFTER `id`;
ALTER TABLE `eleve` ADD `nationalite` VARCHAR(20) NOT NULL AFTER `menage`;
ALTER TABLE `menage` ADD `province` VARCHAR(20) NOT NULL AFTER `id_original`;
UPDATE eleve SET nationalite = 'CONGOLAISE';
UPDATE menage SET province = 'KINSHASA';
UPDATE appel SET anneeScolaire = '2026-2027';
UPDATE appel SET anneeScolaire = '2026-2027';

#26.10.2026 
CREATE TABLE IF NOT EXISTS directeur_activite_taches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    date_tache DATE NOT NULL,
    heure_tache TIME NOT NULL,
    user_id INT DEFAULT NULL,
    users VARCHAR(255) DEFAULT NULL,
    description_taches TEXT NOT NULL,
    observation TEXT DEFAULT NULL,
    commentaire TEXT DEFAULT NULL,
    piece_jointe VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
ALTER TABLE directeur_activite_taches ADD COLUMN piece_jointe VARCHAR(255) DEFAULT NULL;
ALTER TABLE `directeur_activite_taches` ADD `status` ENUM('approuvee','rejeter','en attente') NOT NULL DEFAULT 'en attente' AFTER `piece_jointe`;