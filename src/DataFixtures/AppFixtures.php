<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\{Billet, Commande, InventairePlace, Place, Salle, Zone};
use App\Enum\{CanalCommande, CategorieTarif};
use App\Service\DemoProgram;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/** Jeu autonome : plan, programme, tarifs, puis ventes ; aucune purge ici. */
final class AppFixtures extends Fixture
{
    public function __construct(private readonly DemoProgram $program)
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

        $manager->flush();
        $representations = [];
        foreach (['concert', 'theatre', 'conference'] as $program) {
            $event = $this->program->create($program, $salle);
            foreach ($event->getRepresentations() as $representation) {
                $code = 'R'.(count($representations) + 1);
                $representations[$code] = $representation;
                $this->addReference($code, $representation);
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
                $tarif = null;
                foreach ($representations[$code]->getTarifs() as $candidate) {
                    if ($candidate->getCategorie() === $categorie && $candidate->appliesTo($inventaire)) { $tarif = $candidate; break; }
                }
                if ($tarif === null) { throw new \LogicException('Tarif fictif manquant pour cette catégorie de siège.'); }
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
