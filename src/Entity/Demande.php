<?php

namespace App\Entity;

use App\Entity\HistoriqueStatut;
use App\Repository\DemandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DemandeRepository::class)]
class Demande
{
    public const STATUT_SOUMISE = 'Soumise';
    public const STATUT_ACCEPTEE = 'Acceptée';
    public const STATUT_REFUSEE = 'Refusée';
    public const STATUT_EN_COURS = 'En cours';
    public const STATUT_EN_ATTENTE = 'En attente';
    public const STATUT_TRAITEE = 'Traitée';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $texte = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateCreation = null;

    #[ORM\Column(length: 255)]
    private ?string $statut = null;

    /**
     * @var Collection<int, HistoriqueStatut>
     */
    #[ORM\OneToMany(targetEntity: HistoriqueStatut::class, cascade: ['persist'], mappedBy: 'demande', orphanRemoval: true)]
    #[ORM\OrderBy(['date' => 'ASC'])]
    private Collection $historiqueStatuts;

    #[ORM\ManyToOne(inversedBy: 'demande')]
    #[ORM\JoinColumn(nullable: false)]

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getTexte(): ?string
    {
        return $this->texte;
    }

    public function setTexte(string $texte): static
    {
        $this->texte = $texte;

        return $this;
    }

    public function getDateCreation(): ?\DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeImmutable $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function __construct()
{
        $this->dateCreation = new \DateTimeImmutable();
        $this->statut = self::STATUT_SOUMISE;
        $this->historiqueStatuts = new ArrayCollection();
        $this->addHistoriqueStatut(new HistoriqueStatut(self::STATUT_SOUMISE));
}

    /**
     * @return Collection<int, HistoriqueStatut>
     */
    public function changerStatut(
        string $nouveauStatut,
        ?string $commentaire = null
    ): void {
        $transitionsAutorisees = [
            self::STATUT_SOUMISE => [
                self::STATUT_ACCEPTEE,
                self::STATUT_REFUSEE,
            ],
            self::STATUT_ACCEPTEE => [
                self::STATUT_EN_COURS,
                self::STATUT_EN_ATTENTE,
            ],
            self::STATUT_EN_COURS => [
                self::STATUT_EN_ATTENTE,
                self::STATUT_TRAITEE,
            ],
            self::STATUT_EN_ATTENTE => [
                self::STATUT_EN_COURS,
                self::STATUT_REFUSEE,
                self::STATUT_ACCEPTEE,
            ],
            // Traitée est un état final : aucune transition n'en part.
            self::STATUT_REFUSEE => [],
            self::STATUT_TRAITEE => [],
        ];

        if (!in_array($nouveauStatut, $transitionsAutorisees[$this->statut] ?? [], true)) {
            throw new \DomainException(
                sprintf(
                    'Transition de statut interdite : « %s » vers « %s ».',
                    $this->statut,
                    $nouveauStatut
                )
            );
        }

        $this->statut = $nouveauStatut;
        $this->addHistoriqueStatut(new HistoriqueStatut($nouveauStatut, $commentaire));
    }

    public function getHistoriqueStatuts(): Collection
    {
        return $this->historiqueStatuts;
    }

    public function addHistoriqueStatut(HistoriqueStatut $historiqueStatut): static
    {
        if (!$this->historiqueStatuts->contains($historiqueStatut)) {
            $this->historiqueStatuts->add($historiqueStatut);
            $historiqueStatut->setDemande($this);
        }

        return $this;
    }

}
