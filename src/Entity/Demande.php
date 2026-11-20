<?php

namespace App\Entity;

use App\Repository\DemandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DemandeRepository::class)]
class Demande
{
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
    #[ORM\OneToMany(targetEntity: HistoriqueStatut::class, mappedBy: 'demande', orphanRemoval: true)]
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
        $this->statut = 'Soumise';
        $this->historiqueStatuts = new ArrayCollection();
}

    /**
     * @return Collection<int, HistoriqueStatut>
     */
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

    public function removeHistoriqueStatut(HistoriqueStatut $historiqueStatut): static
    {
        if ($this->historiqueStatuts->removeElement($historiqueStatut)) {
            // set the owning side to null (unless already changed)
            if ($historiqueStatut->getDemande() === $this) {
                $historiqueStatut->setDemande(null);
            }
        }

        return $this;
    }
    }
