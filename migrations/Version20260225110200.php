<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260225110200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Align creneaux foreign key/index names for jour_livraison relation';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE creneaux DROP FOREIGN KEY FK_1C973CE22FAFCA0C');
        $this->addSql('DROP INDEX IDX_1C973CE22FAFCA0C ON creneaux');
        $this->addSql('ALTER TABLE creneaux ADD CONSTRAINT FK_77F13C6DDA2C5F96 FOREIGN KEY (jour_livraison_id) REFERENCES jours_livraison (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_77F13C6DDA2C5F96 ON creneaux (jour_livraison_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE creneaux DROP FOREIGN KEY FK_77F13C6DDA2C5F96');
        $this->addSql('DROP INDEX IDX_77F13C6DDA2C5F96 ON creneaux');
        $this->addSql('ALTER TABLE creneaux ADD CONSTRAINT FK_1C973CE22FAFCA0C FOREIGN KEY (jour_livraison_id) REFERENCES jours_livraison (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_1C973CE22FAFCA0C ON creneaux (jour_livraison_id)');
    }
}
