<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260224094500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align utilisateurs.profil column with Doctrine mapping (remove DB default)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateurs CHANGE profil profil VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE utilisateurs CHANGE profil profil VARCHAR(255) NOT NULL DEFAULT 'public'");
    }
}
