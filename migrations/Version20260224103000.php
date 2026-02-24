<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260224103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add regles_tagger table for automatic teletravailleur tagging by product label';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE regles_tagger (id INT AUTO_INCREMENT NOT NULL, libelle_contains VARCHAR(255) NOT NULL, tag_teletravailleur TINYINT(1) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE regles_tagger');
    }
}
