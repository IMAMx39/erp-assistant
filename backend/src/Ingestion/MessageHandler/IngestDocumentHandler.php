<?php

declare(strict_types=1);

namespace App\Ingestion\MessageHandler;

use App\Embedding\EmbedderInterface;
use App\Entity\Document;
use App\Ingestion\Chunker\MarkdownChunker;
use App\Ingestion\Dto\ChunkData;
use App\Ingestion\Message\IngestDocument;
use App\Ingestion\Parser\MarkdownParser;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class IngestDocumentHandler
{
    public function __construct(
        private MarkdownParser         $parser,
        private MarkdownChunker        $chunker,
        private EmbedderInterface      $embedder,
        private DocumentRepository     $documentRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface        $logger,
        #[Autowire('%env(CORPUS_DIR)%')]
        private string                 $corpusDir,
    )
    {
    }

    public function __invoke(IngestDocument $message): void
    {
        $file = rtrim($this->corpusDir, '/') . '/' . $message->relativePath;
        $raw = is_file($file) ? file_get_contents($file) : false;

        if ($raw === false) {
            throw new UnrecoverableMessageHandlingException(sprintf('Fichier illisible : %s', $file));
        }

        $hash = hash('sha256', $raw);
        $document = $this->documentRepository->findOneByPath($message->relativePath);

        if (!$message->force && $document?->getContentHash() === $hash) {
            $this->logger->info('Document inchangé, ignoré', ['path' => $message->relativePath]);

            return;
        }

        $parsed = $this->parser->parse($raw, pathinfo($file, \PATHINFO_FILENAME));
        $chunks = $this->chunker->chunk($parsed);

        // Calculés avant toute modification : si Ollama échoue, le message est rejoué
        // et le document en base reste dans son état précédent.
        $embeddings = $this->embedder->embedDocuments(array_map(
            static fn(ChunkData $chunk): string => $chunk->heading . "\n\n" . $chunk->content,
            $chunks,
        ));

        $document ??= new Document($message->relativePath);
        $document->update($parsed->title, $parsed->module, $parsed->tags, $hash);
        $document->clearChunks();

        foreach ($chunks as $i => $chunk) {
            $document->addChunk($chunk->position, $chunk->heading, $chunk->content, $embeddings[$i]);
        }

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        $this->logger->info('Document ingéré', [
            'path' => $message->relativePath,
            'chunks' => \count($chunks),
        ]);
    }
}
