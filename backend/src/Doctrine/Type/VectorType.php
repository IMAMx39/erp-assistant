<?php

declare(strict_types=1);

namespace App\Doctrine\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use INM39\MariadbVectorBundle\Doctrine\Type\VectorType as BundleVectorType;

/**
 * Type « vector » du bundle MariaDB Vector, avec l'écriture et la lecture corrigées.
 *
 * Deux bugs du bundle (v1.0.1) :
 *  - écriture : le vecteur part en binaire float32, PDO le transmet comme une chaîne utf8mb4
 *    et MariaDB le rejette (« Incorrect vector value »). On l'écrit en JSON converti par VEC_FromText() ;
 *  - lecture : toute valeur dont le premier octet est « [ » est décodée comme du JSON. Un vecteur
 *    binaire peut commencer par l'octet 0x5B : la lecture plantait (« Syntax error »), trouvé par
 *    l'évaluation du J6. On n'accepte le JSON que s'il se décode en liste, sinon on lit du binaire.
 *
 * À supprimer quand les correctifs seront publiés dans inm39/mariadb-vector-bundle.
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
        if (\is_resource($value)) {
            $value = stream_get_contents($value);
        }

        if (!\is_string($value) || $value === '') {
            return $this->inner()->convertToPHPValue($value, $platform);
        }

        // Forme texte (résultat de VEC_ToText) : seulement si c'est vraiment une liste JSON.
        if ($value[0] === '[' && str_ends_with($value, ']')) {
            $decoded = json_decode($value, true);
            if (\is_array($decoded) && array_is_list($decoded)) {
                return array_map(floatval(...), $decoded);
            }
        }

        // Forme native : suite de float32 little-endian.
        /** @var array<int, float> $floats */
        $floats = unpack('g*', $value) ?: [];

        return array_values($floats);
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
