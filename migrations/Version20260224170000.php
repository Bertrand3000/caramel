<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260224170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Normalize stock and line quantities to binary mode (0/1)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE produits SET quantite = CASE WHEN quantite > 0 THEN 1 ELSE 0 END');
        $this->addSql('UPDATE reservations_temporaires SET quantite = 1 WHERE quantite <> 1');
        $this->addSql('UPDATE lignes_panier SET quantite = 1 WHERE quantite <> 1');
        $this->addSql('UPDATE lignes_commande SET quantite = 1 WHERE quantite <> 1');
    }

    public function down(Schema $schema): void
    {
        // Irreversible: previous multi-quantity values are intentionally collapsed to 0/1.
    }
}

