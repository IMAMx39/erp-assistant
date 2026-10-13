<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Erp\Repository\ArticleRepository;
use App\Erp\Service\StockService;

final class SearchArticlesTool implements AgentToolInterface
{
    private const LIMIT = 10;

    public function __construct(
        private readonly ArticleRepository $articleRepository,
        private readonly StockService $stockService,
    ) {
    }

    public function name(): string
    {
        return 'search_articles';
    }

    public function description(): string
    {
        return "Recherche d'articles par mots du libellé (ex. « gants », « vis M8 ») et/ou par famille "
            .'(Visserie, Outillage, EPI, Lubrifiants, Emballage, Manutention). À utiliser quand le code article est inconnu. '
            .'Renvoie au plus 10 articles avec leur statut et leur stock disponible par entrepôt.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => ['string', 'null'], 'description' => 'Mots du libellé recherchés'],
                'family' => ['type' => ['string', 'null'], 'description' => "Famille d'articles (facultatif)"],
            ],
        ];
    }

    public function execute(array $arguments): array
    {
        $query = \is_string($arguments['query'] ?? null) ? $arguments['query'] : '';
        $family = \is_string($arguments['family'] ?? null) ? $arguments['family'] : null;

        if (trim($query) === '' && ($family === null || trim($family) === '')) {
            return ['erreur' => 'Indiquez des mots du libellé ou une famille.'];
        }

        $articles = $this->articleRepository->search($query, $family, self::LIMIT);
        $note = null;
        // Famille mal devinée par le modèle (ex. « gants » cherchés en Manutention) : on élargit.
        if ($articles === [] && $family !== null && trim($query) !== '') {
            $articles = $this->articleRepository->search($query, null, self::LIMIT);
            if ($articles !== []) {
                $note = sprintf('Aucun résultat dans la famille « %s » : résultats toutes familles confondues.', $family);
            }
        }

        $results = [];
        foreach ($articles as $article) {
            $results[] = [
                'article' => $article->getCode(),
                'libelle' => $article->getLabel(),
                'famille' => $article->getFamily(),
                'statut' => $article->getStatus()->label(),
                'stock_disponible' => array_map(
                    static fn (array $row): int => $row['stock_disponible'],
                    $this->stockService->byWarehouse($article),
                ),
            ];
        }

        return array_filter(
            ['nombre' => \count($results), 'remarque' => $note, 'articles' => $results],
            static fn (mixed $value): bool => $value !== null,
        );
    }
}
