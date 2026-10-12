<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ChunkRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ChunkRepository::class)]
#[ORM\Table(name: 'chunk')]
#[ORM\Index(name: 'ft_chunk_search', columns: ['heading', 'content'], flags: ['fulltext'])]
// Créé en VECTOR INDEX (HNSW, cosinus) par la migration ; DBAL le relit comme un index simple.
#[ORM\Index(name: 'idx_chunk_embedding', columns: ['embedding'])]
class Chunk
{
    public const EMBEDDING_DIMENSION = 768;

    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'chunks')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Document $document;

    #[ORM\Column]
    private int $position;

    #[ORM\Column(length: 255)]
    private string $heading;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    /** @var list<float> */
    #[ORM\Column(type: 'vector', length: self::EMBEDDING_DIMENSION)]
    private array $embedding;

    /**
     * @param list<float> $embedding
     */
    public function __construct(Document $document, int $position, string $heading, string $content, array $embedding)
    {
        $this->document = $document;
        $this->position = $position;
        $this->heading = $heading;
        $this->content = $content;
        $this->embedding = $embedding;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocument(): Document
    {
        return $this->document;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function getHeading(): string
    {
        return $this->heading;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /** @return list<float> */
    public function getEmbedding(): array
    {
        return $this->embedding;
    }
}
