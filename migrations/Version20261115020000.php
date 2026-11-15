<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115020000 extends AbstractMigration
{
    public function getDescription(): string { return 'Visuel facultatif pour les événements, sans modifier les ventes existantes.'; }
    public function up(Schema $schema): void { $this->addSql('ALTER TABLE evenement ADD image_filename VARCHAR(64) DEFAULT NULL'); }
    public function down(Schema $schema): void { $this->addSql('ALTER TABLE evenement DROP image_filename'); }
}
