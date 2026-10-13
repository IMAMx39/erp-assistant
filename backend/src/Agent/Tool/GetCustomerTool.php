<?php

declare(strict_types=1);

namespace App\Agent\Tool;

use App\Erp\Entity\Customer;
use App\Erp\Service\CustomerAccountService;
use Doctrine\ORM\EntityManagerInterface;

final class GetCustomerTool implements AgentToolInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly CustomerAccountService $accountService,
    ) {
    }

    public function name(): string
    {
        return 'get_customer';
    }

    public function description(): string
    {
        return "Situation d'un client à partir de son code (CLI-xxxxx) ou d'une partie de son nom : plafond d'encours, encours "
            .'(factures non réglées + commandes non facturées), factures non réglées avec retard et niveau de relance, '
            .'motifs de blocage des commandes, pays et numéro de TVA intracommunautaire.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'customer' => ['type' => 'string', 'description' => 'Code client (ex. CLI-00042) ou partie du nom (ex. « Fontaine »)'],
            ],
            'required' => ['customer'],
        ];
    }

    public function execute(array $arguments): array
    {
        $search = \is_string($arguments['customer'] ?? null) ? trim($arguments['customer']) : '';
        if ($search === '') {
            return ['erreur' => 'Indiquez un code client ou un nom.'];
        }

        $repository = $this->entityManager->getRepository(Customer::class);
        $customer = $repository->findOneBy(['code' => strtoupper($search)]);

        if ($customer === null) {
            /** @var list<Customer> $matches */
            $matches = $repository->createQueryBuilder('c')
                ->where('c.name LIKE :name')
                ->setParameter('name', '%'.$search.'%')
                ->getQuery()
                ->getResult();

            if (\count($matches) > 1) {
                return ['erreur' => 'Plusieurs clients correspondent, précisez le code.', 'clients' => array_map(
                    static fn (Customer $c): array => ['client' => $c->getCode(), 'nom' => $c->getName()],
                    $matches,
                )];
            }
            $customer = $matches[0] ?? null;
        }

        if ($customer === null) {
            return ['erreur' => sprintf('Aucun client ne correspond à « %s ».', $search)];
        }

        return $this->accountService->account($customer);
    }
}
