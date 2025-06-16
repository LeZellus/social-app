<?php

namespace App\Entity;

use App\Repository\PostPublicationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PostPublicationRepository::class)]
class PostPublication
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'postPublications')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Post $post = null;

    #[ORM\ManyToOne(inversedBy: 'postPublications')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SocialAccount $socialAccount = null;

    // ✅ NOUVEAU : Destination spécifique sur la plateforme
    #[ORM\Column(length: 255)]
    private ?string $destination = null; // Ex: "r/gamedev", "r/IndieGame", ou pour Twitter: "general"

    #[ORM\Column(length: 50)]
    private ?string $status = 'pending'; // 'pending', 'published', 'failed', 'scheduled'

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $platformPostId = null; // ID du post sur la plateforme

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $platformUrl = null; // URL du post publié

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $publishedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adaptedContent = null; // Contenu adapté à la plateforme

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adaptedTitle = null; // Titre adapté à la plateforme

    // ✅ NOUVEAU : Métadonnées spécifiques à la destination
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $destinationSettings = null; // Ex: {"flair": "Tutorial", "spoiler": false} pour Reddit

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $errorMessage = null; // Message d'erreur en cas d'échec

    #[ORM\Column(nullable: true)]
    private ?int $retryCount = 0;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $platformResponse = null; // Réponse complète de l'API

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(?Post $post): static
    {
        $this->post = $post;
        return $this;
    }

    public function getSocialAccount(): ?SocialAccount
    {
        return $this->socialAccount;
    }

    public function setSocialAccount(?SocialAccount $socialAccount): static
    {
        $this->socialAccount = $socialAccount;
        return $this;
    }

    public function getDestination(): ?string
    {
        return $this->destination;
    }

    public function setDestination(string $destination): static
    {
        $this->destination = $destination;
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

    public function getPlatformPostId(): ?string
    {
        return $this->platformPostId;
    }

    public function setPlatformPostId(?string $platformPostId): static
    {
        $this->platformPostId = $platformPostId;
        return $this;
    }

    public function getPlatformUrl(): ?string
    {
        return $this->platformUrl;
    }

    public function setPlatformUrl(?string $platformUrl): static
    {
        $this->platformUrl = $platformUrl;
        return $this;
    }

    public function getPublishedAt(): ?\DateTimeImmutable
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeImmutable $publishedAt): static
    {
        $this->publishedAt = $publishedAt;
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

    public function getAdaptedContent(): ?string
    {
        return $this->adaptedContent;
    }

    public function setAdaptedContent(?string $adaptedContent): static
    {
        $this->adaptedContent = $adaptedContent;
        return $this;
    }

    public function getAdaptedTitle(): ?string
    {
        return $this->adaptedTitle;
    }

    public function setAdaptedTitle(?string $adaptedTitle): static
    {
        $this->adaptedTitle = $adaptedTitle;
        return $this;
    }

    public function getDestinationSettings(): ?array
    {
        return $this->destinationSettings;
    }

    public function setDestinationSettings(?array $destinationSettings): static
    {
        $this->destinationSettings = $destinationSettings;
        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): static
    {
        $this->errorMessage = $errorMessage;
        return $this;
    }

    public function getRetryCount(): ?int
    {
        return $this->retryCount;
    }

    public function setRetryCount(int $retryCount): static
    {
        $this->retryCount = $retryCount;
        return $this;
    }

    public function getPlatformResponse(): ?array
    {
        return $this->platformResponse;
    }

    public function setPlatformResponse(?array $platformResponse): static
    {
        $this->platformResponse = $platformResponse;
        return $this;
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function isFailed(): bool
    {
        return $this->status === 'failed';
    }

    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function incrementRetryCount(): void
    {
        $this->retryCount++;
    }

    public function markAsPublished(string $platformPostId, string $platformUrl, array $platformResponse = []): void
    {
        $this->status = 'published';
        $this->platformPostId = $platformPostId;
        $this->platformUrl = $platformUrl;
        $this->platformResponse = $platformResponse;
        $this->publishedAt = new \DateTimeImmutable();
        $this->errorMessage = null;
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->status = 'failed';
        $this->errorMessage = $errorMessage;
    }

    // ✅ HELPER : Récupère le subreddit pour Reddit
    public function getSubreddit(): ?string
    {
        if ($this->socialAccount?->getPlatform() === 'reddit') {
            return str_replace('r/', '', $this->destination);
        }
        return null;
    }

    // ✅ HELPER : Définit le subreddit pour Reddit
    public function setSubreddit(string $subreddit): static
    {
        if ($this->socialAccount?->getPlatform() === 'reddit') {
            $this->destination = 'r/' . ltrim($subreddit, 'r/');
        }
        return $this;
    }
}