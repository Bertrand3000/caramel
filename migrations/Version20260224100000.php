<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260224100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove redundant produits.is_teletravailleur and keep tag_teletravailleur as single source';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE produits SET tag_teletravailleur = 1 WHERE is_teletravailleur = 1');
        $this->addSql('ALTER TABLE produits DROP is_teletravailleur');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE produits ADD is_teletravailleur TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('UPDATE produits SET is_teletravailleur = tag_teletravailleur');
    }
}
