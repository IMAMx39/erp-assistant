<?php

declare(strict_types=1);

namespace App\Embedding;

interface EmbedderInterface
{
    /**
     * Vectorise des passages destinés à être indexés.
     *
     * @param list<string> $texts
     *
     * @return list<list<float>> un vecteur par texte, dans le même ordre
     */
    public function embedDocuments(array $texts): array;

    /**
     * Vectorise une question utilisateur.
     *
     * @return list<float>
     */
    public function embedQuery(string $query): array;
}
