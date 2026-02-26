<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Replace legacy commande status refusee by annulee';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("UPDATE commandes SET statut = 'annulee' WHERE statut = 'refusee'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE commandes SET statut = 'refusee' WHERE statut = 'annulee'");
    }
}
