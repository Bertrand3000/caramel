<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260223154214 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commandes ADD session_id VARCHAR(255) NOT NULL, CHANGE date_validation date_validation DATETIME NOT NULL, CHANGE utilisateur_id utilisateur_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE creneaux ADD date_heure DATETIME NOT NULL, DROP date');
        $this->addSql('ALTER TABLE lignes_commande ADD quantite INT NOT NULL');
        $this->addSql('ALTER TABLE lignes_panier ADD quantite INT NOT NULL');
        $this->addSql('ALTER TABLE paniers ADD session_id VARCHAR(255) NOT NULL, CHANGE utilisateur_id utilisateur_id INT DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_48999036613FECDF ON paniers (session_id)');
        $this->addSql('ALTER TABLE produits ADD quantite INT DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE teletravailleurs_liste ADD nom VARCHAR(120) DEFAULT NULL, ADD prenom VARCHAR(120) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commandes DROP session_id, CHANGE date_validation date_validation DATETIME DEFAULT NULL, CHANGE utilisateur_id utilisateur_id INT NOT NULL');
        $this->addSql('ALTER TABLE creneaux ADD date DATE NOT NULL, DROP date_heure');
        $this->addSql('ALTER TABLE lignes_commande DROP quantite');
        $this->addSql('ALTER TABLE lignes_panier DROP quantite');
        $this->addSql('DROP INDEX UNIQ_48999036613FECDF ON paniers');
        $this->addSql('ALTER TABLE paniers DROP session_id, CHANGE utilisateur_id utilisateur_id INT NOT NULL');
        $this->addSql('ALTER TABLE produits DROP quantite');
        $this->addSql('ALTER TABLE teletravailleurs_liste DROP nom, DROP prenom');
    }
}
