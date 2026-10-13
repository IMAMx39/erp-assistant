<?php

declare(strict_types=1);

namespace App\Erp\Service;

use App\Erp\Entity\Customer;
use App\Erp\Entity\CustomerOrder;
use App\Erp\Entity\Invoice;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Situation financière d'un client (ventes/commande-client.md, facturation/relances.md) :
 * encours = factures non réglées + commandes non facturées ; une relance de niveau 3 bloque
 * automatiquement les nouvelles commandes.
 */
final class CustomerAccountService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    )
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function account(Customer $customer): array
    {
        $today = new \DateTimeImmutable('today');

        $unpaid = array_values(array_filter(
            $this->entityManager->getRepository(Invoice::class)->findBy(['customer' => $customer], ['dueAt' => 'ASC']),
            static fn(Invoice $invoice): bool => $invoice->getOutstandingAmount() > 0,
        ));

        $openOrders = array_values(array_filter(
            $this->entityManager->getRepository(CustomerOrder::class)->findBy(['customer' => $customer], ['orderedAt' => 'ASC']),
            static fn(CustomerOrder $order): bool => $order->getStatus()->countsInOutstanding(),
        ));

        $unpaidTotal = array_sum(array_map(static fn(Invoice $i): float => $i->getOutstandingAmount(), $unpaid));
        $ordersTotal = array_sum(array_map(static fn(CustomerOrder $o): float => $o->getTotalExcludingTax(), $openOrders));
        $outstanding = round($unpaidTotal + $ordersTotal, 2);
        $limit = (float)$customer->getCreditLimit();
        $maxReminder = max([0, ...array_map(static fn(Invoice $i): int => $i->getReminderLevel(), $unpaid)]);

        $blockingReasons = [];
        if ($outstanding > $limit) {
            $blockingReasons[] = sprintf('Encours de %.2f € supérieur au plafond de %.2f €', $outstanding, $limit);
        }
        if ($maxReminder >= 3) {
            $blockingReasons[] = 'Facture en relance de niveau 3 (mise en demeure) : nouvelles commandes bloquées';
        }

        return [
            'client' => $customer->getCode(),
            'nom' => $customer->getName(),
            'pays' => $customer->getCountry(),
            'numero_tva_intracommunautaire' => $customer->getVatNumber(),
            'mode_facturation' => $customer->getInvoicingMode(),
            'plafond_encours' => $limit,
            'encours' => $outstanding,
            'dont_factures_non_reglees' => round($unpaidTotal, 2),
            'dont_commandes_non_facturees' => round($ordersTotal, 2),
            'niveau_relance_max' => $maxReminder,
            'motifs_blocage' => $blockingReasons,
            'factures_non_reglees' => array_map(static fn(Invoice $i): array => [
                'facture' => $i->getNumber(),
                'echeance' => $i->getDueAt()->format('Y-m-d'),
                'jours_de_retard' => $i->isOverdue($today) ? (int)$i->getDueAt()->diff($today)->days : 0,
                'reste_a_payer' => $i->getOutstandingAmount(),
                'niveau_relance' => $i->getReminderLevel(),
            ], $unpaid),
            'commandes_non_facturees' => array_map(static fn(CustomerOrder $o): array => [
                'commande' => $o->getNumber(),
                'statut' => $o->getStatus()->label(),
                'montant_ht' => $o->getTotalExcludingTax(),
            ], $openOrders),
        ];
    }
}
