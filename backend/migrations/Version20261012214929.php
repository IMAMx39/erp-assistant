<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261012214929 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Embeddings des chunks (VECTOR(768)) et index vectoriel cosinus';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        // Les chunks existants n'ont pas d'embedding : on vide l'index documentaire,
        // à reconstruire ensuite avec app:corpus:ingest (les chunks partent en cascade).
        $this->addSql('DELETE FROM document');
        $this->addSql('ALTER TABLE chunk ADD embedding VECTOR(768) NOT NULL');
        // Index HNSW ajouté à la main : Doctrine ne sait pas le générer.
        $this->addSql('ALTER TABLE chunk ADD VECTOR INDEX idx_chunk_embedding (embedding) M=8 DISTANCE=cosine');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chunk DROP embedding');
    }
}
