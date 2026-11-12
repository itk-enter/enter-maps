<?php

declare(strict_types=1);

namespace App\Enter;

/**
 * The filter is not valid CQL2-JSON, or it uses a part of CQL2 that the proxy
 * does not support. config/schema/cql2-filter.json defines the supported part.
 */
final class InvalidFilterException extends \InvalidArgumentException
{
}
