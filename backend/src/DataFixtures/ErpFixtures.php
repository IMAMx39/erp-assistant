<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Erp\Entity\Article;
use App\Erp\Entity\Customer;
use App\Erp\Entity\CustomerOrder;
use App\Erp\Entity\Invoice;
use App\Erp\Entity\Location;
use App\Erp\Entity\PurchaseOrder;
use App\Erp\Entity\StockLevel;
use App\Erp\Entity\StockRule;
use App\Erp\Entity\Supplier;
use App\Erp\Entity\Warehouse;
use App\Erp\Enum\ArticleStatus;
use App\Erp\Enum\CustomerOrderStatus;
use App\Erp\Enum\LocationType;
use App\Erp\Enum\PurchaseOrderStatus;
use App\Erp\Enum\StockStatus;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Données de l'ERP fictif de Distrilog SAS, cohérentes avec corpus/README.md.
 *
 * Écrites à la main (pas de génération aléatoire) pour être reproductibles : le jeu d'évaluation
 * de l'agent s'appuie sur ces valeurs. Chaque règle de la documentation a au moins un cas concret
 * (stock sous le minimum, commande bloquée par l'encours ou par une relance de niveau 3, reliquat,
 * litige, transit, emplacement gelé, article bloqué à la vente…). Les dates sont relatives au jour
 * du chargement pour que les retards de paiement restent cohérents.
 */
final class ErpFixtures extends Fixture
{
    private ObjectManager $manager;
    private \DateTimeImmutable $today;

    /** @var array<string, Warehouse> */
    private array $warehouses = [];
    /** @var array<string, Location> clé « ORL/A-03-2 » */
    private array $locations = [];
    /** @var array<string, Supplier> */
    private array $suppliers = [];
    /** @var array<string, Article> */
    private array $articles = [];
    /** @var array<string, Customer> */
    private array $customers = [];

    public function load(ObjectManager $manager): void
    {
        $this->manager = $manager;
        $this->today = new \DateTimeImmutable('today');

        $this->warehousesAndLocations();
        $this->suppliers();
        $this->articles();
        $this->stockRules();
        $this->stockLevels();
        $this->customers();
        $this->customerOrdersAndInvoices();
        $this->purchaseOrders();

        $manager->flush();
    }

    private function warehousesAndLocations(): void
    {
        foreach (['ORL' => 'Orly (principal)', 'LYO' => 'Lyon (secondaire)'] as $code => $name) {
            $this->warehouses[$code] = $this->persist(new Warehouse($code, $name));
        }

        $locations = [
            'ORL' => ['QUAI', 'A-01-1', 'A-01-2', 'A-03-2', 'B-01-2', 'B-02-1', 'B-02-3', 'C-04-1', 'C-05-2'],
            'LYO' => ['QUAI', 'A-01-1', 'A-02-1', 'B-01-2'],
        ];
        foreach ($locations as $warehouse => $codes) {
            foreach ($codes as $code) {
                $this->locations["{$warehouse}/{$code}"] = $this->persist(new Location(
                    $this->warehouses[$warehouse],
                    $code,
                    $code === 'QUAI' ? LocationType::Dock : LocationType::Storage,
                    // Inventaire tournant en cours sur C-05-2 : emplacement gelé.
                    frozen: $warehouse === 'ORL' && $code === 'C-05-2',
                ));
            }
        }
    }

    private function suppliers(): void
    {
        $suppliers = [
            'FRN-0007' => ['Boulonnerie Martin', 'achats@boulonnerie-martin.example'],
            'FRN-0012' => ['Outillage Durand', 'commandes@outillage-durand.example'],
            'FRN-0021' => ['EPI Sécurité Plus', 'adv@epi-securite-plus.example'],
            'FRN-0034' => ['Lubritec', 'ventes@lubritec.example'],
            'FRN-0045' => ['Emballages du Rhône', 'contact@emballages-rhone.example'],
        ];
        foreach ($suppliers as $code => [$name, $email]) {
            $this->suppliers[$code] = $this->persist(new Supplier($code, $name, $email));
        }
    }

    private function articles(): void
    {
        // code => [libellé, famille, unité de stock, prix de vente HT, fournisseur, unité d'achat, coefficient, statut]
        $articles = [
            'ART-10452' => ['Vis tête hexagonale M8x40 zinguée (boîte de 100)', 'Visserie', 'boîte', '18.50', 'FRN-0007', 'carton de 12', 12],
            'ART-10453' => ['Écrou hexagonal M8 zingué (boîte de 100)', 'Visserie', 'boîte', '9.90', 'FRN-0007', 'carton de 12', 12],
            'ART-10460' => ['Rondelle plate M8 (boîte de 200)', 'Visserie', 'boîte', '7.40', 'FRN-0007', null, 1],
            'ART-10471' => ['Cheville nylon 8 mm (boîte de 100)', 'Visserie', 'boîte', '6.20', 'FRN-0007', null, 1],
            'ART-10480' => ['Vis à bois 5x50 inox (boîte de 200)', 'Visserie', 'boîte', '21.00', 'FRN-0007', null, 1, ArticleStatus::Archived],
            'ART-20118' => ['Clé dynamométrique 20-100 Nm', 'Outillage', 'pièce', '129.00', 'FRN-0012', null, 1],
            'ART-20125' => ['Jeu de clés mixtes 8-19 mm', 'Outillage', 'pièce', '54.90', 'FRN-0012', null, 1],
            'ART-20131' => ['Perceuse-visseuse 18 V', 'Outillage', 'pièce', '189.00', 'FRN-0012', null, 1, ArticleStatus::BlockedForSale],
            'ART-20140' => ['Coffret de forets HSS 19 pièces', 'Outillage', 'pièce', '32.50', 'FRN-0012', null, 1],
            'ART-30205' => ['Gants de manutention taille 9', 'EPI', 'paire', '4.80', 'FRN-0021', 'carton de 12', 12],
            'ART-30210' => ['Lunettes de protection incolores', 'EPI', 'pièce', '6.90', 'FRN-0021', null, 1],
            'ART-30222' => ['Casque de chantier blanc', 'EPI', 'pièce', '14.50', 'FRN-0021', null, 1],
            'ART-30230' => ['Chaussures de sécurité S3 pointure 42', 'EPI', 'paire', '69.00', 'FRN-0021', null, 1],
            'ART-30240' => ["Bouchons d'oreilles (boîte de 200)", 'EPI', 'boîte', '24.90', 'FRN-0021', null, 1],
            'ART-40310' => ['Huile de coupe 5 L', 'Lubrifiants', 'bidon', '38.00', 'FRN-0034', null, 1],
            'ART-40315' => ['Graisse multi-usage 400 g', 'Lubrifiants', 'cartouche', '8.90', 'FRN-0034', null, 1],
            'ART-40322' => ['Dégrippant aérosol 500 ml', 'Lubrifiants', 'aérosol', '6.40', 'FRN-0034', 'carton de 24', 24],
            'ART-50401' => ['Carton double cannelure 60x40x40', 'Emballage', 'pièce', '2.10', 'FRN-0045', 'paquet de 50', 50],
            'ART-50410' => ['Film étirable 23 microns 1,65 kg', 'Emballage', 'rouleau', '12.80', 'FRN-0045', null, 1],
            'ART-50415' => ['Ruban adhésif PVC brun 50 mm', 'Emballage', 'rouleau', '2.30', 'FRN-0045', 'carton de 36', 36],
            'ART-60502' => ['Transpalette manuel 2 500 kg', 'Manutention', 'pièce', '349.00', null, null, 1],
            'ART-60510' => ['Diable acier 250 kg', 'Manutention', 'pièce', '119.00', 'FRN-0012', null, 1],
        ];

        foreach ($articles as $code => $a) {
            $this->articles[$code] = $this->persist(new Article(
                $code,
                $a[0],
                $a[1],
                $a[2],
                $a[3],
                $a[4] === null ? null : $this->suppliers[$a[4]],
                $a[5],
                $a[6],
                status: $a[7] ?? ArticleStatus::Active,
            ));
        }
    }

    private function stockRules(): void
    {
        // article, entrepôt, minimum, maximum
        $rules = [
            ['ART-10452', 'ORL', 40, 120],
            ['ART-10452', 'LYO', 15, null],
            ['ART-30205', 'ORL', 60, 200],
            ['ART-30210', 'ORL', 50, 150],
            ['ART-40310', 'ORL', 10, 30],
            ['ART-50401', 'ORL', 300, 1000],
            ['ART-60502', 'ORL', 2, null],
            ['ART-20118', 'LYO', 3, 8],
        ];
        foreach ($rules as [$article, $warehouse, $minimum, $maximum]) {
            $this->persist(new StockRule($this->articles[$article], $this->warehouses[$warehouse], $minimum, $maximum));
        }
    }

    private function stockLevels(): void
    {
        // article, emplacement, quantité, statut
        $levels = [
            ['ART-10452', 'ORL/A-03-2', 85],
            ['ART-10452', 'ORL/B-02-1', 20],
            ['ART-10452', 'ORL/QUAI', 24],
            ['ART-10452', 'ORL/QUAI', 6, StockStatus::Dispute],
            ['ART-10452', 'LYO/A-01-1', 30],
            ['ART-10453', 'ORL/A-03-2', 60],
            ['ART-10460', 'ORL/A-03-2', 140],
            ['ART-10471', 'LYO/A-01-1', 50],
            ['ART-20118', 'ORL/B-02-1', 7],
            ['ART-20118', 'LYO/A-02-1', 2],
            ['ART-20125', 'ORL/B-02-1', 12],
            ['ART-20131', 'ORL/B-02-3', 8],
            ['ART-20140', 'ORL/B-02-3', 15],
            ['ART-30205', 'ORL/C-04-1', 40],
            ['ART-30210', 'ORL/A-01-2', 120],
            ['ART-30222', 'ORL/C-04-1', 25],
            ['ART-30230', 'ORL/C-04-1', 18],
            ['ART-30230', 'LYO/QUAI', 6, StockStatus::InTransit],
            ['ART-30240', 'ORL/C-04-1', 10],
            ['ART-40310', 'ORL/B-02-3', 6],
            ['ART-40315', 'ORL/C-05-2', 48],
            ['ART-40322', 'ORL/B-01-2', 96],
            ['ART-40322', 'LYO/B-01-2', 24],
            ['ART-50401', 'ORL/A-01-2', 450],
            ['ART-50410', 'ORL/A-01-1', 35],
            ['ART-50415', 'ORL/A-01-1', 200],
            ['ART-60502', 'ORL/A-01-1', 1],
            ['ART-60510', 'ORL/A-01-1', 4],
        ];
        foreach ($levels as $level) {
            $this->persist(new StockLevel(
                $this->articles[$level[0]],
                $this->locations[$level[1]],
                $level[2],
                $level[3] ?? StockStatus::Available,
            ));
        }
    }

    private function customers(): void
    {
        // code => [raison sociale, pays, plafond d'encours, n° TVA intracommunautaire, mode de facturation]
        $customers = [
            'CLI-00042' => ['Mécanique Bernard SARL', 'FR', '15000.00', null, 'livraison'],
            'CLI-00057' => ['Ateliers Fontaine', 'FR', '5000.00', null, 'livraison'],
            'CLI-00063' => ['BTP Lefèvre', 'FR', '20000.00', null, 'mensuelle'],
            'CLI-00071' => ['Industrie Weber GmbH', 'DE', '30000.00', 'DE123456789', 'mensuelle'],
            'CLI-00085' => ['Rhône Maintenance', 'FR', '8000.00', null, 'livraison'],
            'CLI-00090' => ['Swiss Tools SA', 'CH', '10000.00', null, 'livraison'],
        ];
        foreach ($customers as $code => [$name, $country, $limit, $vat, $mode]) {
            $this->customers[$code] = $this->persist(new Customer($code, $name, $country, $limit, $vat, $mode));
        }
    }

    private function customerOrdersAndInvoices(): void
    {
        $a = $this->articles;

        // Commande validée : stock réservé à Orly.
        $this->order('CC-24051', 'CLI-00042', 'ORL', CustomerOrderStatus::Validated, 2)
            ->addLine($a['ART-10452'], 30, '18.50', 30)
            ->addLine($a['ART-30205'], 25, '4.80', 25);

        $this->order('CC-24055', 'CLI-00085', 'ORL', CustomerOrderStatus::InPreparation, 1)
            ->addLine($a['ART-10452'], 12, '18.50', 12)
            ->addLine($a['ART-40322'], 10, '6.40', 10);

        // Stock insuffisant à Lyon : 2 clés réservées, 2 en reliquat.
        $this->order('CC-24058', 'CLI-00071', 'LYO', CustomerOrderStatus::Validated, 1)
            ->addLine($a['ART-10452'], 5, '18.50', 5)
            ->addLine($a['ART-20118'], 4, '129.00', 2);

        // Bloquée : l'encours d'Ateliers Fontaine dépasse son plafond de 5 000 €.
        $this->order('CC-24060', 'CLI-00057', 'ORL', CustomerOrderStatus::CreditBlocked, 0)
            ->addLine($a['ART-20125'], 20, '54.90')
            ->addLine($a['ART-30230'], 19, '69.00');

        // Bloquée : BTP Lefèvre a une facture en relance de niveau 3 (mise en demeure).
        $this->order('CC-24062', 'CLI-00063', 'ORL', CustomerOrderStatus::CreditBlocked, 0)
            ->addLine($a['ART-50401'], 100, '2.10');

        // Expédiée, pas encore facturée (facturation mensuelle).
        $this->order('CC-24049', 'CLI-00063', 'ORL', CustomerOrderStatus::Shipped, 6)
            ->addLine($a['ART-30222'], 10, '14.50', 0, 10);

        $invoiced = $this->order('CC-24047', 'CLI-00042', 'ORL', CustomerOrderStatus::Invoiced, 20)
            ->addLine($a['ART-20118'], 10, '129.00', 0, 10)
            ->addLine($a['ART-10453'], 50, '9.90', 0, 50);

        // facture, client, émise il y a (jours), échéance dans (jours, négatif = dépassée), TTC, réglé, niveau de relance, commande
        $invoices = [
            ['FA-24-0187', 'CLI-00063', 82, -52, '4860.00', '0.00', 3, null],
            ['FA-24-0190', 'CLI-00085', 18, 12, '980.00', '0.00', 0, null],
            ['FA-24-0195', 'CLI-00057', 40, -10, '3200.00', '0.00', 1, null],
            ['FA-24-0199', 'CLI-00063', 35, -5, '1200.00', '1200.00', 0, null],
            ['FA-24-0201', 'CLI-00042', 19, 41, '2142.00', '2142.00', 0, $invoiced],
        ];
        foreach ($invoices as [$number, $customer, $issued, $due, $amount, $paid, $level, $order]) {
            $this->persist(new Invoice(
                $number,
                $this->customers[$customer],
                $this->today->modify("-{$issued} days"),
                $this->today->modify(sprintf('%+d days', $due)),
                $amount,
                $paid,
                $level,
                $order,
            ));
        }
    }

    private function purchaseOrders(): void
    {
        $a = $this->articles;

        // Partiellement reçue : reliquat de 48 paires de gants (4 cartons).
        $this->purchaseOrder('CF-24018', 'FRN-0021', 'ORL', PurchaseOrderStatus::PartiallyReceived, 9, 5)
            ->addLine($a['ART-30205'], 96, '2.60', 48)
            ->addLine($a['ART-30210'], 60, '3.10', 60);

        $this->purchaseOrder('CF-24021', 'FRN-0007', 'ORL', PurchaseOrderStatus::Sent, 3, 4)
            ->addLine($a['ART-10452'], 120, '9.80');

        // Brouillon : pas encore envoyée, ne compte pas dans les quantités attendues.
        $this->purchaseOrder('CF-24023', 'FRN-0034', 'ORL', PurchaseOrderStatus::Draft, 0, null)
            ->addLine($a['ART-40315'], 48, '3.90');

        // Montant HT de 5 000 € ou plus : validation supplémentaire par un Administrateur.
        $this->purchaseOrder('CF-24024', 'FRN-0045', 'ORL', PurchaseOrderStatus::Validated, 1, 10)
            ->addLine($a['ART-50401'], 2000, '1.05')
            ->addLine($a['ART-50410'], 400, '6.50')
            ->addLine($a['ART-50415'], 360, '1.10');

        $this->purchaseOrder('CF-24015', 'FRN-0012', 'LYO', PurchaseOrderStatus::Closed, 30, null)
            ->addLine($a['ART-20118'], 5, '74.00', 5);
    }

    private function order(string $number, string $customer, string $warehouse, CustomerOrderStatus $status, int $daysAgo): CustomerOrder
    {
        return $this->persist(new CustomerOrder(
            $number,
            $this->customers[$customer],
            $this->warehouses[$warehouse],
            $status,
            $this->today->modify("-{$daysAgo} days"),
        ));
    }

    private function purchaseOrder(string $number, string $supplier, string $warehouse, PurchaseOrderStatus $status, int $daysAgo, ?int $expectedIn): PurchaseOrder
    {
        return $this->persist(new PurchaseOrder(
            $number,
            $this->suppliers[$supplier],
            $this->warehouses[$warehouse],
            $status,
            $this->today->modify("-{$daysAgo} days"),
            $expectedIn === null ? null : $this->today->modify("+{$expectedIn} days"),
        ));
    }

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    private function persist(object $entity): object
    {
        $this->manager->persist($entity);

        return $entity;
    }
}
