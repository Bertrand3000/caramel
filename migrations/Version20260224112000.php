<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260224112000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Store checkout profile directly on commandes for reporting';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE commandes ADD profil_commande VARCHAR(20) NOT NULL DEFAULT 'agent'");
        $this->addSql("UPDATE commandes SET profil_commande = 'partenaire' WHERE numero_agent IS NULL OR numero_agent = ''");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commandes DROP profil_commande');
    }
}
