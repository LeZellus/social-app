<?php

namespace App\Entity;

use App\Repository\PostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'posts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column(length: 50)]
    private ?string $status = 'draft'; // 'draft', 'scheduled', 'published', 'failed'

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $scheduledAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $mediaFiles = null; // URLs des médias attachés

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $platformSettings = null; // Adaptations par plateforme

    /**
     * @var Collection<int, PostPublication>
     */
    #[ORM\OneToMany(targetEntity: PostPublication::class, mappedBy: 'post', orphanRemoval: true)]
    private Collection $postPublications;

    public function __construct()
    {
        $this->postPublications = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getScheduledAt(): ?\DateTimeImmutable
    {
        return $this->scheduledAt;
    }

    public function setScheduledAt(?\DateTimeImmutable $scheduledAt): static
    {
        $this->scheduledAt = $scheduledAt;
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getMediaFiles(): ?array
    {
        return $this->mediaFiles;
    }

    public function setMediaFiles(?array $mediaFiles): static
    {
        $this->mediaFiles = $mediaFiles;
        return $this;
    }

    public function getPlatformSettings(): ?array
    {
        return $this->platformSettings;
    }

    public function setPlatformSettings(?array $platformSettings): static
    {
        $this->platformSettings = $platformSettings;
        return $this;
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled' && $this->scheduledAt !== null;
    }

    public function isDraft(): bool
    {
        return $this->status === 'draft';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
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
            $postPublication->setPost($this);
        }
        return $this;
    }

    public function removePostPublication(PostPublication $postPublication): static
    {
        if ($this->postPublications->removeElement($postPublication)) {
            if ($postPublication->getPost() === $this) {
                $postPublication->setPost(null);
            }
        }
        return $this;
    }

    /**
     * Obtenir les publications par statut
     */
    public function getPublicationsByStatus(string $status): Collection
    {
        return $this->postPublications->filter(
            fn(PostPublication $publication) => $publication->getStatus() === $status
        );
    }

    /**
     * Obtenir les publications publiées
     */
    public function getPublishedPublications(): Collection
    {
        return $this->getPublicationsByStatus('published');
    }

    /**
     * Obtenir les publications en attente
     */
    public function getPendingPublications(): Collection
    {
        return $this->getPublicationsByStatus('pending');
    }

    /**
     * Obtenir les publications échouées
     */
    public function getFailedPublications(): Collection
    {
        return $this->getPublicationsByStatus('failed');
    }

    /**
     * Vérifier si toutes les publications sont publiées
     */
    public function areAllPublicationsPublished(): bool
    {
        if ($this->postPublications->isEmpty()) {
            return false;
        }

        foreach ($this->postPublications as $publication) {
            if (!$publication->isPublished()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtenir le nombre de publications par statut
     */
    public function getPublicationStats(): array
    {
        $stats = [
            'total' => $this->postPublications->count(),
            'published' => 0,
            'pending' => 0,
            'failed' => 0,
            'scheduled' => 0,
        ];

        foreach ($this->postPublications as $publication) {
            $stats[$publication->getStatus()]++;
        }

        return $stats;
    }

    /**
     * Obtenir un aperçu du contenu (pour les listes)
     */
    public function getContentPreview(int $length = 100): string
    {
        if (strlen($this->content) <= $length) {
            return $this->content;
        }

        return substr($this->content, 0, $length) . '...';
    }

    /**
     * Vérifier si le post a des médias
     */
    public function hasMediaFiles(): bool
    {
        return !empty($this->mediaFiles);
    }

    /**
     * Obtenir le premier média (pour affichage)
     */
    public function getFirstMediaFile(): ?string
    {
        return $this->mediaFiles[0] ?? null;
    }

    /**
     * Vérifier si le post peut être publié
     */
    public function canBePublished(): bool
    {
        return $this->status === 'draft' || $this->status === 'failed';
    }

    /**
     * Vérifier si le post peut être modifié
     */
    public function canBeEdited(): bool
    {
        return $this->status !== 'published' || $this->getPendingPublications()->count() > 0;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
        if ($this->updatedAt === null) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function __toString(): string
    {
        return $this->title ?? 'Post #' . $this->id;
    }
}