<?php

declare(strict_types=1);

namespace App\Presentation;

use App\Entity\Representation;
use App\Enum\CategorieTarif;
use App\Repository\InventairePlaceRepository;

/** Projection de consultation : ni réservation, ni réinterprétation des échéances. */
final class SeatMapView
{
    public function __construct(private readonly InventairePlaceRepository $inventories) {}

    public function build(Representation $representation, ?int $zoneId = null): array
    {
        $query = $this->inventories->createQueryBuilder('i')
            ->join('i.place', 'p')->addSelect('p')->join('p.zone', 'z')->addSelect('z')
            ->leftJoin('i.seatCategory', 'c')->addSelect('c')
            ->where('i.representation = :representation')->setParameter('representation', $representation);
        if ($zoneId !== null && $zoneId > 0) {
            $query->andWhere('z.id = :zone')->setParameter('zone', $zoneId);
        }
        $prices = [];
        foreach ($representation->getTarifs() as $tariff) {
            if ($tariff->isActif() && $tariff->getCategorie() === CategorieTarif::Plein) {
                $key = $tariff->getSeatCategory()?->getId() ?? 'legacy';
                $prices[$key] = min($prices[$key] ?? PHP_INT_MAX, $tariff->getPrixCentimes());
            }
        }
        $zones = []; $available = 0; $seats = []; $categories = [];
        foreach ($query->orderBy('z.id', 'ASC')->addOrderBy('p.rangee', 'ASC')->addOrderBy('p.numero', 'ASC')->getQuery()->getResult() as $inventory) {
            $place = $inventory->getPlace(); $zone = $place->getZone();
            $status = $inventory->getStatut()->value;
            $category = $inventory->getSeatCategory();$categoryId = $category?->getId();
            $seat = [
                'id' => $inventory->getId(), 'zone' => $zone->getNom(), 'row' => $place->getRangee(),
                'number' => $place->getNumero(), 'status' => $status,
                'label' => $zone->getNom().', rangée '.$place->getRangee().', place '.$place->getNumero(),
                'statusLabel' => $status === 'disponible' ? 'libre' : $inventory->getStatut()->label(),
                'categoryId' => $categoryId, 'categoryName' => $category?->getNom() ?? '',
                'categoryCode' => $category?->getCode() ?? '', 'priceCentimes' => $prices[$categoryId ?? 'legacy'] ?? null,
            ];
            if ($category !== null) {
                $categories[$categoryId] ??= ['name'=>$category->getNom(),'code'=>$category->getCode(),'total'=>0,'price'=>$seat['priceCentimes']];
                ++$categories[$categoryId]['total'];
            }
            $zones[$zone->getId()] ??= ['name' => $zone->getNom(), 'id' => $zone->getId(), 'rows' => [], 'numbers' => [], 'total' => 0, 'available' => 0];
            $zones[$zone->getId()]['rows'][$place->getRangee()][] = $seat;
            $zones[$zone->getId()]['numbers'][$place->getNumero()] = $place->getNumero();
            ++$zones[$zone->getId()]['total'];
            $zones[$zone->getId()]['available'] += (int) ($status === 'disponible');
            $available += (int) ($status === 'disponible');
            $seats[$inventory->getId()] = $seat;
        }
        foreach ($zones as &$zone) { sort($zone['numbers'], SORT_NUMERIC); } unset($zone);
        return ['zones' => $zones, 'available' => $available, 'total' => count($seats), 'seats' => $seats, 'categories'=>$categories];
    }

    /** @return list<int> */
    public static function parseSelection(array $values): array
    {
        if (count($values) > 400) { throw new \InvalidArgumentException('Sélection limitée à 400 sièges.'); }
        $ids = [];
        foreach ($values as $value) {
            if ((!is_string($value) && !is_int($value)) || filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
                throw new \InvalidArgumentException('Identifiant de siège invalide.');
            }
            $ids[] = (int) $value;
        }
        return array_values(array_unique($ids));
    }

    public static function availableSelection(array $requested, array $map): array
    {
        return array_values(array_filter($requested, static fn (int $id): bool => ($map['seats'][$id]['status'] ?? null) === 'disponible'));
    }
}
