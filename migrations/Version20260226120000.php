<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260226120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add GRH identity fields and make email/telephone nullable in commande_contacts_tmp';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande_contacts_tmp ADD nom_grh VARCHAR(120) DEFAULT NULL, ADD prenom_grh VARCHAR(120) DEFAULT NULL, CHANGE email email VARCHAR(255) DEFAULT NULL, CHANGE telephone telephone VARCHAR(30) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("UPDATE commande_contacts_tmp SET email = '' WHERE email IS NULL");
        $this->addSql("UPDATE commande_contacts_tmp SET telephone = '' WHERE telephone IS NULL");
        $this->addSql('ALTER TABLE commande_contacts_tmp CHANGE email email VARCHAR(255) NOT NULL, CHANGE telephone telephone VARCHAR(30) NOT NULL, DROP nom_grh, DROP prenom_grh');
    }
}
