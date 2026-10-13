<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Erp\Entity\CustomerOrder;
use App\Erp\Enum\CustomerOrderStatus;
use App\Erp\Service\CustomerAccountService;
use Doctrine\ORM\EntityManagerInterface;

final class GetCustomerOrderTool implements AgentToolInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CustomerAccountService $accountService,
    ) {
    }

    public function name(): string
    {
        return 'get_customer_order';
    }

    public function description(): string
    {
        return "Détail d'une commande client à partir de son numéro (CC-xxxxx) : statut, client, entrepôt, montant HT, lignes "
            .'(quantités commandées, réservées, en reliquat, expédiées) et, si elle est bloquée, les motifs du blocage '
            ."(plafond d'encours dépassé, relance de niveau 3).";
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'number' => ['type' => 'string', 'description' => 'Numéro de commande client, ex. CC-24060'],
            ],
            'required' => ['number'],
        ];
    }

    public function execute(array $arguments): array
    {
        $number = \is_string($arguments['number'] ?? null) ? strtoupper(trim($arguments['number'])) : '';
        $order = $this->entityManager->getRepository(CustomerOrder::class)->findOneBy(['number' => $number]);
        if ($order === null) {
            return ['erreur' => sprintf('Aucune commande client « %s ».', $number)];
        }

        $result = [
            'commande' => $order->getNumber(),
            'statut' => $order->getStatus()->label(),
            'client' => ['code' => $order->getCustomer()->getCode(), 'nom' => $order->getCustomer()->getName()],
            'entrepot' => $order->getWarehouse()->getCode(),
            'date' => $order->getOrderedAt()->format('Y-m-d'),
            'montant_ht' => $order->getTotalExcludingTax(),
            'lignes' => array_map(static fn ($line): array => [
                'article' => $line->getArticle()->getCode(),
                'libelle' => $line->getArticle()->getLabel(),
                'quantite' => $line->getQuantity(),
                'prix_unitaire_ht' => (float) $line->getUnitPrice(),
                'reservee' => $line->getReservedQuantity(),
                'en_reliquat' => $order->getStatus()->reservesStock() ? $line->getBackorderQuantity() : 0,
                'expediee' => $line->getShippedQuantity(),
            ], $order->getLines()->toArray()),
        ];

        if ($order->getStatus() === CustomerOrderStatus::CreditBlocked) {
            $account = $this->accountService->account($order->getCustomer());
            $result['motifs_blocage'] = $account['motifs_blocage'];
            $result['encours_client'] = $account['encours'];
            $result['plafond_encours_client'] = $account['plafond_encours'];
        }

        return $result;
    }
}
