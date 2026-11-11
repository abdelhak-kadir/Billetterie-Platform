<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\StatutBillet;
use App\Enum\StatutRemboursement;
use App\Repository\BilletRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BilletRepository::class)]
#[ORM\Table(name: 'billet')]
#[ORM\UniqueConstraint(name: 'uniq_billet_reference', columns: ['reference'])]
#[ORM\UniqueConstraint(name: 'uniq_billet_inventaire_valide', columns: ['inventaire_valide_id'])]
#[ORM\Index(name: 'idx_billet_commande', columns: ['commande_id'])]
#[ORM\Index(name: 'idx_billet_inventaire_place', columns: ['inventaire_place_id'])]
#[ORM\Index(name: 'idx_billet_tarif', columns: ['tarif_id'])]
class Billet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $reference;

    #[ORM\ManyToOne(targetEntity: Commande::class, inversedBy: 'billets')]
    #[ORM\JoinColumn(name: 'commande_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Commande $commande;

    #[ORM\ManyToOne(targetEntity: InventairePlace::class)]
    #[ORM\JoinColumn(name: 'inventaire_place_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private InventairePlace $inventairePlace;

    #[ORM\ManyToOne(targetEntity: Tarif::class)]
    #[ORM\JoinColumn(name: 'tarif_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    private Tarif $tarif;

    #[ORM\Column(name: 'prix_paye_centimes')]
    private int $prixPayeCentimes;

    #[ORM\Column(length: 20, enumType: StatutBillet::class)]
    private StatutBillet $statut;

    #[ORM\Column(name: 'emis_a', type: 'datetime_immutable')]
    private \DateTimeImmutable $emisA;

    #[ORM\Column(name: 'annule_a', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $annuleA = null;

    #[ORM\Column(name: 'statut_remboursement', length: 20, enumType: StatutRemboursement::class)]
    private StatutRemboursement $statutRemboursement;

    #[ORM\Column(name: 'montant_rembourse_centimes')]
    private int $montantRembourseCentimes = 0;

    #[ORM\Column(name: 'rembourse_a', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $rembourseA = null;

    /**
     * Colonne technique générée par MySQL :
     * vaut inventaire_place_id si le billet est valide, NULL sinon.
     * L'index UNIQUE associé autorise plusieurs billets annulés pour un même
     * inventaire, mais un seul billet valide. La colonne est en lecture seule
     * côté Doctrine et son expression est déclarée dans la migration.
     */
    #[ORM\Column(
        name: 'inventaire_valide_id',
        type: 'integer',
        nullable: true,
        insertable: false,
        updatable: false,
        generated: 'ALWAYS',
    )]
    private ?int $inventaireValideId = null;

    public function __construct(
        string $reference,
        Commande $commande,
        InventairePlace $inventairePlace,
        Tarif $tarif,
        int $prixPayeCentimes,
        ?\DateTimeImmutable $emisA = null,
    ) {
        $reference = trim($reference);

        if ($reference === '' || strlen($reference) > 64) {
            throw new \InvalidArgumentException('La référence du billet est obligatoire et limitée à 64 caractères.');
        }

        if ($prixPayeCentimes < 0) {
            throw new \InvalidArgumentException('Le prix payé d’un billet ne peut pas être négatif.');
        }

        $representationInventaire = $inventairePlace->getRepresentation();
        $representationTarif = $tarif->getRepresentation();

        if ($representationInventaire->getId() !== null && $representationTarif->getId() !== null) {
            if ($representationInventaire->getId() !== $representationTarif->getId()) {
                throw new \InvalidArgumentException('Le tarif ne concerne pas la représentation du billet.');
            }
        } elseif ($representationInventaire !== $representationTarif) {
            throw new \InvalidArgumentException('Le tarif ne concerne pas la représentation du billet.');
        }

        $this->reference = $reference;
        $this->commande = $commande;
        $this->inventairePlace = $inventairePlace;
        $this->tarif = $tarif;
        $this->prixPayeCentimes = $prixPayeCentimes;
        $this->statut = StatutBillet::Valide;
        $this->emisA = ($emisA ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('UTC'));
        $this->statutRemboursement = StatutRemboursement::SansObjet;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getCommande(): Commande
    {
        return $this->commande;
    }

    public function getInventairePlace(): InventairePlace
    {
        return $this->inventairePlace;
    }

    public function getTarif(): Tarif
    {
        return $this->tarif;
    }

    public function getPrixPayeCentimes(): int
    {
        return $this->prixPayeCentimes;
    }

    public function getStatut(): StatutBillet
    {
        return $this->statut;
    }

    public function getEmisA(): \DateTimeImmutable
    {
        return $this->emisA;
    }

    public function getAnnuleA(): ?\DateTimeImmutable
    {
        return $this->annuleA;
    }

    public function getStatutRemboursement(): StatutRemboursement
    {
        return $this->statutRemboursement;
    }

    public function getMontantRembourseCentimes(): int
    {
        return $this->montantRembourseCentimes;
    }

    public function getRembourseA(): ?\DateTimeImmutable
    {
        return $this->rembourseA;
    }

    public function getInventaireValideId(): ?int
    {
        return $this->inventaireValideId;
    }

    public function annuler(?\DateTimeImmutable $annuleA = null): void
    {
        if ($this->statut !== StatutBillet::Valide) {
            throw new \LogicException('Seul un billet valide peut être annulé.');
        }

        $this->statut = StatutBillet::Annule;
        $this->annuleA = ($annuleA ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('UTC'));
        $this->statutRemboursement = $this->prixPayeCentimes > 0
            ? StatutRemboursement::ARembourser
            : StatutRemboursement::SansObjet;
    }

    public function marquerRembourse(int $montantCentimes, ?\DateTimeImmutable $rembourseA = null): void
    {
        if ($this->statutRemboursement !== StatutRemboursement::ARembourser) {
            throw new \LogicException('Ce billet n’est pas à rembourser.');
        }

        if ($montantCentimes !== $this->prixPayeCentimes || $montantCentimes < 0) {
            throw new \InvalidArgumentException('Le remboursement doit correspondre au prix payé, sans montant partiel.');
        }

        $this->statutRemboursement = StatutRemboursement::Rembourse;
        $this->montantRembourseCentimes = $montantCentimes;
        $this->rembourseA = ($rembourseA ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('UTC'));
    }
}
