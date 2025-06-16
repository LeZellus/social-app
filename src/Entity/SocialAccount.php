<?php

namespace App\Entity;

use App\Repository\SocialAccountRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SocialAccountRepository::class)]
class SocialAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'socialAccounts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    private ?string $platform = null; // 'reddit', 'twitter'

    #[ORM\Column(length: 255)]
    private ?string $accountName = null; // nom d'utilisateur sur la plateforme

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $accessToken = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $refreshToken = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $tokenExpiresAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private bool $isActive = true;

    /**
     * @var Collection<int, PostPublication>
     */
    #[ORM\OneToMany(targetEntity: PostPublication::class, mappedBy: 'socialAccount', orphanRemoval: true)]
    private Collection $postPublications;

    /**
     * @var Collection<int, Destination>
     */
    #[ORM\OneToMany(targetEntity: Destination::class, mappedBy: 'socialAccount', orphanRemoval: true)]
    private Collection $destinations;

    public function __construct()
    {
        $this->postPublications = new ArrayCollection();
        $this->destinations = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getPlatform(): ?string
    {
        return $this->platform;
    }

    public function setPlatform(string $platform): static
    {
        $this->platform = $platform;
        return $this;
    }

    public function getAccountName(): ?string
    {
        return $this->accountName;
    }

    public function setAccountName(string $accountName): static
    {
        $this->accountName = $accountName;
        return $this;
    }

    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    public function setAccessToken(?string $accessToken): static
    {
        $this->accessToken = $accessToken;
        return $this;
    }

    public function getRefreshToken(): ?string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(?string $refreshToken): static
    {
        $this->refreshToken = $refreshToken;
        return $this;
    }

    public function getTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->tokenExpiresAt;
    }

    public function setTokenExpiresAt(?\DateTimeImmutable $tokenExpiresAt): static
    {
        $this->tokenExpiresAt = $tokenExpiresAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    /**
     * @return Collection<int, PostPublication>
     */
    public function getPostPublications(): Collection
    {
        return $this->postPublications;
    }

    public function addPostPublication(PostPublication $postPublication): static
    {
        if (!$this->postPublications->contains($postPublication)) {
            $this->postPublications->add($postPublication);
            $postPublication->setSocialAccount($this);
        }

        return $this;
    }

    public function removePostPublication(PostPublication $postPublication): static
    {
        if ($this->postPublications->removeElement($postPublication)) {
            if ($postPublication->getSocialAccount() === $this) {
                $postPublication->setSocialAccount(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Destination>
     */
    public function getDestinations(): Collection
    {
        return $this->destinations;
    }

    public function addDestination(Destination $destination): static
    {
        if (!$this->destinations->contains($destination)) {
            $this->destinations->add($destination);
            $destination->setSocialAccount($this);
        }

        return $this;
    }

    public function removeDestination(Destination $destination): static
    {
        if ($this->destinations->removeElement($destination)) {
            if ($destination->getSocialAccount() === $this) {
                $destination->setSocialAccount(null);
            }
        }

        return $this;
    }
}