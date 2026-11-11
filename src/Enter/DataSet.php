<?php

declare(strict_types=1);

namespace App\Enter;

/**
 * A data set that Enter lists in /sources.json.
 */
final readonly class DataSet
{
    /**
     * @param string      $id         the ID of the data set, e.g. "merged-public-toilets"
     * @param string|null $model      the NGSI-LD entity type of the data set
     * @param string|null $contextUrl the JSON-LD context that gives the short attribute names of the model
     */
    public function __construct(
        public string $id,
        public string $title,
        public ?string $model,
        public ?string $contextUrl,
    ) {
    }
}
