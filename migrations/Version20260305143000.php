<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260305143000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligne la colonne produits.porte en VARCHAR(50)';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf(
            !$this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform,
            'Cette migration supporte uniquement MySQL/MariaDB.',
        );

        // Safe en prod même si déjà à 50 : le MODIFY rejoue la définition cible.
        $this->addSql('ALTER TABLE produits MODIFY porte VARCHAR(50) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Rollback interdit: réduire porte à 20 chars peut tronquer des données.',
        );
    }
}
