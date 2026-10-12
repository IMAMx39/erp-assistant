<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use INM39\MariadbVectorBundle\Doctrine\Type\VectorType as BundleVectorType;

/**
 * Type « vector » du bundle MariaDB Vector, avec une écriture corrigée.
 *
 * Le bundle (v1.0.1) envoie le vecteur en binaire float32 : PDO le transmet comme
 * une chaîne utf8mb4 et MariaDB le rejette (« Incorrect vector value »). On écrit
 * donc le vecteur en JSON converti par VEC_FromText(). La lecture binaire du bundle
 * fonctionne et reste utilisée.
 *
 * À supprimer quand le correctif sera publié dans inm39/mariadb-vector-bundle.
 */
final class VectorType extends Type
{
    private BundleVectorType $inner;

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $this->inner()->getSQLDeclaration($column, $platform);
    }

    /**
     * @return list<float>|null
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?array
    {
        return $this->inner()->convertToPHPValue($value, $platform);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!\is_array($value)) {
            throw new \InvalidArgumentException(sprintf(
                'Type « vector » : float[] attendu, « %s » reçu.',
                get_debug_type($value),
            ));
        }

        return json_encode(array_map(floatval(...), array_values($value)), \JSON_THROW_ON_ERROR);
    }

    public function convertToDatabaseValueSQL(string $sqlExpr, AbstractPlatform $platform): string
    {
        return sprintf('VEC_FromText(%s)', $sqlExpr);
    }

    private function inner(): BundleVectorType
    {
        return $this->inner ??= new BundleVectorType();
    }
}
