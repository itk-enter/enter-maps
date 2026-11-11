<?php

declare(strict_types=1);

namespace App\Enter;

/**
 * The "where" filter of a request is too long or holds characters that are not permitted.
 */
final class InvalidWhereException extends \InvalidArgumentException
{
}
