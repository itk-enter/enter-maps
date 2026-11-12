<?php

declare(strict_types=1);

namespace App\Enter;

/**
 * A filter in CQL2-JSON, changed into an NGSI-LD query. Make it with
 * Cql2FilterParser.
 */
final readonly class Cql2Filter
{
    /**
     * @param string $query the NGSI-LD query; "and" and "or" have parentheses
     */
    public function __construct(
        public string $query,
    ) {
    }
}
