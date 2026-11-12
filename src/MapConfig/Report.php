<?php

declare(strict_types=1);

namespace App\MapConfig;

/**
 * A link for each feature, for example to a form that corrects the data.
 */
final readonly class Report
{
    /**
     * @param string                        $url    {id} and {seeAlso} get the values of the entity
     * @param '_blank'|'_top'|'postMessage' $target with postMessage, the map sends a message to the page around the frame
     */
    public function __construct(
        public string $label,
        public string $url,
        public string $target,
    ) {
    }

    /**
     * @param array{label: string, url: string, target?: '_blank'|'_top'|'postMessage'} $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['label'], $data['url'], $data['target'] ?? '_blank');
    }
}
