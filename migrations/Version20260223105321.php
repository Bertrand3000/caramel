<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260223105321 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE bons_livraison (id INT AUTO_INCREMENT NOT NULL, date_impression DATETIME NOT NULL, signe TINYINT NOT NULL, commande_id INT NOT NULL, UNIQUE INDEX UNIQ_3B32E0B882EA2E54 (commande_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE commande_contacts_tmp (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, telephone VARCHAR(30) NOT NULL, import_batch_id VARCHAR(100) NOT NULL, imported_at DATETIME NOT NULL, commande_id INT NOT NULL, UNIQUE INDEX UNIQ_77B6840382EA2E54 (commande_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE commandes (id INT AUTO_INCREMENT NOT NULL, numero_agent VARCHAR(5) DEFAULT NULL, nom VARCHAR(120) DEFAULT NULL, prenom VARCHAR(120) DEFAULT NULL, date_validation DATETIME DEFAULT NULL, statut VARCHAR(255) NOT NULL, utilisateur_id INT NOT NULL, creneau_id INT DEFAULT NULL, INDEX IDX_35D4282CFB88E14F (utilisateur_id), INDEX IDX_35D4282C7D0729A9 (creneau_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE creneaux (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, heure_debut TIME NOT NULL, heure_fin TIME NOT NULL, capacite_max INT NOT NULL, capacite_utilisee INT NOT NULL, type VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE lignes_commande (id INT AUTO_INCREMENT NOT NULL, commande_id INT NOT NULL, produit_id INT NOT NULL, INDEX IDX_DAAE0FCB82EA2E54 (commande_id), INDEX IDX_DAAE0FCBF347EFB (produit_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE lignes_panier (id INT AUTO_INCREMENT NOT NULL, panier_id INT NOT NULL, produit_id INT NOT NULL, INDEX IDX_ECBFA351F77D927C (panier_id), INDEX IDX_ECBFA351F347EFB (produit_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE paniers (id INT AUTO_INCREMENT NOT NULL, date_expiration DATETIME NOT NULL, utilisateur_id INT NOT NULL, UNIQUE INDEX UNIQ_48999036FB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE parametres (id INT AUTO_INCREMENT NOT NULL, cle VARCHAR(100) NOT NULL, valeur VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_1A79799D41401D17 (cle), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE partenaires (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, contact VARCHAR(255) DEFAULT NULL, utilisateur_id INT NOT NULL, UNIQUE INDEX UNIQ_D230102EFB88E14F (utilisateur_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE produits (id INT AUTO_INCREMENT NOT NULL, numero_inventaire VARCHAR(255) DEFAULT NULL, libelle VARCHAR(255) NOT NULL, photo_produit VARCHAR(255) NOT NULL, photo_numero_inventaire VARCHAR(255) DEFAULT NULL, etat VARCHAR(255) NOT NULL, tag_teletravailleur TINYINT NOT NULL, etage VARCHAR(20) NOT NULL, porte VARCHAR(20) NOT NULL, largeur DOUBLE PRECISION DEFAULT NULL, hauteur DOUBLE PRECISION DEFAULT NULL, profondeur DOUBLE PRECISION DEFAULT NULL, statut VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE teletravailleurs_liste (id INT AUTO_INCREMENT NOT NULL, numero_agent VARCHAR(5) NOT NULL, UNIQUE INDEX UNIQ_F720ACA89D4B7A0 (numero_agent), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE utilisateurs (id INT AUTO_INCREMENT NOT NULL, login VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, roles JSON NOT NULL, actif TINYINT NOT NULL, UNIQUE INDEX UNIQ_497B315EAA08CB10 (login), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E0FB7336F0E3BD61CE16BA31DBBF396750 (queue_name, available_at, delivered_at, id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE bons_livraison ADD CONSTRAINT FK_3B32E0B882EA2E54 FOREIGN KEY (commande_id) REFERENCES commandes (id)');
        $this->addSql('ALTER TABLE commande_contacts_tmp ADD CONSTRAINT FK_77B6840382EA2E54 FOREIGN KEY (commande_id) REFERENCES commandes (id)');
        $this->addSql('ALTER TABLE commandes ADD CONSTRAINT FK_35D4282CFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs (id)');
        $this->addSql('ALTER TABLE commandes ADD CONSTRAINT FK_35D4282C7D0729A9 FOREIGN KEY (creneau_id) REFERENCES creneaux (id)');
        $this->addSql('ALTER TABLE lignes_commande ADD CONSTRAINT FK_DAAE0FCB82EA2E54 FOREIGN KEY (commande_id) REFERENCES commandes (id)');
        $this->addSql('ALTER TABLE lignes_commande ADD CONSTRAINT FK_DAAE0FCBF347EFB FOREIGN KEY (produit_id) REFERENCES produits (id)');
        $this->addSql('ALTER TABLE lignes_panier ADD CONSTRAINT FK_ECBFA351F77D927C FOREIGN KEY (panier_id) REFERENCES paniers (id)');
        $this->addSql('ALTER TABLE lignes_panier ADD CONSTRAINT FK_ECBFA351F347EFB FOREIGN KEY (produit_id) REFERENCES produits (id)');
        $this->addSql('ALTER TABLE paniers ADD CONSTRAINT FK_48999036FB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs (id)');
        $this->addSql('ALTER TABLE partenaires ADD CONSTRAINT FK_D230102EFB88E14F FOREIGN KEY (utilisateur_id) REFERENCES utilisateurs (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bons_livraison DROP FOREIGN KEY FK_3B32E0B882EA2E54');
        $this->addSql('ALTER TABLE commande_contacts_tmp DROP FOREIGN KEY FK_77B6840382EA2E54');
        $this->addSql('ALTER TABLE commandes DROP FOREIGN KEY FK_35D4282CFB88E14F');
        $this->addSql('ALTER TABLE commandes DROP FOREIGN KEY FK_35D4282C7D0729A9');
        $this->addSql('ALTER TABLE lignes_commande DROP FOREIGN KEY FK_DAAE0FCB82EA2E54');
        $this->addSql('ALTER TABLE lignes_commande DROP FOREIGN KEY FK_DAAE0FCBF347EFB');
        $this->addSql('ALTER TABLE lignes_panier DROP FOREIGN KEY FK_ECBFA351F77D927C');
        $this->addSql('ALTER TABLE lignes_panier DROP FOREIGN KEY FK_ECBFA351F347EFB');
        $this->addSql('ALTER TABLE paniers DROP FOREIGN KEY FK_48999036FB88E14F');
        $this->addSql('ALTER TABLE partenaires DROP FOREIGN KEY FK_D230102EFB88E14F');
        $this->addSql('DROP TABLE bons_livraison');
        $this->addSql('DROP TABLE commande_contacts_tmp');
        $this->addSql('DROP TABLE commandes');
        $this->addSql('DROP TABLE creneaux');
        $this->addSql('DROP TABLE lignes_commande');
        $this->addSql('DROP TABLE lignes_panier');
        $this->addSql('DROP TABLE paniers');
        $this->addSql('DROP TABLE parametres');
        $this->addSql('DROP TABLE partenaires');
        $this->addSql('DROP TABLE produits');
        $this->addSql('DROP TABLE teletravailleurs_liste');
        $this->addSql('DROP TABLE utilisateurs');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
