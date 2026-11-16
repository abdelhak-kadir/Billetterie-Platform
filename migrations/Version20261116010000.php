<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261116010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identifier le nouveau programme fictif sans modifier les événements et ventes existants.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE evenement ADD demo_key VARCHAR(80) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX uniq_evenement_demo_key ON evenement (demo_key)');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Conserver la provenance des programmes fictifs pour éviter leur duplication.');
    }
}
