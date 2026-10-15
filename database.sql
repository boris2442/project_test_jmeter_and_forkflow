DROP DATABASE IF EXISTS minishop;
CREATE DATABASE minishop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE minishop;

CREATE TABLE produits (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nom VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    prix DECIMAL(10,2) NOT NULL,
    stock INT UNSIGNED NOT NULL DEFAULT 0,
    image VARCHAR(255) NOT NULL,
    actif TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_produits_actif (actif),
    CONSTRAINT chk_produits_prix CHECK (prix > 0),
    CONSTRAINT chk_produits_stock CHECK (stock >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE commandes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    reference VARCHAR(50) NOT NULL UNIQUE,
    nom_client VARCHAR(120) NOT NULL,
    telephone_client VARCHAR(30) NOT NULL,
    total DECIMAL(10,2) NOT NULL,
    statut VARCHAR(30) NOT NULL DEFAULT 'en_attente',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_commandes_created_at (created_at),
    CONSTRAINT chk_commandes_total CHECK (total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lignes_commande (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    commande_id INT UNSIGNED NOT NULL,
    produit_id INT UNSIGNED NOT NULL,
    nom_produit VARCHAR(150) NOT NULL,
    prix_unitaire DECIMAL(10,2) NOT NULL,
    quantite INT UNSIGNED NOT NULL,
    sous_total DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (id),
    KEY idx_lignes_commande_commande (commande_id),
    KEY idx_lignes_commande_produit (produit_id),
    CONSTRAINT fk_lignes_commande_commande FOREIGN KEY (commande_id) REFERENCES commandes (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_lignes_commande_produit FOREIGN KEY (produit_id) REFERENCES produits (id) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_lignes_quantite CHECK (quantite > 0),
    CONSTRAINT chk_lignes_prix CHECK (prix_unitaire > 0),
    CONSTRAINT chk_lignes_total CHECK (sous_total >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO produits (nom, description, prix, stock, image, actif, created_at) VALUES
('Casque Bluetooth X10', 'Casque audio sans fil, autonomie élevée et son clair pour le travail et les déplacements.', 19000.00, 18, 'images/produit-1.svg', 1, NOW()),
('Smartwatch Flex 5', 'Montre connectée élégante avec suivi d’activité, notifications et design premium.', 24000.00, 12, 'images/produit-2.svg', 1, NOW()),
('Enceinte Portable Mini', 'Enceinte compacte et résistante, idéale pour les sorties et les réunions.', 15000.00, 20, 'images/produit-3.svg', 1, NOW()),
('Clavier Ergonomique Pro', 'Clavier silencieux ultra confortable avec tactile agréable et connexion fiable.', 22000.00, 9, 'images/produit-4.svg', 1, NOW()),
('Souris Gaming Pulse', 'Souris ergonomique pour gaming avec capteur précis et boutons programmables.', 16000.00, 15, 'images/produit-5.svg', 1, NOW()),
('Sac à Dos Urbain', 'Sac fonctionnel pour le quotidien avec compartiments pratiques et finition moderne.', 12000.00, 25, 'images/produit-6.svg', 1, NOW()),
('Lampe de bureau LED', 'Lampe élégante avec éclairage doux et réglage de luminosité.', 13000.00, 17, 'images/produit-7.svg', 1, NOW()),
('Power Bank 20000mAh', 'Batterie portable fiable pour charger plusieurs appareils en déplacement.', 18000.00, 11, 'images/produit-8.svg', 1, NOW());
