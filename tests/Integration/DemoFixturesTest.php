<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\DataFixtures\AppFixtures;
use App\Entity\{Billet, Commande, Evenement, InventairePlace, Place, Representation, Salle, Tarif};
use App\Enum\{CanalCommande, CategorieTarif, StatutBillet, StatutCommande, StatutInventairePlace, TypeEvenement};
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class DemoFixturesTest extends KernelTestCase
{
    public function testDemoDatasetAndStandardReloadAreConsistent(): void
    {
        self::bootKernel();
        $manager = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $manager->getConnection();
        // Vérification de la connexion réelle avant la première purge et chaque recharge.
        $expectedDatabase = 'billetterie_s105_verify_test';
        $snapshots = [];
        for ($load = 0; $load < 2; ++$load) {
            self::assertSame($expectedDatabase, $connection->fetchOne('SELECT DATABASE()'), 'Refus de purger une autre base.');
            $manager->clear();
            $executor = new ORMExecutor($manager, new ORMPurger($manager));
            $executor->execute([self::getContainer()->get(AppFixtures::class)]);

            foreach ([Salle::class => 1, Place::class => 400, Evenement::class => 3,
                Representation::class => 4, InventairePlace::class => 1600,
                Tarif::class => 16, Commande::class => 4, Billet::class => 6] as $class => $count) {
                self::assertSame($count, $manager->getRepository($class)->count([]), $class);
            }
            $salle = $manager->getRepository(Salle::class)->findOneBy([]);
            self::assertSame(400, $salle->getCapacite());
            $types = array_map(static fn (Evenement $e) => $e->getType(), $manager->getRepository(Evenement::class)->findAll());
            self::assertEqualsCanonicalizing(TypeEvenement::cases(), $types);
            $representations = $manager->getRepository(Representation::class)->findBy([], ['debut' => 'ASC']);
            self::assertSame($representations[0]->getEvenement(), $representations[1]->getEvenement());
            foreach ($representations as $representation) {
                self::assertSame(400, $manager->getRepository(InventairePlace::class)->count(['representation' => $representation]));
                $categories = [];
                foreach ($representation->getTarifs() as $tarif) {
                    $categories[] = $tarif->getCategorie();
                    self::assertSame($tarif->getCategorie() === CategorieTarif::Invitation ? 0 : match ($tarif->getCategorie()) {
                        CategorieTarif::Plein => 10000, CategorieTarif::Reduit => 8000, CategorieTarif::Groupe => 6000,
                    }, $tarif->getPrixCentimes());
                }
                self::assertEqualsCanonicalizing(CategorieTarif::cases(), $categories);
            }
            $canaux = [];
            $snapshot = [];
            foreach ($manager->getRepository(Commande::class)->findBy([], ['reference' => 'ASC']) as $commande) {
                self::assertSame(StatutCommande::Payee, $commande->getStatut());
                self::assertStringEndsWith('@example.com', $commande->getEmailClient());
                self::assertGreaterThan(0, $commande->getBillets()->count());
                self::assertEquals($commande->getCreeA(), $commande->getPayeA());
                $canaux[] = $commande->getCanal();
                $snapshot[] = [$commande->getReference(), $commande->getEmailClient(), $commande->getCanal()->value,
                    $commande->getCreeA()->format('c'), $commande->getPayeA()->format('c')];
            }
            self::assertEqualsCanonicalizing(CanalCommande::cases(), array_values(array_unique($canaux, SORT_REGULAR)));
            $gratuites = 0;
            foreach ($manager->getRepository(Billet::class)->findBy([], ['reference' => 'ASC']) as $billet) {
                $inventaire = $billet->getInventairePlace();
                self::assertSame(StatutBillet::Valide, $billet->getStatut());
                self::assertSame(StatutInventairePlace::Vendue, $inventaire->getStatut());
                self::assertSame($inventaire->getRepresentation(), $billet->getTarif()->getRepresentation());
                self::assertSame($inventaire->getRepresentation()->getSalle(), $inventaire->getPlace()->getZone()->getSalle());
                self::assertSame($billet->getTarif()->getPrixCentimes(), $billet->getPrixPayeCentimes());
                if ($billet->getTarif()->getCategorie() === CategorieTarif::Invitation) {
                    self::assertSame(0, $billet->getPrixPayeCentimes());
                    ++$gratuites;
                }
                $snapshot[] = [$billet->getReference(), $billet->getCommande()->getReference(),
                    $inventaire->getPlace()->getLibelle(), $inventaire->getRepresentation()->getDebut()->format('c'),
                    $billet->getTarif()->getCategorie()->value, $billet->getPrixPayeCentimes(), $billet->getEmisA()->format('c')];
            }
            self::assertSame(1, $gratuites);
            $a1 = $manager->createQuery('SELECT p FROM App\Entity\Place p JOIN p.zone z WHERE z.nom = :zone AND p.rangee = :rangee AND p.numero = 1')
                ->setParameters(['zone' => 'Parterre', 'rangee' => 'A'])->getSingleResult();
            foreach ([0 => StatutInventairePlace::Vendue, 1 => StatutInventairePlace::Disponible] as $index => $statut) {
                $inventaire = $manager->getRepository(InventairePlace::class)->findOneBy(['representation' => $representations[$index], 'place' => $a1]);
                self::assertSame($statut, $inventaire->getStatut());
            }
            foreach ($manager->getRepository(InventairePlace::class)->findAll() as $inventaire) {
                $billets = $manager->getRepository(Billet::class)->count(['inventairePlace' => $inventaire]);
                self::assertSame($billets === 1 ? StatutInventairePlace::Vendue : StatutInventairePlace::Disponible, $inventaire->getStatut());
                self::assertLessThanOrEqual(1, $billets);
                self::assertNull($inventaire->getCommandeReservation());
                self::assertNull($inventaire->getReservationExpireA());
            }
            // Compare les lignes métier en remplaçant les clés auto-incrémentées
            // par des clés naturelles : les associations sont donc vérifiées aussi.
            $rowsByTable = [];
            foreach (['salle', 'zone', 'place', 'evenement', 'representation', 'tarif', 'inventaire_place', 'commande', 'billet'] as $table) {
                $rowsByTable[$table] = $connection->fetchAllAssociative('SELECT * FROM '.$table.' ORDER BY id');
            }

            $naturalKeys = array_fill_keys(array_keys($rowsByTable), []);

            foreach ($rowsByTable['salle'] as $row) {
                $naturalKeys['salle'][$row['id']] = $row['nom'];
            }
            foreach ($rowsByTable['zone'] as $row) {
                $naturalKeys['zone'][$row['id']] = $naturalKeys['salle'][$row['salle_id']].'|'.$row['nom'];
            }
            foreach ($rowsByTable['place'] as $row) {
                $naturalKeys['place'][$row['id']] = $naturalKeys['zone'][$row['zone_id']].'|'.$row['rangee'].'|'.$row['numero'];
            }
            foreach ($rowsByTable['evenement'] as $row) {
                $naturalKeys['evenement'][$row['id']] = $row['titre'];
            }
            foreach ($rowsByTable['representation'] as $row) {
                $naturalKeys['representation'][$row['id']] = $naturalKeys['evenement'][$row['evenement_id']]
                    .'|'.$naturalKeys['salle'][$row['salle_id']].'|'.$row['debut'];
            }
            foreach ($rowsByTable['tarif'] as $row) {
                $naturalKeys['tarif'][$row['id']] = $naturalKeys['representation'][$row['representation_id']]
                    .'|'.$row['libelle'];
            }
            foreach ($rowsByTable['commande'] as $row) {
                $naturalKeys['commande'][$row['id']] = $row['reference'];
            }
            foreach ($rowsByTable['inventaire_place'] as $row) {
                $naturalKeys['inventaire_place'][$row['id']] = $naturalKeys['representation'][$row['representation_id']]
                    .'|'.$naturalKeys['place'][$row['place_id']];
            }
            foreach ($rowsByTable['billet'] as $row) {
                $naturalKeys['billet'][$row['id']] = $row['reference'];
            }

            $foreignKeys = [
                'salle_id' => 'salle',
                'zone_id' => 'zone',
                'place_id' => 'place',
                'evenement_id' => 'evenement',
                'representation_id' => 'representation',
                'tarif_id' => 'tarif',
                'commande_id' => 'commande',
                'inventaire_place_id' => 'inventaire_place',
                'commande_reservation_id' => 'commande',
                'inventaire_valide_id' => 'inventaire_place',
            ];

            foreach ($rowsByTable as $table => $rows) {
                $snapshot[$table] = array_map(static function (array $row) use ($foreignKeys, $naturalKeys): array {
                    unset($row['id']);

                    foreach ($foreignKeys as $column => $referencedTable) {
                        if (! array_key_exists($column, $row)) {
                            continue;
                        }

                        $row[$column] = $row[$column] === null
                            ? null
                            : ($naturalKeys[$referencedTable][$row[$column]] ?? 'unknown:'.$row[$column]);
                    }

                    return $row;
                }, $rows);

                usort($snapshot[$table], static fn (array $a, array $b): int => strcmp(serialize($a), serialize($b)));
            }
            $snapshots[] = $snapshot;
        }
        self::assertSame($snapshots[0], $snapshots[1], 'La recharge doit reproduire le même jeu métier.');
        $manager->close();
    }
}
