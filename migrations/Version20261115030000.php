<?php

declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261115030000 extends AbstractMigration
{
    public function getDescription(): string { return 'Noms des spectacles et catégories de places par spectacle ; données existantes conservées.'; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE representation ADD nom VARCHAR(150) DEFAULT NULL');
        $this->addSql('CREATE TABLE seat_category (id INT AUTO_INCREMENT NOT NULL, representation_id INT NOT NULL, code VARCHAR(24) NOT NULL, nom VARCHAR(80) NOT NULL, INDEX IDX_SEAT_CATEGORY_SHOW (representation_id), UNIQUE INDEX uniq_seat_category_show_code (representation_id, code), PRIMARY KEY(id), CONSTRAINT FK_SEAT_CATEGORY_SHOW FOREIGN KEY (representation_id) REFERENCES representation (id) ON DELETE RESTRICT) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE=InnoDB');
        $this->addSql('ALTER TABLE inventaire_place ADD seat_category_id INT DEFAULT NULL, ADD INDEX IDX_INVENTORY_CATEGORY (seat_category_id), ADD CONSTRAINT FK_INVENTORY_CATEGORY FOREIGN KEY (seat_category_id) REFERENCES seat_category (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE tarif ADD seat_category_id INT DEFAULT NULL, ADD INDEX IDX_TARIFF_CATEGORY (seat_category_id), ADD CONSTRAINT FK_TARIFF_CATEGORY FOREIGN KEY (seat_category_id) REFERENCES seat_category (id) ON DELETE RESTRICT');
    }
    public function down(Schema $schema): void { $this->throwIrreversibleMigrationException('Conserver les catégories liées aux ventes.'); }
}
