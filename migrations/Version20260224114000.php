<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260224114000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make commandes.utilisateur_id mandatory and backfill legacy null values';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            INSERT INTO utilisateurs (login, password, roles, actif, profil)
            SELECT 'system-commandes-legacy@caramel.local', '!legacy-password!', '["ROLE_AGENT"]', 0, 'public'
            WHERE NOT EXISTS (
                SELECT 1 FROM utilisateurs WHERE login = 'system-commandes-legacy@caramel.local'
            )
        SQL);
        $this->addSql(<<<'SQL'
            UPDATE commandes
            SET utilisateur_id = (
                SELECT id FROM utilisateurs WHERE login = 'system-commandes-legacy@caramel.local' LIMIT 1
            )
            WHERE utilisateur_id IS NULL
        SQL);
        $this->addSql('ALTER TABLE commandes CHANGE utilisateur_id utilisateur_id INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commandes CHANGE utilisateur_id utilisateur_id INT DEFAULT NULL');
    }
}
