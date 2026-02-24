<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260223125646 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE reservations_temporaires (id INT AUTO_INCREMENT NOT NULL, quantite INT NOT NULL, session_id VARCHAR(255) NOT NULL, expire_at DATETIME NOT NULL, produit_id INT NOT NULL, INDEX IDX_593DECD0F347EFB (produit_id), INDEX idx_reservation_temp_session_id (session_id), INDEX idx_reservation_temp_expire_at (expire_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE reservations_temporaires ADD CONSTRAINT FK_593DECD0F347EFB FOREIGN KEY (produit_id) REFERENCES produits (id)');
        $this->addSql('ALTER TABLE produits ADD is_teletravailleur TINYINT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE reservations_temporaires DROP FOREIGN KEY FK_593DECD0F347EFB');
        $this->addSql('DROP TABLE reservations_temporaires');
        $this->addSql('ALTER TABLE produits DROP is_teletravailleur');
    }
}
