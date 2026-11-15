<?php

declare(strict_types=1);
namespace App\Sheets;
use App\Entity\Billet;

final class SaleProjection
{
    public const COLUMNS = ['reference_billet','reference_commande','evenement','representation_utc','salle','zone','rangee','place','tarif','canal','prix_paye_centimes','remboursement_centimes','net_centimes','statut_billet','vente_utc','remboursement_utc'];

    public static function fromTicket(Billet $ticket): array
    {
        $inventory = $ticket->getInventairePlace(); $rep = $inventory->getRepresentation();
        $place = $inventory->getPlace(); $order = $ticket->getCommande();
        $utc = static fn (?\DateTimeImmutable $date): string => $date?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z') ?? '';
        return array_combine(self::COLUMNS, [
            $ticket->getReference(), $order->getReference(), $rep->getEvenement()->getTitre(), $utc($rep->getDebut()),
            $rep->getSalle()->getNom(), $place->getZone()->getNom(), $place->getRangee(), $place->getNumero(),
            $ticket->getTarif()->getLibelle(), $order->getCanal()->value, $ticket->getPrixPayeCentimes(),
            $ticket->getMontantRembourseCentimes(), $ticket->getPrixPayeCentimes()-$ticket->getMontantRembourseCentimes(),
            $ticket->getStatut()->value, $utc($order->getPayeA()), $utc($ticket->getRembourseA()),
        ]);
    }
}
