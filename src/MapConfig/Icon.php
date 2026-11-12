<?php

declare(strict_types=1);

namespace App\MapConfig;

/**
 * The icon of the markers of a layer: a name from the list of icons on the
 * server, or an SVG from the editor. One of the two is set.
 */
final readonly class Icon
{
    /**
     * @param string|null $svg an SVG that SvgIconValidator accepts
     */
    public function __construct(
        public ?string $name,
        public ?string $svg,
    ) {
    }

    /**
     * @param array{name?: string, svg?: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self($data['name'] ?? null, $data['svg'] ?? null);
    }
}
