<?php

declare(strict_types=1);

namespace App\Builder;

use App\Enter\EnterClient;
use App\Enter\EnterUnavailableException;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The choices of the data set field in the map form: the data sets of Enter,
 * with the number of entities of each.
 *
 * The numbers are in a cache for 10 minutes, because the form renders again
 * after each change. If Enter does not give a number, the label has none,
 * and the cache keeps the numbers for 1 minute only.
 *
 * Example: "Offentlige toiletter (merged-public-toilets) – 412 steder".
 */
final readonly class DataSetChoices
{
    private const string COUNTS_KEY = 'data_set_counts';

    private const int MISSING_COUNT_LIFETIME = 60;

    public function __construct(
        private EnterClient $enter,
        #[Autowire(service: 'cache.builder')]
        private CacheItemPoolInterface $cache,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @return array<string, string>|null the ID of each data set, with the label as key and sorted by label. Null if
     *                                    Enter does not answer.
     */
    public function choices(): ?array
    {
        try {
            $dataSets = $this->enter->getDataSets();
        } catch (EnterUnavailableException) {
            return null;
        }

        $counts = $this->counts(array_values($dataSets));

        $choices = [];
        foreach ($dataSets as $dataSet) {
            $label = \sprintf('%s (%s)', $dataSet->title, $dataSet->id);
            $count = $counts[$dataSet->id] ?? null;
            if (null !== $count) {
                $label .= ' – '.$this->translator->trans('builder.form.data_set_count', ['%count%' => $count]);
            }
            $choices[$label] = $dataSet->id;
        }
        // Sorted as in the language of the page, for example Æ, Ø and Å last in Danish.
        $collator = new \Collator($this->translator->getLocale());
        uksort($choices, static fn (string $a, string $b): int => (int) $collator->compare($a, $b));

        return $choices;
    }

    /**
     * @param list<\App\Enter\DataSet> $dataSets
     *
     * @return array<string, int|null>
     */
    private function counts(array $dataSets): array
    {
        $item = $this->cache->getItem(self::COUNTS_KEY);
        if ($item->isHit()) {
            /** @var array<string, int|null> $counts */
            $counts = $item->get();

            return $counts;
        }

        $counts = $this->enter->countEntities($dataSets);
        if (\in_array(null, $counts, true)) {
            $item->expiresAfter(self::MISSING_COUNT_LIFETIME);
        }
        $this->cache->save($item->set($counts));

        return $counts;
    }
}
