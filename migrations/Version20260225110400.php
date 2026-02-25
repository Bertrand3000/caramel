<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260225110400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remove type from jours_livraison';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_C8A833FFC54C8C93 ON jours_livraison');
        $this->addSql('ALTER TABLE jours_livraison DROP type');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE jours_livraison ADD type VARCHAR(32) NOT NULL DEFAULT 'general'");
        $this->addSql('CREATE INDEX IDX_C8A833FFC54C8C93 ON jours_livraison (type)');
    }
}
