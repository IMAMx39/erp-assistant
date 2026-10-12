<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DocumentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\Table(name: 'document')]
#[ORM\UniqueConstraint(name: 'uniq_document_path', columns: ['path'])]
class Document
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private string $path;

    #[ORM\Column(length: 255)]
    private string $title = '';

    #[ORM\Column(length: 50)]
    private string $module = 'general';

    /** @var list<string> */
    #[ORM\Column(type: Types::JSON)]
    private array $tags = [];

    #[ORM\Column(length: 64)]
    private string $contentHash = '';

    #[ORM\Column]
    private \DateTimeImmutable $ingestedAt;

    /** @var Collection<int, Chunk> */
    #[ORM\OneToMany(targetEntity: Chunk::class, mappedBy: 'document', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $chunks;

    public function __construct(string $path)
    {
        $this->path = $path;
        $this->ingestedAt = new \DateTimeImmutable();
        $this->chunks = new ArrayCollection();
    }

    /**
     * @param list<string> $tags
     */
    public function update(string $title, string $module, array $tags, string $contentHash): void
    {
        $this->title = $title;
        $this->module = $module;
        $this->tags = $tags;
        $this->contentHash = $contentHash;
        $this->ingestedAt = new \DateTimeImmutable();
    }

    public function clearChunks(): void
    {
        $this->chunks->clear();
    }

    public function addChunk(int $position, string $heading, string $content): void
    {
        $this->chunks->add(new Chunk($this, $position, $heading, $content));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getModule(): string
    {
        return $this->module;
    }

    /** @return list<string> */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function getContentHash(): string
    {
        return $this->contentHash;
    }

    public function getIngestedAt(): \DateTimeImmutable
    {
        return $this->ingestedAt;
    }

    /** @return Collection<int, Chunk> */
    public function getChunks(): Collection
    {
        return $this->chunks;
    }
}
