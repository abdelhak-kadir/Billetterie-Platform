<?php
declare(strict_types=1);
namespace App\Presentation;
use Doctrine\DBAL\{Connection,ArrayParameterType};

final class EventOverview
{
    public function __construct(private readonly Connection $db) {}
    public function forEvents(array $events): array
    {
        if ($events === []) { return []; }
        $ids = array_map(static fn($event)=>(int)$event->getId(),$events);
        $rows = $this->db->fetchAllAssociative("SELECT r.evenement_id,COUNT(DISTINCT r.id) AS shows,COUNT(i.id) AS capacity,COALESCE(SUM(i.statut='vendue'),0) AS sold,COALESCE(SUM(i.statut='disponible'),0) AS available FROM representation r LEFT JOIN inventaire_place i ON i.representation_id=r.id WHERE r.evenement_id IN (?) GROUP BY r.evenement_id",[$ids],[ArrayParameterType::INTEGER]);
        $result = [];
        foreach ($rows as $row) { $result[$row['evenement_id']] = array_map('intval',$row); }
        return $result;
    }
}
