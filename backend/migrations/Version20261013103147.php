<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261013103147 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'ERP fictif (articles, stocks, clients, commandes, factures) consulté par les outils de l\'agent';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE erp_article (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(9) NOT NULL, label VARCHAR(80) NOT NULL, family VARCHAR(50) NOT NULL, stock_unit VARCHAR(20) NOT NULL, sale_price NUMERIC(10, 2) NOT NULL, purchase_unit VARCHAR(20) DEFAULT NULL, conversion_factor INT NOT NULL, vat_rate NUMERIC(4, 1) NOT NULL, status VARCHAR(20) NOT NULL, main_supplier_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_5B4B2D9477153098 (code), INDEX IDX_5B4B2D94FE5EA51A (main_supplier_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE erp_customer (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(9) NOT NULL, name VARCHAR(100) NOT NULL, country VARCHAR(2) NOT NULL, credit_limit NUMERIC(12, 2) NOT NULL, vat_number VARCHAR(20) DEFAULT NULL, invoicing_mode VARCHAR(20) NOT NULL, UNIQUE INDEX UNIQ_D2D36C1A77153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE erp_customer_order (id INT AUTO_INCREMENT NOT NULL, number VARCHAR(12) NOT NULL, status VARCHAR(20) NOT NULL, ordered_at DATETIME NOT NULL, customer_id INT NOT NULL, warehouse_id INT NOT NULL, UNIQUE INDEX UNIQ_3B72339C96901F54 (number), INDEX IDX_3B72339C9395C3F3 (customer_id), INDEX IDX_3B72339C5080ECDE (warehouse_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE erp_customer_order_line (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, unit_price NUMERIC(10, 2) NOT NULL, reserved_quantity INT NOT NULL, shipped_quantity INT NOT NULL, order_id INT NOT NULL, article_id INT NOT NULL, INDEX IDX_119E0AB58D9F6D38 (order_id), INDEX IDX_119E0AB57294869C (article_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE erp_invoice (id INT AUTO_INCREMENT NOT NULL, number VARCHAR(12) NOT NULL, issued_at DATETIME NOT NULL, due_at DATETIME NOT NULL, amount_including_tax NUMERIC(12, 2) NOT NULL, paid_amount NUMERIC(12, 2) NOT NULL, reminder_level INT NOT NULL, customer_id INT NOT NULL, customer_order_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_C91434B696901F54 (number), INDEX IDX_C91434B69395C3F3 (customer_id), INDEX IDX_C91434B6A15A2E17 (customer_order_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE erp_location (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(20) NOT NULL, type VARCHAR(20) NOT NULL, frozen TINYINT NOT NULL, warehouse_id INT NOT NULL, UNIQUE INDEX uniq_location_code (warehouse_id, code), INDEX IDX_D746BD85080ECDE (warehouse_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE erp_purchase_order (id INT AUTO_INCREMENT NOT NULL, number VARCHAR(12) NOT NULL, status VARCHAR(20) NOT NULL, ordered_at DATETIME NOT NULL, expected_at DATETIME DEFAULT NULL, supplier_id INT NOT NULL, warehouse_id INT NOT NULL, UNIQUE INDEX UNIQ_218CC58D96901F54 (number), INDEX IDX_218CC58D2ADD6D8C (supplier_id), INDEX IDX_218CC58D5080ECDE (warehouse_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE erp_purchase_order_line (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, unit_price NUMERIC(10, 2) NOT NULL, received_quantity INT NOT NULL, order_id INT NOT NULL, article_id INT NOT NULL, INDEX IDX_E06459FD8D9F6D38 (order_id), INDEX IDX_E06459FD7294869C (article_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE erp_stock_level (id INT AUTO_INCREMENT NOT NULL, quantity INT NOT NULL, status VARCHAR(20) NOT NULL, article_id INT NOT NULL, location_id INT NOT NULL, INDEX IDX_340D57907294869C (article_id), INDEX IDX_340D579064D218E (location_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE erp_stock_rule (id INT AUTO_INCREMENT NOT NULL, minimum INT NOT NULL, maximum INT DEFAULT NULL, article_id INT NOT NULL, warehouse_id INT NOT NULL, UNIQUE INDEX uniq_stock_rule (article_id, warehouse_id), INDEX IDX_702965247294869C (article_id), INDEX IDX_702965245080ECDE (warehouse_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE erp_supplier (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(8) NOT NULL, name VARCHAR(100) NOT NULL, purchasing_email VARCHAR(150) NOT NULL, UNIQUE INDEX UNIQ_C8C08E6D77153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE erp_warehouse (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(3) NOT NULL, name VARCHAR(100) NOT NULL, UNIQUE INDEX UNIQ_685E20C077153098 (code), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE erp_article ADD CONSTRAINT FK_5B4B2D94FE5EA51A FOREIGN KEY (main_supplier_id) REFERENCES erp_supplier (id)');
        $this->addSql('ALTER TABLE erp_customer_order ADD CONSTRAINT FK_3B72339C9395C3F3 FOREIGN KEY (customer_id) REFERENCES erp_customer (id)');
        $this->addSql('ALTER TABLE erp_customer_order ADD CONSTRAINT FK_3B72339C5080ECDE FOREIGN KEY (warehouse_id) REFERENCES erp_warehouse (id)');
        $this->addSql('ALTER TABLE erp_customer_order_line ADD CONSTRAINT FK_119E0AB58D9F6D38 FOREIGN KEY (order_id) REFERENCES erp_customer_order (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE erp_customer_order_line ADD CONSTRAINT FK_119E0AB57294869C FOREIGN KEY (article_id) REFERENCES erp_article (id)');
        $this->addSql('ALTER TABLE erp_invoice ADD CONSTRAINT FK_C91434B69395C3F3 FOREIGN KEY (customer_id) REFERENCES erp_customer (id)');
        $this->addSql('ALTER TABLE erp_invoice ADD CONSTRAINT FK_C91434B6A15A2E17 FOREIGN KEY (customer_order_id) REFERENCES erp_customer_order (id)');
        $this->addSql('ALTER TABLE erp_location ADD CONSTRAINT FK_D746BD85080ECDE FOREIGN KEY (warehouse_id) REFERENCES erp_warehouse (id)');
        $this->addSql('ALTER TABLE erp_purchase_order ADD CONSTRAINT FK_218CC58D2ADD6D8C FOREIGN KEY (supplier_id) REFERENCES erp_supplier (id)');
        $this->addSql('ALTER TABLE erp_purchase_order ADD CONSTRAINT FK_218CC58D5080ECDE FOREIGN KEY (warehouse_id) REFERENCES erp_warehouse (id)');
        $this->addSql('ALTER TABLE erp_purchase_order_line ADD CONSTRAINT FK_E06459FD8D9F6D38 FOREIGN KEY (order_id) REFERENCES erp_purchase_order (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE erp_purchase_order_line ADD CONSTRAINT FK_E06459FD7294869C FOREIGN KEY (article_id) REFERENCES erp_article (id)');
        $this->addSql('ALTER TABLE erp_stock_level ADD CONSTRAINT FK_340D57907294869C FOREIGN KEY (article_id) REFERENCES erp_article (id)');
        $this->addSql('ALTER TABLE erp_stock_level ADD CONSTRAINT FK_340D579064D218E FOREIGN KEY (location_id) REFERENCES erp_location (id)');
        $this->addSql('ALTER TABLE erp_stock_rule ADD CONSTRAINT FK_702965247294869C FOREIGN KEY (article_id) REFERENCES erp_article (id)');
        $this->addSql('ALTER TABLE erp_stock_rule ADD CONSTRAINT FK_702965245080ECDE FOREIGN KEY (warehouse_id) REFERENCES erp_warehouse (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE erp_article DROP FOREIGN KEY FK_5B4B2D94FE5EA51A');
        $this->addSql('ALTER TABLE erp_customer_order DROP FOREIGN KEY FK_3B72339C9395C3F3');
        $this->addSql('ALTER TABLE erp_customer_order DROP FOREIGN KEY FK_3B72339C5080ECDE');
        $this->addSql('ALTER TABLE erp_customer_order_line DROP FOREIGN KEY FK_119E0AB58D9F6D38');
        $this->addSql('ALTER TABLE erp_customer_order_line DROP FOREIGN KEY FK_119E0AB57294869C');
        $this->addSql('ALTER TABLE erp_invoice DROP FOREIGN KEY FK_C91434B69395C3F3');
        $this->addSql('ALTER TABLE erp_invoice DROP FOREIGN KEY FK_C91434B6A15A2E17');
        $this->addSql('ALTER TABLE erp_location DROP FOREIGN KEY FK_D746BD85080ECDE');
        $this->addSql('ALTER TABLE erp_purchase_order DROP FOREIGN KEY FK_218CC58D2ADD6D8C');
        $this->addSql('ALTER TABLE erp_purchase_order DROP FOREIGN KEY FK_218CC58D5080ECDE');
        $this->addSql('ALTER TABLE erp_purchase_order_line DROP FOREIGN KEY FK_E06459FD8D9F6D38');
        $this->addSql('ALTER TABLE erp_purchase_order_line DROP FOREIGN KEY FK_E06459FD7294869C');
        $this->addSql('ALTER TABLE erp_stock_level DROP FOREIGN KEY FK_340D57907294869C');
        $this->addSql('ALTER TABLE erp_stock_level DROP FOREIGN KEY FK_340D579064D218E');
        $this->addSql('ALTER TABLE erp_stock_rule DROP FOREIGN KEY FK_702965247294869C');
        $this->addSql('ALTER TABLE erp_stock_rule DROP FOREIGN KEY FK_702965245080ECDE');
        $this->addSql('DROP TABLE erp_article');
        $this->addSql('DROP TABLE erp_customer');
        $this->addSql('DROP TABLE erp_customer_order');
        $this->addSql('DROP TABLE erp_customer_order_line');
        $this->addSql('DROP TABLE erp_invoice');
        $this->addSql('DROP TABLE erp_location');
        $this->addSql('DROP TABLE erp_purchase_order');
        $this->addSql('DROP TABLE erp_purchase_order_line');
        $this->addSql('DROP TABLE erp_stock_level');
        $this->addSql('DROP TABLE erp_stock_rule');
        $this->addSql('DROP TABLE erp_supplier');
        $this->addSql('DROP TABLE erp_warehouse');
    }
}
