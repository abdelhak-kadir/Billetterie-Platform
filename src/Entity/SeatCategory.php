<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'seat_category')]
#[ORM\Index(name: 'IDX_SEAT_CATEGORY_SHOW', columns: ['representation_id'])]
#[ORM\UniqueConstraint(name: 'uniq_seat_category_show_code', columns: ['representation_id', 'code'])]
class SeatCategory
{
    public const LABELS = ['standard' => 'Standard', 'vip' => 'VIP', 'vvip' => 'VVIP'];
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;
    #[ORM\ManyToOne(targetEntity: Representation::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'RESTRICT')]
    private Representation $representation;
    #[ORM\Column(length: 24)]
    private string $code;
    #[ORM\Column(length: 80)]
    private string $nom;

    public function __construct(Representation $representation, string $code, ?string $nom = null)
    {
        if (!isset(self::LABELS[$code])) { throw new \InvalidArgumentException('Catégorie de place inconnue.'); }
        $this->representation = $representation;
        $this->code = $code;
        $this->nom = $nom ?? self::LABELS[$code];
    }
    public function getId(): ?int { return $this->id; }
    public function getRepresentation(): Representation { return $this->representation; }
    public function getCode(): string { return $this->code; }
    public function getNom(): string { return $this->nom; }
}
