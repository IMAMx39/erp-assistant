<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Erp\Entity\StockRule;
use App\Erp\Service\StockService;
use Doctrine\ORM\EntityManagerInterface;

final readonly class GetArticleTool implements AgentToolInterface
{
    public function __construct(
        private ArticleLookup          $articles,
        private StockService           $stockService,
        private EntityManagerInterface $entityManager,
    )
    {
    }

    public function name(): string
    {
        return 'get_article';
    }

    public function description(): string
    {
        return "Fiche d'un article à partir de son code (ART-xxxxx) : libellé, famille, statut (actif, bloqué à la vente, archivé), "
            . 'prix de vente HT, TVA, fournisseur principal, unités, et stock par entrepôt (physique, réservé, disponible, '
            . 'en litige, en transit) avec les stocks minimum et maximum. Outil à utiliser pour le stock disponible ou réservé.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'code' => ['type' => 'string', 'description' => "Code de l'article, ex. ART-10452"],
            ],
            'required' => ['code'],
        ];
    }

    public function execute(array $arguments): array
    {
        $article = $this->articles->find($arguments['code'] ?? null);
        if ($article === null) {
            return ['erreur' => sprintf('Aucun article avec le code « %s ».', (string)($arguments['code'] ?? ''))];
        }

        $stock = $this->stockService->byWarehouse($article);
        foreach ($this->entityManager->getRepository(StockRule::class)->findBy(['article' => $article]) as $rule) {
            $stock[$rule->getWarehouse()->getCode()]['stock_minimum'] = $rule->getMinimum();
            $stock[$rule->getWarehouse()->getCode()]['stock_maximum'] = $rule->getMaximum();
        }

        return [
            'article' => $article->getCode(),
            'libelle' => $article->getLabel(),
            'famille' => $article->getFamily(),
            'statut' => $article->getStatus()->label(),
            'prix_vente_ht' => (float)$article->getSalePrice(),
            'taux_tva' => (float)$article->getVatRate(),
            'unite_stock' => $article->getStockUnit(),
            'unite_achat' => $article->getPurchaseUnit(),
            'coefficient_conversion' => $article->getConversionFactor(),
            'fournisseur_principal' => $article->getMainSupplier() === null ? null : [
                'code' => $article->getMainSupplier()->getCode(),
                'nom' => $article->getMainSupplier()->getName(),
            ],
            'stock_par_entrepot' => array_values($stock),
        ];
    }
}
