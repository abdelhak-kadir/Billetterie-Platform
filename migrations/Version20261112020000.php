<?php

declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261112020000 extends AbstractMigration
{
    public function getDescription(): string { return 'S3: comptes et propriétaire nullable, sans appropriation des ventes historiques'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE app_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, password VARCHAR(255) NOT NULL, role VARCHAR(32) NOT NULL, UNIQUE INDEX uniq_user_email (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE commande ADD owner_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_6EEAA67D7E3C61F9 ON commande (owner_id)');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT FK_6EEAA67D7E3C61F9 FOREIGN KEY (owner_id) REFERENCES app_user (id) ON DELETE RESTRICT');
    }
    public function down(Schema $schema): void { $this->throwIrreversibleMigrationException('Conserver les comptes et la propriété de l’historique.'); }
}
