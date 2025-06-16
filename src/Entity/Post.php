<?php

namespace App\Entity;

use App\Repository\PostRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostRepository::class)]
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

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}