<?php
declare(strict_types=1);
namespace App\Service;
use App\Entity\{Evenement,Salle,Representation,SeatCategory,Tarif};
use App\Enum\CategorieTarif;
use App\Form\Model\EventSetupData;
use Doctrine\ORM\EntityManagerInterface;

final class EventSetupService
{
    public function __construct(private readonly EntityManagerInterface $em, private readonly RepresentationInventoryService $inventories, private readonly EventImageStorage $images) {}

    /** Clés stables et uniques, y compris lorsque plusieurs zones utilisent la rangée A. */
    public static function rowKey(int $zoneId, string $row): string { return 'z'.$zoneId.'_'.bin2hex($row); }

    public static function rows(Salle $hall): array
    {
        $rows = [];
        foreach ($hall->getPlaces() as $place) {
            $key = self::rowKey($place->getZone()->getId(), $place->getRangee());
            $rows[$key] ??= ['label' => $place->getZone()->getNom().' · Rangée '.$place->getRangee(), 'zone' => $place->getZone()->getId(), 'count' => 0];
            ++$rows[$key]['count'];
        }
        return $rows;
    }

    public function create(EventSetupData $data, ?Evenement $existing = null): Evenement
    {
        if ($data->hall === null || $data->shows === [] || count($data->shows) > 12) { throw new \InvalidArgumentException('Choisissez une salle et un à douze spectacles.'); }
        $rows = self::rows($data->hall);
        if ($rows === []) { throw new \InvalidArgumentException('La salle doit contenir des places avant sa programmation.'); }
        if (array_diff(array_keys($data->rowCategories), array_keys($rows)) !== []) { throw new \InvalidArgumentException('Une rangée n’appartient pas à cette salle.'); }
        foreach ($data->rowCategories as $code) { if (!isset(SeatCategory::LABELS[$code])) { throw new \InvalidArgumentException('Catégorie de place inconnue.'); } }
        foreach ($data->shows as $show) {
            if (trim($show->nom ?? '') === '' || mb_strlen($show->nom) > 150 || $show->debut === null || $show->debut <= new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) { throw new \InvalidArgumentException('Chaque spectacle doit avoir un nom et une date future.'); }
            foreach ([$show->standardPrice,$show->vipPrice,$show->vvipPrice] as $price) { if ($price === null || $price < 0) { throw new \InvalidArgumentException('Indiquez un prix positif ou nul pour chaque catégorie.'); } }
        }
        $filename = null;
        try {
            if ($existing === null && $data->evenement->image !== null) { $filename = $this->images->store($data->evenement->image); }
            $event = $this->em->wrapInTransaction(function () use ($data,$existing,$rows,$filename): Evenement {
                $event = $existing ?? new Evenement($data->evenement->titre ?? '', $data->evenement->type ?? throw new \InvalidArgumentException('Choisissez le type de l’événement.'), $data->evenement->description);
                if ($existing === null) { if ($filename !== null) { $event->setImageFilename($filename); } $this->em->persist($event);$this->em->flush(); }
                foreach ($data->shows as $show) {
                    $rep = $this->inventories->createRepresentation($event,$data->hall,$show->debut);
                    $event->addRepresentation($rep);
                    $rep->nommer($show->nom);$categories = [];
                    foreach (SeatCategory::LABELS as $code => $label) {
                        $category = new SeatCategory($rep,$code);$this->em->persist($category);$categories[$code] = $category;
                        $price = $show->{$code.'Price'};
                        $tariff = new Tarif($rep,$label.' · Plein tarif',CategorieTarif::Plein,$price);$tariff->setSeatCategory($category);$rep->addTarif($tariff);$this->em->persist($tariff);
                    }
                    foreach ($rep->getInventairePlaces() as $inventory) {
                        $place = $inventory->getPlace();$key = self::rowKey($place->getZone()->getId(),$place->getRangee());
                        $inventory->classify($categories[$data->rowCategories[$key] ?? 'standard']);
                    }
                    $this->em->flush();
                }
                return $event;
            });
            $filename = null;
            return $event;
        } finally { if ($filename !== null) { $this->images->remove($filename); } }
    }
}
