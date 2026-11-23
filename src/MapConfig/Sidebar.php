<?php

declare(strict_types=1);

namespace App\MapConfig;

/**
 * The settings of the sidebar.
 */
final readonly class Sidebar
{
    /**
     * @param 'left'|'right' $position
     * @param bool           $list          show the list of the features
     * @param bool           $categories    show the layers as categories that the user can turn on and off
     * @param bool           $localSearch   search in the features of the map
     * @param bool           $addressSearch search for an address with Adressevælger
     * @param bool           $routeLink     show a link to a route to the feature in the detail view
     */
    public function __construct(
        public string $position,
        public bool $list,
        public bool $categories,
        public bool $localSearch,
        public bool $addressSearch,
        public bool $routeLink = true,
    ) {
    }

    /**
     * @param array{position?: 'left'|'right', list?: bool, categories?: bool, routeLink?: bool, search?: array{local?: bool, address?: bool}} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            position: $data['position'] ?? 'left',
            list: $data['list'] ?? true,
            categories: $data['categories'] ?? false,
            localSearch: $data['search']['local'] ?? false,
            addressSearch: $data['search']['address'] ?? false,
            routeLink: $data['routeLink'] ?? true,
        );
    }
}
