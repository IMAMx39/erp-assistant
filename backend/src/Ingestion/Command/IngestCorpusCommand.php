<?php

declare(strict_types=1);

namespace App\Ingestion\Command;

use App\Ingestion\Message\IngestDocument;
use App\Repository\DocumentRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:corpus:ingest',
    description: 'Envoie les fichiers Markdown du corpus en ingestion',
)]
final class IngestCorpusCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly DocumentRepository $documentRepository,
        #[Autowire('%env(CORPUS_DIR)%')]
        private readonly string $corpusDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('force', null, InputOption::VALUE_NONE, 'Réingère aussi les fichiers inchangés');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (!is_dir($this->corpusDir)) {
            $io->error(sprintf('Dossier du corpus introuvable : %s', $this->corpusDir));

            return Command::FAILURE;
        }

        $finder = (new Finder())
            ->files()
            ->in($this->corpusDir)
            ->name('*.md')
            ->notName('README.md')
            ->sortByName();

        $paths = [];
        foreach ($finder as $file) {
            $paths[] = $file->getRelativePathname();
        }

        // Garde-fou : un corpus vide (montage Docker raté) ne doit pas tout supprimer.
        if ($paths === []) {
            $io->warning('Aucun fichier Markdown trouvé. Rien n\'a été envoyé ni supprimé.');

            return Command::FAILURE;
        }

        $force = (bool) $input->getOption('force');
        foreach ($paths as $path) {
            $this->messageBus->dispatch(new IngestDocument($path, $force));
        }

        $deleted = $this->documentRepository->deleteMissing($paths);

        $io->success(sprintf(
            '%d document(s) envoyé(s) en ingestion, %d document(s) obsolète(s) supprimé(s).',
            \count($paths),
            $deleted,
        ));

        return Command::SUCCESS;
    }
}
