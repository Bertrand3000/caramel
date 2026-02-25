<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260225110300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align commandes.profil_commande length with mapping';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commandes CHANGE profil_commande profil_commande VARCHAR(20) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commandes CHANGE profil_commande profil_commande VARCHAR(255) NOT NULL');
    }
}
