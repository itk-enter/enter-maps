<?php

declare(strict_types=1);

namespace App\Builder;

use App\Enter\EnterClient;
use App\Enter\EnterUnavailableException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Finds the paths of the properties of a data set, for the suggestions in
 * the map form. Enter does not list the attributes of a data set, so the
 * class reads the first entities and collects the paths in them, with the
 * first value of each as an example.
 *
 * A nested object gives a path for each key, for example
 * "address.streetAddress". A list is one path. The result is in a cache for
 * 10 minutes, because the form renders again after each change.
 *
 * Example: the properties {"name": "Dokk1", "address": {"streetAddress":
 * "Hack Kampmanns Plads 2"}} give the paths "address.streetAddress" with the
 * example "Hack Kampmanns Plads 2", and "name" with the example "Dokk1".
 */
final readonly class DataSetFields
{
    public const int SAMPLE_SIZE = 20;

    private const int EXAMPLE_LENGTH = 60;

    private const string PATH_PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    /**
     * The time in seconds that the cache keeps an empty result for a data set
     * that Enter does not list. The data set can come back.
     */
    private const int UNKNOWN_LIFETIME = 60;

    public function __construct(
        private EnterClient $enter,
        #[Autowire(service: 'cache.data_set_fields')]
        private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * @return array<string, string> the example of each path, sorted by path. Empty if Enter does not answer, or
     *                               does not list the data set.
     */
    public function paths(?string $dataSetId): array
    {
        if (null === $dataSetId || '' === $dataSetId) {
            return [];
        }

        $item = $this->cache->getItem(hash('xxh128', $dataSetId));
        if ($item->isHit()) {
            /** @var array<string, string> $paths */
            $paths = $item->get();

            return $paths;
        }

        try {
            $dataSet = $this->enter->getDataSets()[$dataSetId] ?? null;
            $paths = [];
            if (null === $dataSet) {
                $item->expiresAfter(self::UNKNOWN_LIFETIME);
            } else {
                foreach ($this->enter->getSample($dataSet, self::SAMPLE_SIZE) as $feature) {
                    $this->collect(\is_array($feature['properties'] ?? null) ? $feature['properties'] : [], '', $paths);
                }
                ksort($paths);
            }
        } catch (EnterUnavailableException) {
            // Nothing goes into the cache, so the next render asks Enter again.
            return [];
        }

        $this->cache->save($item->set($paths));

        return $paths;
    }

    /**
     * @param array<mixed>          $properties
     * @param array<string, string> $paths      the paths so far, with the first example of each
     */
    private function collect(array $properties, string $prefix, array &$paths): void
    {
        foreach ($properties as $key => $value) {
            if (!\is_string($key) || 1 !== preg_match(self::PATH_PATTERN, $key)) {
                continue;
            }

            $path = $prefix.$key;
            if (\is_array($value) && !array_is_list($value)) {
                $this->collect($value, $path.'.', $paths);

                continue;
            }

            $example = $this->example($value);
            if (!isset($paths[$path]) || ('' === $paths[$path] && '' !== $example)) {
                $paths[$path] = $example;
            }
        }
    }

    private function example(mixed $value): string
    {
        $text = match (true) {
            \is_bool($value) => $value ? 'true' : 'false',
            \is_scalar($value) => (string) $value,
            \is_array($value) => implode(', ', array_map($this->example(...), \array_slice($value, 0, 3))),
            default => '',
        };

        return mb_strlen($text) > self::EXAMPLE_LENGTH ? mb_substr($text, 0, self::EXAMPLE_LENGTH - 1).'…' : $text;
    }
}
