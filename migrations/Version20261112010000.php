<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261112010000 extends AbstractMigration
{
    public function getDescription(): string { return 'Persist reservation deadline and immutable accepted prices'; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE commande ADD reservation_expire_a DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_commande_expiration ON commande (reservation_expire_a)');
        $this->addSql('CREATE TABLE reservation_item (id INT AUTO_INCREMENT NOT NULL, commande_id INT NOT NULL, inventaire_place_id INT NOT NULL, tarif_id INT NOT NULL, prix_centimes INT NOT NULL, INDEX IDX_RES_ORDER (commande_id), INDEX IDX_RES_INVENTORY (inventaire_place_id), INDEX IDX_RES_TARIFF (tarif_id), UNIQUE INDEX uniq_reservation_order_inventory (commande_id, inventaire_place_id), PRIMARY KEY(id), CONSTRAINT chk_reservation_price CHECK (prix_centimes >= 0)) DEFAULT CHARACTER SET utf8mb4 ENGINE=InnoDB');
        $this->addSql('ALTER TABLE reservation_item ADD CONSTRAINT FK_RES_ORDER FOREIGN KEY (commande_id) REFERENCES commande(id) ON DELETE RESTRICT, ADD CONSTRAINT FK_RES_INVENTORY FOREIGN KEY (inventaire_place_id) REFERENCES inventaire_place(id) ON DELETE RESTRICT, ADD CONSTRAINT FK_RES_TARIFF FOREIGN KEY (tarif_id) REFERENCES tarif(id) ON DELETE RESTRICT');
    }
    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE reservation_item');
        $this->addSql('DROP INDEX idx_commande_expiration ON commande');
        $this->addSql('ALTER TABLE commande DROP reservation_expire_a');
    }
}
