<?php

declare(strict_types=1);

namespace App\MapConfig;

/**
 * The JSON is not a valid map configuration. $errors tells why.
 */
final class InvalidMapConfigException extends \InvalidArgumentException
{
    /**
     * @param list<string> $errors one message for each error, with the JSON pointer of the value first
     */
    public function __construct(
        public readonly array $errors,
    ) {
        parent::__construct('The map configuration is not valid: '.implode(' ', $errors));
    }
}
