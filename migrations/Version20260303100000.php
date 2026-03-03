<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260303100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajout de la table agent_eligible pour la liste blanche des agents';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE agent_eligible (id INT AUTO_INCREMENT NOT NULL, numero_agent VARCHAR(5) NOT NULL, UNIQUE INDEX uniq_agent_eligible_numero_agent (numero_agent), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE agent_eligible');
    }
}
