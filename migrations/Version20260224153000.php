<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260224153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Set default stock to 1 for new products and backfill available products at 0';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE produits SET quantite = 1 WHERE statut = 'disponible' AND quantite = 0");
        $this->addSql('ALTER TABLE produits CHANGE quantite quantite INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE produits CHANGE quantite quantite INT DEFAULT 0 NOT NULL');
    }
}

