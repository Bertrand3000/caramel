<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260225110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create jours_livraison table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE jours_livraison (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, actif TINYINT(1) NOT NULL, heure_ouverture TIME NOT NULL, heure_fermeture TIME NOT NULL, coupure_meridienne TINYINT(1) NOT NULL, heure_coupure_debut TIME DEFAULT NULL, heure_coupure_fin TIME DEFAULT NULL, exiger_journee_pleine TINYINT(1) NOT NULL, type VARCHAR(32) NOT NULL, INDEX IDX_C8A833FFF00B1B0D (date), INDEX IDX_C8A833FFC54C8C93 (type), INDEX IDX_C8A833FFB8755515 (actif), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE jours_livraison');
    }
}
