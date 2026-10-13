<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Erp\Entity\PurchaseOrder;
use Doctrine\ORM\EntityManagerInterface;

final class GetPurchaseOrderTool implements AgentToolInterface
{
    /** Seuil de validation par un Administrateur (achats/commande-fournisseur.md). */
    private const ADMIN_APPROVAL_THRESHOLD = 5000.0;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    )
    {
    }

    public function name(): string
    {
        return 'get_purchase_order';
    }

    public function description(): string
    {
        return "Détail d'une commande fournisseur à partir de son numéro (CF-xxxxx) : statut, fournisseur, entrepôt, date prévue, "
            . 'montant HT, lignes avec quantités commandées, reçues et reliquat restant à recevoir, et validation requise.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'number' => ['type' => 'string', 'description' => 'Numéro de commande fournisseur, ex. CF-24018'],
            ],
            'required' => ['number'],
        ];
    }

    public function execute(array $arguments): array
    {
        $number = \is_string($arguments['number'] ?? null) ? strtoupper(trim($arguments['number'])) : '';
        $order = $this->entityManager->getRepository(PurchaseOrder::class)->findOneBy(['number' => $number]);
        if ($order === null) {
            return ['erreur' => sprintf('Aucune commande fournisseur « %s ».', $number)];
        }

        $total = $order->getTotalExcludingTax();

        return [
            'commande' => $order->getNumber(),
            'statut' => $order->getStatus()->label(),
            'fournisseur' => ['code' => $order->getSupplier()->getCode(), 'nom' => $order->getSupplier()->getName()],
            'entrepot' => $order->getWarehouse()->getCode(),
            'date' => $order->getOrderedAt()->format('Y-m-d'),
            'date_prevue' => $order->getExpectedAt()?->format('Y-m-d'),
            'montant_ht' => $total,
            'validation_requise' => $total >= self::ADMIN_APPROVAL_THRESHOLD
                ? 'Responsable logistique et Administrateur (montant HT de 5 000 € ou plus)'
                : 'Responsable logistique',
            'lignes' => array_map(static fn($line): array => [
                'article' => $line->getArticle()->getCode(),
                'libelle' => $line->getArticle()->getLabel(),
                'commandee' => $line->getQuantity(),
                'recue' => $line->getReceivedQuantity(),
                'reliquat' => $line->getRemainingQuantity(),
                'prix_unitaire_ht' => (float)$line->getUnitPrice(),
            ], $order->getLines()->toArray()),
        ];
    }
}
