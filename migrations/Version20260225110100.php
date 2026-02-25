<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260225110100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add nullable jour_livraison foreign key to creneaux';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE creneaux ADD jour_livraison_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE creneaux ADD CONSTRAINT FK_1C973CE22FAFCA0C FOREIGN KEY (jour_livraison_id) REFERENCES jours_livraison (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_1C973CE22FAFCA0C ON creneaux (jour_livraison_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE creneaux DROP FOREIGN KEY FK_1C973CE22FAFCA0C');
        $this->addSql('DROP INDEX IDX_1C973CE22FAFCA0C ON creneaux');
        $this->addSql('ALTER TABLE creneaux DROP jour_livraison_id');
    }
}
