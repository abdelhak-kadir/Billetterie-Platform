<?php

declare(strict_types=1);
namespace App\Service;
use App\Enum\StatutRepresentation;
use Doctrine\DBAL\Connection;

/** Agrégations séparées : l'historique des reventes ne multiplie pas la capacité. */
final class SalesReport
{
    public function __construct(private readonly Connection $db) {}
    public function build(int $representation = 0, int $zone = 0, string $channel = ''): array
    {
        $channel = in_array($channel, ['en_ligne', 'guichet'], true) ? $channel : '';
        $params = ['representation'=>$representation, 'zone'=>$zone, 'channel'=>$channel];
        $occupancy = $this->db->fetchAllAssociative(<<<'SQL'
SELECT r.id representation_id, e.titre, r.debut, r.statut, z.id zone_id, z.nom zone,
       COUNT(DISTINCT i.id) capacity,
       COUNT(DISTINCT CASE WHEN i.statut='vendue' AND b.statut='valide' AND (:channel='' OR c.canal=:channel) THEN i.id END) sold
FROM representation r JOIN evenement e ON e.id=r.evenement_id
JOIN inventaire_place i ON i.representation_id=r.id
JOIN place p ON p.id=i.place_id JOIN zone z ON z.id=p.zone_id
LEFT JOIN billet b ON b.inventaire_place_id=i.id
LEFT JOIN commande c ON c.id=b.commande_id
WHERE (:representation=0 OR r.id=:representation) AND (:zone=0 OR z.id=:zone)
GROUP BY r.id, e.titre, r.debut, r.statut, z.id, z.nom ORDER BY r.debut, r.id, z.id
SQL, $params);
        foreach ($occupancy as &$row) {
            $row['debut'] = new \DateTimeImmutable($row['debut'], new \DateTimeZone('UTC'));
            $row['sold'] = (int) $row['sold']; $row['capacity'] = (int) $row['capacity'];
            $row['percent'] = $row['capacity'] > 0 ? round(100 * $row['sold'] / $row['capacity'], 2) : 0;
        } unset($row);
        $revenue = $this->db->fetchAllAssociative(<<<'SQL'
SELECT r.id representation_id, e.titre, r.debut, t.id tariff_id, t.libelle, t.categorie,
       SUM(CASE WHEN c.paye_a IS NOT NULL THEN 1 ELSE 0 END) issued,
       SUM(CASE WHEN c.paye_a IS NOT NULL AND b.statut='valide' THEN 1 ELSE 0 END) valid,
       COALESCE(SUM(CASE WHEN c.paye_a IS NOT NULL THEN b.prix_paye_centimes ELSE 0 END),0) gross,
       COALESCE(SUM(CASE WHEN c.paye_a IS NOT NULL THEN b.montant_rembourse_centimes ELSE 0 END),0) refunded
FROM tarif t JOIN representation r ON r.id=t.representation_id JOIN evenement e ON e.id=r.evenement_id
LEFT JOIN billet b ON b.tarif_id=t.id
LEFT JOIN inventaire_place i ON i.id=b.inventaire_place_id
LEFT JOIN place p ON p.id=i.place_id
LEFT JOIN commande c ON c.id=b.commande_id AND (:channel='' OR c.canal=:channel)
WHERE (:representation=0 OR r.id=:representation) AND (:zone=0 OR p.zone_id=:zone)
GROUP BY r.id, e.titre, r.debut, t.id, t.libelle, t.categorie ORDER BY r.debut, r.id, t.id
SQL, $params);
        $totals = ['gross'=>0, 'refunded'=>0, 'net'=>0, 'sold'=>0, 'capacity'=>0, 'percent'=>0];
        foreach ($revenue as &$row) {
            $row['debut'] = new \DateTimeImmutable($row['debut'], new \DateTimeZone('UTC'));
            foreach (['gross', 'refunded', 'issued', 'valid'] as $key) { $row[$key] = (int) $row[$key]; }
            $row['net'] = $row['gross'] - $row['refunded'];
            foreach (['gross','refunded','net'] as $key) { $totals[$key] += $row[$key]; }
        } unset($row);
        $representations = [];
        foreach ($occupancy as $row) {
            foreach (['sold','capacity'] as $key) { $totals[$key] += $row[$key]; }
            $representations[$row['representation_id']] ??= ['id'=>(int)$row['representation_id'], 'title'=>$row['titre'], 'date'=>$row['debut'], 'status'=>StatutRepresentation::from($row['statut'])->label(), 'sold'=>0, 'capacity'=>0];
            foreach (['sold','capacity'] as $key) { $representations[$row['representation_id']][$key] += $row[$key]; }
        }
        foreach ($representations as &$row) { $row['percent'] = $row['capacity'] > 0 ? round(100*$row['sold']/$row['capacity'],2) : 0; } unset($row);
        $totals['percent'] = $totals['capacity'] > 0 ? round(100*$totals['sold']/$totals['capacity'],2) : 0;
        return compact('occupancy','revenue','totals','representations');
    }
}
