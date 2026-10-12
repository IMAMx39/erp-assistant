<?php

declare(strict_types=1);

namespace App\Ingestion\MessageHandler;

use App\Entity\Document;
use App\Ingestion\Chunker\MarkdownChunker;
use App\Ingestion\Message\IngestDocument;
use App\Ingestion\Parser\MarkdownParser;
use App\Repository\DocumentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

#[AsMessageHandler]
final class IngestDocumentHandler
{
    public function __construct(
        private readonly MarkdownParser $parser,
        private readonly MarkdownChunker $chunker,
        private readonly DocumentRepository $documentRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(CORPUS_DIR)%')]
        private readonly string $corpusDir,
    ) {
    }

    public function __invoke(IngestDocument $message): void
    {
        $file = rtrim($this->corpusDir, '/').'/'.$message->relativePath;
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

        $document ??= new Document($message->relativePath);
        $parsed = $this->parser->parse($raw, pathinfo($file, \PATHINFO_FILENAME));

        $document->update($parsed->title, $parsed->module, $parsed->tags, $hash);
        $document->clearChunks();

        $chunks = $this->chunker->chunk($parsed);
        foreach ($chunks as $chunk) {
            $document->addChunk($chunk->position, $chunk->heading, $chunk->content);
        }

        $this->entityManager->persist($document);
        $this->entityManager->flush();

        $this->logger->info('Document ingéré', [
            'path' => $message->relativePath,
            'chunks' => \count($chunks),
        ]);
    }
}
