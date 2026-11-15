<?php

declare(strict_types=1);
namespace App\Entity;
use Doctrine\ORM\Mapping as ORM;

/** Projection de vente durable ; les accès de la file passent par DBAL. */
#[ORM\Entity]
#[ORM\Table(name: 'sheets_sync_job')]
#[ORM\UniqueConstraint(name: 'uniq_sheets_ticket', columns: ['ticket_reference'])]
#[ORM\Index(name: 'idx_sheets_due', columns: ['status', 'next_attempt_at'])]
class SheetsSyncJob
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column] private ?int $id = null;
    #[ORM\Column(length: 64)] private string $ticketReference;
    #[ORM\Column(type: 'json')] private array $payload;
    #[ORM\Column] private int $revision;
    #[ORM\Column(length: 16)] private string $status;
    #[ORM\Column] private int $attempts;
    #[ORM\Column(type: 'datetime_immutable')] private \DateTimeImmutable $nextAttemptAt;
    #[ORM\Column(length: 255, nullable: true)] private ?string $lastError = null;
    #[ORM\Column(type: 'datetime_immutable')] private \DateTimeImmutable $updatedAt;
    #[ORM\Column(type: 'datetime_immutable', nullable: true)] private ?\DateTimeImmutable $syncedAt = null;
    #[ORM\Column] private int $syncedRevision;
    #[ORM\Column(length: 64, nullable: true)] private ?string $syncedTarget = null;
}
