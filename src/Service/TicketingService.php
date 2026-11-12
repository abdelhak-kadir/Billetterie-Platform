<?php

declare(strict_types=1);

namespace App\Service;

use App\Clock\Clock;
use App\Entity\{Billet, Commande, InventairePlace, Representation, ReservationItem, Tarif};
use App\Enum\{CanalCommande, CategorieTarif, StatutBillet, StatutCommande, StatutInventairePlace, StatutRemboursement, StatutRepresentation};
use App\Exception\SaleConflict;
use Doctrine\DBAL\{ArrayParameterType, LockMode};
use Doctrine\DBAL\Exception\{RetryableException, UniqueConstraintViolationException};
use Doctrine\ORM\EntityManagerInterface;

/** Toutes les écritures du cycle de vente passent par ces transactions courtes. */
final class TicketingService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Clock $clock,
        private readonly int $reservationTtlSeconds = 600,
    ) {
        if ($reservationTtlSeconds < 1) {
            throw new \InvalidArgumentException('La durée de réservation doit être un nombre positif de secondes.');
        }
    }

    /** @param list<array{inventaire: int, tarif: int}> $choices */
    public function reserve(int $representationId, array $choices, string $email, CanalCommande $canal): Commande
    {
        if ($choices === [] || count($choices) > 400) {
            throw new SaleConflict('Choisissez entre une et 400 places.');
        }
        $inventoryIds = $tariffIds = [];
        foreach ($choices as $choice) {
            if (!is_array($choice) || !is_int($choice['inventaire'] ?? null) || !is_int($choice['tarif'] ?? null)
                || $choice['inventaire'] < 1 || $choice['tarif'] < 1 || isset($inventoryIds[$choice['inventaire']])) {
                throw new SaleConflict('Les places et tarifs doivent être valides, sans place répétée.');
            }
            $inventoryIds[$choice['inventaire']] = $choice['inventaire'];
            $tariffIds[$choice['tarif']] = $choice['tarif'];
        }

        return $this->transaction(function () use ($representationId, $choices, $email, $canal, $inventoryIds, $tariffIds): Commande {
            $representation = $this->lock(Representation::class, $representationId);
            $holders = $this->ids('SELECT DISTINCT commande_reservation_id FROM inventaire_place WHERE id IN (?) AND commande_reservation_id IS NOT NULL', $inventoryIds);
            $orders = $this->lockMany(Commande::class, $holders);
            $heldIds = $holders === [] ? [] : $this->ids('SELECT id FROM inventaire_place WHERE commande_reservation_id IN (?)', $holders);
            $inventories = $this->lockMany(InventairePlace::class, array_merge($inventoryIds, $heldIds));
            $tariffs = $this->lockMany(Tarif::class, $tariffIds);
            $now = $this->now(); // Après l'acquisition de tous les verrous.
            $this->assertSaleOpen($representation, $now);
            foreach ($orders as $order) {
                $this->expireLocked($order, $inventories, $now);
            }
            foreach ($choices as $choice) {
                $inventory = $inventories[$choice['inventaire']];
                if ($inventory->getRepresentation()->getId() !== $representationId) {
                    throw new SaleConflict('Une place ne concerne pas cette représentation.');
                }
                if (!InventairePlace::appartientALaSalle($inventory->getPlace(), $representation->getSalle())) {
                    throw new SaleConflict('Une place n’appartient pas à la salle.');
                }
                if ($inventory->getStatut() !== StatutInventairePlace::Disponible) {
                    throw new SaleConflict('Une place choisie n’est plus disponible. Aucune place n’a été réservée.');
                }
                $this->assertTariff($tariffs[$choice['tarif']], $representationId);
            }
            $order = new Commande('CMD-'.bin2hex(random_bytes(12)), $email, $canal, $now);
            $deadline = $now->modify('+'.$this->reservationTtlSeconds.' seconds');
            $order->definirEcheance($deadline);
            $this->em->persist($order);
            foreach ($choices as $choice) {
                $inventory = $inventories[$choice['inventaire']];
                $tariff = $tariffs[$choice['tarif']];
                $inventory->reserver($order, $deadline);
                $item = new ReservationItem($order, $inventory, $tariff, $tariff->getPrixCentimes());
                $order->getReservationItems()->add($item);
                $this->em->persist($item);
            }
            return $order;
        });
    }

    public function confirm(int $orderId): Commande
    {
        $order = $this->transaction(function () use ($orderId): Commande {
            [$order, $inventories, $representations] = $this->lockOrderContext($orderId);
            if ($order->getPayeA() !== null) {
                return $order; // Même après annulation : ne jamais réémettre.
            }
            $items = $this->em->getRepository(ReservationItem::class)->findBy(['commande' => $order], ['id' => 'ASC']);
            $this->lockMany(Tarif::class, array_map(static fn ($i) => $i->getTarif()->getId(), $items));
            $now = $this->now();
            if ($this->expireLocked($order, $inventories, $now) || $order->getStatut() === StatutCommande::Expiree) {
                return $order; // Commit de l'expiration avant de signaler le conflit.
            }
            if ($order->getStatut() !== StatutCommande::EnAttente || $items === []) {
                throw new SaleConflict('Cette commande ne peut plus être confirmée.');
            }
            foreach ($representations as $representation) {
                $this->assertSaleOpen($representation, $now);
            }
            if (count(array_filter($inventories, fn ($i) => $this->ownedBy($i, $order))) !== count($items)) {
                throw new SaleConflict('L’ensemble des places réservées ne correspond pas à cette commande.');
            }
            foreach ($items as $item) {
                $inventory = $inventories[$item->getInventairePlace()->getId()];
                if (!$this->ownedBy($inventory, $order) || $inventory->getReservationExpireA() <= $now) {
                    throw new SaleConflict('Les places ne sont plus toutes réservées pour cette commande.');
                }
                $this->assertTariff($item->getTarif(), (int) $inventory->getRepresentation()->getId());
            }
            foreach ($items as $item) {
                $inventory = $inventories[$item->getInventairePlace()->getId()];
                $ticket = new Billet('BIL-'.bin2hex(random_bytes(12)), $order, $inventory, $item->getTarif(), $item->getPrixCentimes(), $now);
                $this->em->persist($ticket);
                $order->getBillets()->add($ticket);
                $inventory->vendre();
            }
            $order->marquerPayee($now);
            return $order;
        });
        if ($order->getStatut() === StatutCommande::Expiree) {
            throw new SaleConflict('La réservation a expiré ; aucun billet n’a été émis.');
        }
        return $order;
    }

    public function fail(int $orderId): Commande
    {
        return $this->transaction(function () use ($orderId): Commande {
            [$order, $inventories] = $this->lockOrderContext($orderId);
            if ($order->getStatut() !== StatutCommande::EnAttente) {
                return $order;
            }
            if (!$this->expireLocked($order, $inventories, $this->now())) {
                $this->releaseOwned($order, $inventories);
                $order->echouer();
            }
            return $order;
        });
    }

    public function expire(int $orderId): bool
    {
        return $this->transaction(function () use ($orderId): bool {
            [$order, $inventories] = $this->lockOrderContext($orderId);
            return $this->expireLocked($order, $inventories, $this->now());
        });
    }

    public function expireDue(): int
    {
        $ids = $this->em->getConnection()->fetchFirstColumn(
            "SELECT DISTINCT c.id FROM commande c LEFT JOIN inventaire_place i ON i.commande_reservation_id = c.id WHERE c.statut = 'en_attente' AND (c.reservation_expire_a <= ? OR i.reservation_expire_a <= ?) ORDER BY c.id",
            [$this->now()->format('Y-m-d H:i:s'), $this->now()->format('Y-m-d H:i:s')],
        );
        $count = 0;
        foreach ($ids as $id) {
            $count += (int) $this->expire((int) $id);
        }
        return $count;
    }

    public function cancel(int $orderId): Commande
    {
        return $this->transaction(function () use ($orderId): Commande {
            [$order, $inventories, $representations] = $this->lockOrderContext($orderId);
            $now = $this->now();
            if ($order->getStatut() === StatutCommande::EnAttente) {
                if (!$this->expireLocked($order, $inventories, $now)) {
                    $this->releaseOwned($order, $inventories);
                    $order->annuler();
                }
            } elseif ($order->getStatut() === StatutCommande::Payee) {
                $this->cancelTickets($order, $inventories, $representations, $now);
            }
            return $order;
        });
    }

    public function cancelRepresentation(int $representationId): void
    {
        $this->transaction(function () use ($representationId): void {
            $representation = $this->lock(Representation::class, $representationId);
            $orderIds = $this->em->getConnection()->fetchFirstColumn(
                'SELECT commande_id FROM billet b JOIN inventaire_place i ON i.id=b.inventaire_place_id WHERE i.representation_id=? UNION SELECT commande_reservation_id FROM inventaire_place WHERE representation_id=? AND commande_reservation_id IS NOT NULL',
                [$representationId, $representationId],
            );
            $orders = $this->lockMany(Commande::class, $orderIds);
            $inventoryIds = $this->em->getConnection()->fetchFirstColumn('SELECT id FROM inventaire_place WHERE representation_id=?', [$representationId]);
            if ($orderIds !== []) {
                $inventoryIds = array_merge($inventoryIds, $this->ids('SELECT id FROM inventaire_place WHERE commande_reservation_id IN (?)', $orderIds));
            }
            $inventories = $this->lockMany(InventairePlace::class, $inventoryIds);
            $now = $this->now();
            $representation->annuler();
            foreach ($orders as $order) {
                if ($order->getStatut() === StatutCommande::EnAttente) {
                    if (!$this->expireLocked($order, $inventories, $now)) {
                        $this->releaseOwned($order, $inventories);
                        $order->annuler();
                    }
                } elseif ($order->getStatut() === StatutCommande::Payee) {
                    $this->cancelTickets($order, $inventories, [$representationId => $representation], $now, $representationId);
                }
            }
            foreach ($inventories as $inventory) {
                if ($inventory->getRepresentation()->getId() === $representationId) {
                    $inventory->bloquer();
                }
            }
        });
    }

    private function now(): \DateTimeImmutable
    {
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        return $now->setTime((int) $now->format('H'), (int) $now->format('i'), (int) $now->format('s'));
    }

    private function transaction(callable $operation): mixed
    {
        $db = $this->em->getConnection();
        if ($db->isTransactionActive()) {
            throw new \LogicException('Le service de vente doit démarrer sa propre transaction.');
        }
        // Les lectures de découverte doivent voir les commits survenus pendant l'attente d'un verrou.
        $db->executeStatement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $db->beginTransaction();
        try {
            $result = $operation();
            $this->em->flush();
            $db->commit();
            return $result;
        } catch (\Throwable $error) {
            if ($db->isTransactionActive()) { $db->rollBack(); }
            $this->em->clear();
            if ($error instanceof RetryableException || $error instanceof UniqueConstraintViolationException) {
                throw new SaleConflict('Une opération concurrente a modifié ces places. Réessayez.', 0, $error);
            }
            throw $error;
        }
    }

    /** Verrou SQL et refresh explicite : ne jamais décider depuis l'identity map. */
    private function lock(string $class, int $id): object
    {
        $entity = $this->em->find($class, $id) ?? throw new SaleConflict('Objet de vente introuvable.');
        $this->em->refresh($entity, LockMode::PESSIMISTIC_WRITE);
        return $entity;
    }

    /** @return array<int, object> */
    private function lockMany(string $class, array $ids): array
    {
        $ids = array_unique(array_map('intval', $ids));
        sort($ids, SORT_NUMERIC);
        $entities = [];
        foreach ($ids as $id) { $entities[$id] = $this->lock($class, $id); }
        return $entities;
    }

    private function ids(string $sql, array $ids): array
    {
        return $this->em->getConnection()->executeQuery($sql, [array_values($ids)], [ArrayParameterType::INTEGER])->fetchFirstColumn();
    }

    private function lockOrderContext(int $orderId): array
    {
        $representationIds = $this->em->getConnection()->fetchFirstColumn(
            'SELECT i.representation_id FROM reservation_item l JOIN inventaire_place i ON i.id=l.inventaire_place_id WHERE l.commande_id=? UNION SELECT i.representation_id FROM billet b JOIN inventaire_place i ON i.id=b.inventaire_place_id WHERE b.commande_id=? UNION SELECT representation_id FROM inventaire_place WHERE commande_reservation_id=?',
            [$orderId, $orderId, $orderId],
        );
        $representations = $this->lockMany(Representation::class, $representationIds);
        $order = $this->lock(Commande::class, $orderId);
        $inventoryIds = $this->em->getConnection()->fetchFirstColumn(
            'SELECT inventaire_place_id FROM reservation_item WHERE commande_id=? UNION SELECT inventaire_place_id FROM billet WHERE commande_id=? UNION SELECT id FROM inventaire_place WHERE commande_reservation_id=?',
            [$orderId, $orderId, $orderId],
        );
        return [$order, $this->lockMany(InventairePlace::class, $inventoryIds), $representations];
    }

    private function assertSaleOpen(Representation $representation, \DateTimeImmutable $now): void
    {
        if ($representation->getStatut() !== StatutRepresentation::Programme || $representation->getDebut() <= $now) {
            throw new SaleConflict('Cette représentation n’accepte plus de ventes.');
        }
    }

    private function assertTariff(Tarif $tariff, int $representationId): void
    {
        if (!$tariff->isActif() || $tariff->getRepresentation()->getId() !== $representationId
            || ($tariff->getCategorie() === CategorieTarif::Invitation && $tariff->getPrixCentimes() !== 0)) {
            throw new SaleConflict('Le tarif est inactif ou incohérent avec la représentation.');
        }
    }

    private function ownedBy(InventairePlace $inventory, Commande $order): bool
    {
        return $inventory->getStatut() === StatutInventairePlace::Reservee
            && $inventory->getCommandeReservation()?->getId() === $order->getId();
    }

    private function releaseOwned(Commande $order, array $inventories): void
    {
        foreach ($inventories as $inventory) {
            if ($this->ownedBy($inventory, $order)) { $inventory->liberer(); }
        }
    }

    private function expireLocked(Commande $order, array $inventories, \DateTimeImmutable $now): bool
    {
        if ($order->getStatut() !== StatutCommande::EnAttente) { return false; }
        $due = $order->getReservationExpireA() !== null && $order->getReservationExpireA() <= $now;
        foreach ($inventories as $inventory) {
            $due = $due || ($this->ownedBy($inventory, $order) && $inventory->getReservationExpireA() <= $now);
        }
        if (!$due) { return false; }
        $this->releaseOwned($order, $inventories);
        $order->expirer();
        return true;
    }

    private function cancelTickets(Commande $order, array $inventories, array $representations, \DateTimeImmutable $now, ?int $onlyRepresentation = null): void
    {
        $tickets = $this->em->getRepository(Billet::class)->findBy(['commande' => $order], ['id' => 'ASC']);
        $valid = 0;
        foreach ($tickets as $ticket) {
            $this->em->refresh($ticket); // La commande verrouillée sérialise toutes ses notifications.
            $representationId = $ticket->getInventairePlace()->getRepresentation()->getId();
            if ($ticket->getStatut() === StatutBillet::Valide && ($onlyRepresentation === null || $representationId === $onlyRepresentation)) {
                $ticket->annuler($now);
                if ($ticket->getStatutRemboursement() === StatutRemboursement::ARembourser) {
                    $ticket->marquerRembourse($ticket->getPrixPayeCentimes(), $now);
                }
                $inventory = $inventories[$ticket->getInventairePlace()->getId()];
                $representation = $representations[$representationId];
                if ($representation->getStatut() === StatutRepresentation::Programme && $representation->getDebut() > $now) {
                    $inventory->remettreEnVente();
                } else { $inventory->bloquer(); }
            }
            $valid += (int) ($ticket->getStatut() === StatutBillet::Valide);
        }
        if ($valid === 0) { $order->annulerApresVente(); }
    }
}
