<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CanalCommande;
use App\Enum\StatutCommande;
use App\Repository\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
#[ORM\Table(name: 'commande')]
#[ORM\UniqueConstraint(name: 'uniq_commande_reference', columns: ['reference'])]
class Commande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64)]
    private string $reference;

    #[ORM\Column(name: 'email_client', length: 180)]
    private string $emailClient;

    #[ORM\Column(length: 20, enumType: CanalCommande::class)]
    private CanalCommande $canal;

    #[ORM\Column(length: 20, enumType: StatutCommande::class)]
    private StatutCommande $statut;

    #[ORM\Column(name: 'cree_a', type: 'datetime_immutable')]
    private \DateTimeImmutable $creeA;

    #[ORM\Column(name: 'paye_a', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $payeA = null;

    /** @var Collection<int, Billet> */
    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: Billet::class)]
    private Collection $billets;

    /** @var Collection<int, InventairePlace> */
    #[ORM\OneToMany(mappedBy: 'commandeReservation', targetEntity: InventairePlace::class)]
    private Collection $reservations;

    public function __construct(string $reference, string $emailClient, CanalCommande $canal)
    {
        $reference = trim($reference);
        $emailClient = trim($emailClient);

        if ($reference === '' || strlen($reference) > 64) {
            throw new \InvalidArgumentException('La référence de commande est obligatoire et limitée à 64 caractères.');
        }

        if (filter_var($emailClient, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('L’adresse e-mail du client est invalide.');
        }

        $this->reference = $reference;
        $this->emailClient = $emailClient;
        $this->canal = $canal;
        $this->statut = StatutCommande::EnAttente;
        $this->creeA = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $this->billets = new ArrayCollection();
        $this->reservations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    public function getEmailClient(): string
    {
        return $this->emailClient;
    }

    public function getCanal(): CanalCommande
    {
        return $this->canal;
    }

    public function getStatut(): StatutCommande
    {
        return $this->statut;
    }

    public function getCreeA(): \DateTimeImmutable
    {
        return $this->creeA;
    }

    public function getPayeA(): ?\DateTimeImmutable
    {
        return $this->payeA;
    }

    /** @return Collection<int, Billet> */
    public function getBillets(): Collection
    {
        return $this->billets;
    }

    /** @return Collection<int, InventairePlace> */
    public function getReservations(): Collection
    {
        return $this->reservations;
    }

    public function marquerPayee(?\DateTimeImmutable $payeA = null): void
    {
        if ($this->statut !== StatutCommande::EnAttente) {
            throw new \LogicException('Seule une commande en attente peut être marquée payée.');
        }

        $this->statut = StatutCommande::Payee;
        $this->payeA = ($payeA ?? new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->setTimezone(new \DateTimeZone('UTC'));
    }

    public function echouer(): void
    {
        $this->terminerEnAttente(StatutCommande::Echouee);
    }

    public function expirer(): void
    {
        $this->terminerEnAttente(StatutCommande::Expiree);
    }

    public function annuler(): void
    {
        $this->terminerEnAttente(StatutCommande::Annulee);
    }

    private function terminerEnAttente(StatutCommande $statut): void
    {
        if ($this->statut !== StatutCommande::EnAttente) {
            throw new \LogicException('Seule une commande en attente peut changer vers ce statut.');
        }

        $this->statut = $statut;
    }
}
