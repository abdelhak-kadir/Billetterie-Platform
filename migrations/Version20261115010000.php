<?php

declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261115010000 extends AbstractMigration
{
    public function getDescription(): string { return 'File transactionnelle des ventes et remboursements vers Google Sheets.'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE sheets_sync_job (id INT AUTO_INCREMENT NOT NULL, ticket_reference VARCHAR(64) NOT NULL, payload JSON NOT NULL, revision INT NOT NULL, status VARCHAR(16) NOT NULL, attempts INT NOT NULL, next_attempt_at DATETIME NOT NULL, last_error VARCHAR(255) DEFAULT NULL, updated_at DATETIME NOT NULL, synced_at DATETIME DEFAULT NULL, synced_revision INT NOT NULL, synced_target VARCHAR(64) DEFAULT NULL, UNIQUE INDEX uniq_sheets_ticket (ticket_reference), INDEX idx_sheets_due (status, next_attempt_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB');
    }
    public function down(Schema $schema): void { $this->throwIrreversibleMigrationException('Conserver les demandes et preuves de synchronisation.'); }
}
