<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Crée le schéma métier initial.
 *
 * Garanties SQL : clés étrangères RESTRICT, unicités, index et CHECK.
 * Les invariants inter-tables (place/salle, tarif/représentation) restent
 * contrôlés par l'application : ils ne peuvent pas être exprimés par un CHECK MySQL.
 */
final class Version20261111170902 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create ticketing schema with integrity constraints';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE billet (id INT AUTO_INCREMENT NOT NULL, reference VARCHAR(64) NOT NULL, prix_paye_centimes INT NOT NULL, statut VARCHAR(20) NOT NULL, emis_a DATETIME NOT NULL, annule_a DATETIME DEFAULT NULL, statut_remboursement VARCHAR(20) NOT NULL, montant_rembourse_centimes INT NOT NULL, rembourse_a DATETIME DEFAULT NULL, inventaire_valide_id INT GENERATED ALWAYS AS (CASE WHEN statut = \'valide\' THEN inventaire_place_id ELSE NULL END) STORED, commande_id INT NOT NULL, inventaire_place_id INT NOT NULL, tarif_id INT NOT NULL, INDEX idx_billet_commande (commande_id), INDEX idx_billet_inventaire_place (inventaire_place_id), INDEX idx_billet_tarif (tarif_id), UNIQUE INDEX uniq_billet_reference (reference), UNIQUE INDEX uniq_billet_inventaire_valide (inventaire_valide_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE commande (id INT AUTO_INCREMENT NOT NULL, reference VARCHAR(64) NOT NULL, email_client VARCHAR(180) NOT NULL, canal VARCHAR(20) NOT NULL, statut VARCHAR(20) NOT NULL, cree_a DATETIME NOT NULL, paye_a DATETIME DEFAULT NULL, UNIQUE INDEX uniq_commande_reference (reference), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE evenement (id INT AUTO_INCREMENT NOT NULL, titre VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, type VARCHAR(20) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE inventaire_place (id INT AUTO_INCREMENT NOT NULL, statut VARCHAR(20) NOT NULL, reservation_expire_a DATETIME DEFAULT NULL, representation_id INT NOT NULL, place_id INT NOT NULL, commande_reservation_id INT DEFAULT NULL, INDEX idx_inventaire_place_place (place_id), INDEX idx_inventaire_commande_reservation (commande_reservation_id), UNIQUE INDEX uniq_inventaire_representation_place (representation_id, place_id), INDEX IDX_9FDC29AF46CE82F4 (representation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE place (id INT AUTO_INCREMENT NOT NULL, rangee VARCHAR(20) NOT NULL, numero INT NOT NULL, zone_id INT NOT NULL, UNIQUE INDEX uniq_place_zone_rangee_numero (zone_id, rangee, numero), INDEX IDX_741D53CD9F2C3FAB (zone_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE representation (id INT AUTO_INCREMENT NOT NULL, debut DATETIME NOT NULL, statut VARCHAR(20) NOT NULL, evenement_id INT NOT NULL, salle_id INT NOT NULL, INDEX idx_representation_evenement (evenement_id), INDEX idx_representation_salle (salle_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE salle (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(255) NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE tarif (id INT AUTO_INCREMENT NOT NULL, libelle VARCHAR(100) NOT NULL, categorie VARCHAR(20) NOT NULL, prix_centimes INT NOT NULL, actif TINYINT NOT NULL, representation_id INT NOT NULL, UNIQUE INDEX uniq_tarif_representation_libelle (representation_id, libelle), INDEX IDX_E7189C946CE82F4 (representation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('CREATE TABLE zone (id INT AUTO_INCREMENT NOT NULL, nom VARCHAR(100) NOT NULL, salle_id INT NOT NULL, UNIQUE INDEX uniq_zone_salle_nom (salle_id, nom), INDEX IDX_A0EBC007DC304035 (salle_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB');
        $this->addSql('ALTER TABLE billet ADD CONSTRAINT FK_1F034AF682EA2E54 FOREIGN KEY (commande_id) REFERENCES commande (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE billet ADD CONSTRAINT FK_1F034AF67F46DEE6 FOREIGN KEY (inventaire_place_id) REFERENCES inventaire_place (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE billet ADD CONSTRAINT FK_1F034AF6357C0A59 FOREIGN KEY (tarif_id) REFERENCES tarif (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE inventaire_place ADD CONSTRAINT FK_9FDC29AF46CE82F4 FOREIGN KEY (representation_id) REFERENCES representation (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE inventaire_place ADD CONSTRAINT FK_9FDC29AFDA6A219 FOREIGN KEY (place_id) REFERENCES place (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE inventaire_place ADD CONSTRAINT FK_9FDC29AF7D69983D FOREIGN KEY (commande_reservation_id) REFERENCES commande (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE place ADD CONSTRAINT FK_741D53CD9F2C3FAB FOREIGN KEY (zone_id) REFERENCES zone (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE representation ADD CONSTRAINT FK_29D5499EFD02F13 FOREIGN KEY (evenement_id) REFERENCES evenement (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE representation ADD CONSTRAINT FK_29D5499EDC304035 FOREIGN KEY (salle_id) REFERENCES salle (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE tarif ADD CONSTRAINT FK_E7189C946CE82F4 FOREIGN KEY (representation_id) REFERENCES representation (id) ON DELETE RESTRICT');
        $this->addSql('ALTER TABLE zone ADD CONSTRAINT FK_A0EBC007DC304035 FOREIGN KEY (salle_id) REFERENCES salle (id) ON DELETE RESTRICT');

        $this->addSql('ALTER TABLE salle ADD CONSTRAINT chk_salle_nom_non_vide CHECK (nom <> \'\')');
        $this->addSql('ALTER TABLE zone ADD CONSTRAINT chk_zone_nom_non_vide CHECK (nom <> \'\')');
        $this->addSql('ALTER TABLE place ADD CONSTRAINT chk_place_rangee_non_vide CHECK (rangee <> \'\')');
        $this->addSql('ALTER TABLE place ADD CONSTRAINT chk_place_numero_positif CHECK (numero > 0)');
        $this->addSql('ALTER TABLE evenement ADD CONSTRAINT chk_evenement_titre_non_vide CHECK (titre <> \'\')');
        $this->addSql('ALTER TABLE evenement ADD CONSTRAINT chk_evenement_type CHECK (type IN (\'concert\', \'theatre\', \'conference\'))');
        $this->addSql('ALTER TABLE representation ADD CONSTRAINT chk_representation_statut CHECK (statut IN (\'programmee\', \'annulee\', \'terminee\'))');
        $this->addSql('ALTER TABLE inventaire_place ADD CONSTRAINT chk_inventaire_statut CHECK (statut IN (\'disponible\', \'reservee\', \'vendue\', \'bloquee\'))');
        $this->addSql('ALTER TABLE inventaire_place ADD CONSTRAINT chk_inventaire_reservation_coherence CHECK ((statut = \'reservee\' AND commande_reservation_id IS NOT NULL AND reservation_expire_a IS NOT NULL) OR (statut <> \'reservee\' AND commande_reservation_id IS NULL AND reservation_expire_a IS NULL))');
        $this->addSql('ALTER TABLE tarif ADD CONSTRAINT chk_tarif_libelle_non_vide CHECK (libelle <> \'\')');
        $this->addSql('ALTER TABLE tarif ADD CONSTRAINT chk_tarif_categorie CHECK (categorie IN (\'plein\', \'reduit\', \'groupe\', \'invitation\'))');
        $this->addSql('ALTER TABLE tarif ADD CONSTRAINT chk_tarif_prix_positif CHECK (prix_centimes >= 0)');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT chk_commande_reference_non_vide CHECK (reference <> \'\')');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT chk_commande_canal CHECK (canal IN (\'en_ligne\', \'guichet\'))');
        $this->addSql('ALTER TABLE commande ADD CONSTRAINT chk_commande_statut CHECK (statut IN (\'en_attente\', \'payee\', \'echouee\', \'expiree\', \'annulee\'))');
        $this->addSql('ALTER TABLE billet ADD CONSTRAINT chk_billet_reference_non_vide CHECK (reference <> \'\')');
        $this->addSql('ALTER TABLE billet ADD CONSTRAINT chk_billet_statut CHECK (statut IN (\'valide\', \'annule\'))');
        $this->addSql('ALTER TABLE billet ADD CONSTRAINT chk_billet_statut_remboursement CHECK (statut_remboursement IN (\'sans_objet\', \'a_rembourser\', \'rembourse\'))');
        $this->addSql('ALTER TABLE billet ADD CONSTRAINT chk_billet_prix_paye_positif CHECK (prix_paye_centimes >= 0)');
        $this->addSql('ALTER TABLE billet ADD CONSTRAINT chk_billet_montant_rembourse_positif CHECK (montant_rembourse_centimes >= 0)');
        $this->addSql('ALTER TABLE billet ADD CONSTRAINT chk_billet_remboursement_plafonne CHECK (montant_rembourse_centimes <= prix_paye_centimes)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE billet DROP FOREIGN KEY FK_1F034AF682EA2E54');
        $this->addSql('ALTER TABLE billet DROP FOREIGN KEY FK_1F034AF67F46DEE6');
        $this->addSql('ALTER TABLE billet DROP FOREIGN KEY FK_1F034AF6357C0A59');
        $this->addSql('ALTER TABLE inventaire_place DROP FOREIGN KEY FK_9FDC29AF46CE82F4');
        $this->addSql('ALTER TABLE inventaire_place DROP FOREIGN KEY FK_9FDC29AFDA6A219');
        $this->addSql('ALTER TABLE inventaire_place DROP FOREIGN KEY FK_9FDC29AF7D69983D');
        $this->addSql('ALTER TABLE place DROP FOREIGN KEY FK_741D53CD9F2C3FAB');
        $this->addSql('ALTER TABLE representation DROP FOREIGN KEY FK_29D5499EFD02F13');
        $this->addSql('ALTER TABLE representation DROP FOREIGN KEY FK_29D5499EDC304035');
        $this->addSql('ALTER TABLE tarif DROP FOREIGN KEY FK_E7189C946CE82F4');
        $this->addSql('ALTER TABLE zone DROP FOREIGN KEY FK_A0EBC007DC304035');
        $this->addSql('DROP TABLE billet');
        $this->addSql('DROP TABLE commande');
        $this->addSql('DROP TABLE evenement');
        $this->addSql('DROP TABLE inventaire_place');
        $this->addSql('DROP TABLE place');
        $this->addSql('DROP TABLE representation');
        $this->addSql('DROP TABLE salle');
        $this->addSql('DROP TABLE tarif');
        $this->addSql('DROP TABLE zone');
    }
}
