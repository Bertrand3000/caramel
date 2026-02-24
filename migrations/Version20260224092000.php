<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260224092000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make product dimensions mandatory (largeur, hauteur, profondeur)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE produits SET largeur = 0 WHERE largeur IS NULL');
        $this->addSql('UPDATE produits SET hauteur = 0 WHERE hauteur IS NULL');
        $this->addSql('UPDATE produits SET profondeur = 0 WHERE profondeur IS NULL');
        $this->addSql('ALTER TABLE produits CHANGE largeur largeur DOUBLE PRECISION NOT NULL, CHANGE hauteur hauteur DOUBLE PRECISION NOT NULL, CHANGE profondeur profondeur DOUBLE PRECISION NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE produits CHANGE largeur largeur DOUBLE PRECISION DEFAULT NULL, CHANGE hauteur hauteur DOUBLE PRECISION DEFAULT NULL, CHANGE profondeur profondeur DOUBLE PRECISION DEFAULT NULL');
    }
}
