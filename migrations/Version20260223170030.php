<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260223170030 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add profil column to utilisateurs table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE utilisateurs ADD profil VARCHAR(255) NOT NULL DEFAULT 'public'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE utilisateurs DROP profil');
    }
}
