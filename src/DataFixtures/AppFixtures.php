<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\{Billet, Commande, Evenement, InventairePlace, Place, Salle, Tarif, Zone};
use App\Enum\{CanalCommande, CategorieTarif, TypeEvenement};
use App\Service\RepresentationInventoryService;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/** Jeu autonome : plan, programme, tarifs, puis ventes ; aucune purge ici. */
final class AppFixtures extends Fixture
{
    public function __construct(private readonly RepresentationInventoryService $inventoryService)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $salle = new Salle('Salle Horizon — démonstration');
        $manager->persist($salle);
        $places = [];
        foreach (['Parterre' => range('A', 'O'), 'Balcon' => range('A', 'E')] as $nom => $rangees) {
            $zone = new Zone($salle, $nom);
            $salle->addZone($zone);
            $manager->persist($zone);
            foreach ($rangees as $rangee) {
                for ($numero = 1; $numero <= 20; ++$numero) {
                    $place = new Place($zone, $rangee, $numero);
                    $zone->addPlace($place);
                    $manager->persist($place);
                    $places[$nom.'/'.$rangee.'/'.$numero] = $place;
                }
            }
        }

        $evenements = [];
        foreach ([
            ['Concert Horizon', TypeEvenement::Concert],
            ['Les voix de la scène', TypeEvenement::Theatre],
            ['Rencontres des savoirs', TypeEvenement::Conference],
        ] as [$titre, $type]) {
            $evenement = new Evenement($titre, $type, 'Programme fictif de démonstration.');
            $manager->persist($evenement);
            $evenements[] = $evenement;
        }
        $manager->flush();

        $representations = $tarifs = [];
        foreach ([
            'R1' => [0, '2026-12-01T19:00:00Z'],
            'R2' => [0, '2026-12-02T19:00:00Z'],
            'R3' => [1, '2026-12-03T19:00:00Z'],
            'R4' => [2, '2026-12-04T17:00:00Z'],
        ] as $code => [$index, $date]) {
            $representation = $this->inventoryService->createRepresentation(
                $evenements[$index], $salle, new \DateTimeImmutable($date),
            );
            $representations[$code] = $representation;
            $this->addReference($code, $representation);
            foreach ([
                [CategorieTarif::Plein, 10000],
                [CategorieTarif::Reduit, 8000],
                [CategorieTarif::Groupe, 6000],
                [CategorieTarif::Invitation, 0],
            ] as [$categorie, $prix]) {
                $tarif = new Tarif($representation, $categorie->label(), $categorie, $prix);
                $representation->addTarif($tarif);
                $manager->persist($tarif);
                $tarifs[$code][$categorie->value] = $tarif;
            }
        }
        $manager->flush();

        // Une commande par représentation, avec plusieurs billets pour la première.
        $ventes = [
            ['R1', CanalCommande::EnLigne, [['Parterre/A/1', CategorieTarif::Plein], ['Parterre/A/2', CategorieTarif::Plein]]],
            ['R2', CanalCommande::Guichet, [['Parterre/A/3', CategorieTarif::Reduit]]],
            ['R3', CanalCommande::Guichet, [['Balcon/A/1', CategorieTarif::Groupe], ['Balcon/A/2', CategorieTarif::Groupe]]],
            ['R4', CanalCommande::Guichet, [['Parterre/A/4', CategorieTarif::Invitation]]],
        ];
        foreach ($ventes as $index => [$code, $canal, $lignes]) {
            $date = new \DateTimeImmutable(sprintf('2026-11-10T12:0%d:00Z', $index));
            $commande = new Commande(
                'DEMO-CMD-'.$code, 'client'.($index + 1).'@example.com', $canal, $date,
            );
            $manager->persist($commande);
            foreach ($lignes as $ligne => [$clePlace, $categorie]) {
                $inventaire = $manager->getRepository(InventairePlace::class)->findOneBy([
                    'representation' => $representations[$code], 'place' => $places[$clePlace],
                ]);
                if (!$inventaire instanceof InventairePlace) {
                    throw new \LogicException('Inventaire de démonstration manquant : '.$code.' '.$clePlace);
                }
                $tarif = $tarifs[$code][$categorie->value];
                // Transition immédiate de démonstration, sans parcours de paiement.
                $inventaire->reserver($commande, $date->modify('+1 minute'));
                $billet = new Billet(
                    'DEMO-BIL-'.$code.'-'.($ligne + 1), $commande, $inventaire,
                    $tarif, $tarif->getPrixCentimes(), $date,
                );
                $manager->persist($billet);
                $inventaire->vendre();
            }
            $commande->marquerPayee($date);
        }
        $manager->flush();
    }
}
