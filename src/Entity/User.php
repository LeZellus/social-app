<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column]
    private array $roles = [];

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $pseudo = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $website = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $timezone = null;

    /**
     * @var Collection<int, ApiCredentials>
     */
    #[ORM\OneToMany(targetEntity: ApiCredentials::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $apiCredentials;

    /**
     * @var Collection<int, SocialAccount>
     */
    #[ORM\OneToMany(targetEntity: SocialAccount::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $socialAccounts;

    /**
     * @var Collection<int, Post>
     */
    #[ORM\OneToMany(targetEntity: Post::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $posts;

    /**
     * @var Collection<int, Destination>
     */
    #[ORM\OneToMany(targetEntity: Destination::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $destinations;

    public function __construct()
    {
        $this->socialAccounts = new ArrayCollection();
        $this->posts = new ArrayCollection();
        $this->destinations = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->apiCredentials = new ArrayCollection();
    }

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

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
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

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): static
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): static
    {
        $this->lastName = $lastName;
        return $this;
    }

    public function getPseudo(): ?string
    {
        return $this->pseudo;
    }

    public function setPseudo(?string $pseudo): static
    {
        $this->pseudo = $pseudo;
        return $this;
    }

    public function getWebsite(): ?string
    {
        return $this->website;
    }

    public function setWebsite(?string $website): static
    {
        $this->website = $website;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;
        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): static
    {
        $this->timezone = $timezone;
        return $this;
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    public function getDisplayName(): string
    {
        if ($this->firstName || $this->lastName) {
            return trim($this->firstName . ' ' . $this->lastName);
        }
        
        if ($this->pseudo) {
            return $this->pseudo;
        }
        
        return explode('@', $this->email)[0];
    }

    /**
     * @return Collection<int, SocialAccount>
     */
    public function getSocialAccounts(): Collection
    {
        return $this->socialAccounts;
    }

    public function addSocialAccount(SocialAccount $socialAccount): static
    {
        if (!$this->socialAccounts->contains($socialAccount)) {
            $this->socialAccounts->add($socialAccount);
            $socialAccount->setUser($this);
        }
        return $this;
    }

    public function removeSocialAccount(SocialAccount $socialAccount): static
    {
        if ($this->socialAccounts->removeElement($socialAccount)) {
            if ($socialAccount->getUser() === $this) {
                $socialAccount->setUser(null);
            }
        }
        return $this;
    }

    public function getSocialAccountByPlatform(string $platform): ?SocialAccount
    {
        foreach ($this->socialAccounts as $account) {
            if ($account->getPlatform() === $platform && $account->isActive()) {
                return $account;
            }
        }
        return null;
    }

    public function hasConnectedPlatform(string $platform): bool
    {
        $account = $this->getSocialAccountByPlatform($platform);
        return $account !== null && $account->isTokenValid();
    }

    /**
     * @return Collection<int, Post>
     */
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function addPost(Post $post): static
    {
        if (!$this->posts->contains($post)) {
            $this->posts->add($post);
            $post->setUser($this);
        }
        return $this;
    }

    public function removePost(Post $post): static
    {
        if ($this->posts->removeElement($post)) {
            if ($post->getUser() === $this) {
                $post->setUser(null);
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
            $destination->setUser($this);
        }
        return $this;
    }

    public function removeDestination(Destination $destination): static
    {
        if ($this->destinations->removeElement($destination)) {
            if ($destination->getUser() === $this) {
                $destination->setUser(null);
            }
        }
        return $this;
    }

    /**
     * Obtenir les destinations actives
     */
    public function getActiveDestinations(): Collection
    {
        return $this->destinations->filter(
            fn(Destination $destination) => $destination->isActive()
        );
    }

    /**
     * Obtenir les destinations par plateforme
     */
    public function getDestinationsByPlatform(string $platform): Collection
    {
        return $this->destinations->filter(
            fn(Destination $destination) => 
                $destination->getSocialAccount()?->getPlatform() === $platform && $destination->isActive()
        );
    }

    /**
     * @return Collection<int, ApiCredentials>
     */
    public function getApiCredentials(): Collection
    {
        return $this->apiCredentials;
    }

    public function addApiCredential(ApiCredentials $apiCredential): static
    {
        if (!$this->apiCredentials->contains($apiCredential)) {
            $this->apiCredentials->add($apiCredential);
            $apiCredential->setUser($this);
        }

        return $this;
    }

    public function removeApiCredential(ApiCredentials $apiCredential): static
    {
        if ($this->apiCredentials->removeElement($apiCredential)) {
            if ($apiCredential->getUser() === $this) {
                $apiCredential->setUser(null);
            }
        }

        return $this;
    }

    /**
     * Vérifie si l'utilisateur a des clefs configurées pour une plateforme
     */
    public function hasCredentialsForPlatform(string $platform): bool
    {
        foreach ($this->apiCredentials as $credential) {
            if ($credential->getPlatform() === $platform && $credential->isActive()) {
                return true;
            }
        }
        return false;
    }
}