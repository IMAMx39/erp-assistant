<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261012214019 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE chunk (id INT AUTO_INCREMENT NOT NULL, position INT NOT NULL, heading VARCHAR(255) NOT NULL, content LONGTEXT NOT NULL, document_id INT NOT NULL, FULLTEXT INDEX ft_chunk_search (heading, content), INDEX IDX_9506712EC33F7837 (document_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE document (id INT AUTO_INCREMENT NOT NULL, path VARCHAR(255) NOT NULL, title VARCHAR(255) NOT NULL, module VARCHAR(50) NOT NULL, tags JSON NOT NULL, content_hash VARCHAR(64) NOT NULL, ingested_at DATETIME NOT NULL, UNIQUE INDEX uniq_document_path (path), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE chunk ADD CONSTRAINT FK_9506712EC33F7837 FOREIGN KEY (document_id) REFERENCES document (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chunk DROP FOREIGN KEY FK_9506712EC33F7837');
        $this->addSql('DROP TABLE chunk');
        $this->addSql('DROP TABLE document');
    }
}
