<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'chunk')]
#[ORM\Index(name: 'ft_chunk_search', columns: ['heading', 'content'], flags: ['fulltext'])]
class Chunk
{
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

    public function __construct(Document $document, int $position, string $heading, string $content)
    {
        $this->document = $document;
        $this->position = $position;
        $this->heading = $heading;
        $this->content = $content;
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
}
