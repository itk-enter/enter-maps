<?php

declare(strict_types=1);

namespace App\Embed;

/**
 * Applies the query string of an embed URL to the settings of a map.
 *
 * The parser accepts only the parameters in the table "Embed options" of the
 * plan. It ignores an unknown parameter and a value that is not valid, and
 * it does not give an error. An embed code on another site must not break
 * because of a wrong value. An option can only select, hide or move. It
 * cannot add a layer or a report link that the map does not have.
 *
 * Example: "?zoom=16&layers=toilets&sidebar=right&lang=en" gives zoom 16,
 * only the layer "toilets" on, the sidebar on the right, and English texts.
 */
final readonly class EmbedOptionsParser
{
    private const int MAX_ZOOM = 20;

    private const int MAX_SELECT_LENGTH = 500;

    /**
     * @param array<string, mixed> $query    the query string of the request
     * @param \stdClass            $settings the map_json of the Leaflet renderer
     */
    public function parse(array $query, \stdClass $settings): EmbedOptions
    {
        $sidebar = $settings->sidebar ?? null;

        return new EmbedOptions(
            locale: $this->locale($query, $settings),
            sidebar: $this->choice($query, 'sidebar', ['left', 'right', 'off']) ?? ($sidebar->position ?? 'left'),
            list: $this->flag($query, 'list') ?? (bool) ($sidebar->list ?? true),
            report: isset($settings->report) && false !== $this->flag($query, 'report'),
            zoom: $this->zoom($query),
            center: $this->center($query),
            layers: $this->layers($query, $settings),
            select: $this->select($query),
        );
    }

    /**
     * The language of the texts on the page: "lang" from the query string, or
     * the locale of the map. Without a map, the default is Danish.
     *
     * @param array<string, mixed> $query
     *
     * @return 'da'|'en'
     */
    public function locale(array $query, ?\stdClass $settings = null): string
    {
        $lang = $this->choice($query, 'lang', ['da', 'en']);
        if (null !== $lang) {
            return $lang;
        }

        $locale = $settings->locale ?? null;

        return \is_string($locale) && str_starts_with($locale, 'en') ? 'en' : 'da';
    }

    /**
     * @param array<string, mixed> $query
     */
    private function string(array $query, string $name): ?string
    {
        $value = $query[$name] ?? null;

        return \is_string($value) && '' !== $value ? $value : null;
    }

    /**
     * @template T of string
     *
     * @param array<string, mixed> $query
     * @param list<T>              $choices
     *
     * @return T|null
     */
    private function choice(array $query, string $name, array $choices): ?string
    {
        $value = $this->string($query, $name);

        foreach ($choices as $choice) {
            if ($choice === $value) {
                return $choice;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $query
     */
    private function flag(array $query, string $name): ?bool
    {
        return match ($this->string($query, $name)) {
            '1' => true,
            '0' => false,
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $query
     */
    private function zoom(array $query): ?int
    {
        $zoom = filter_var($this->string($query, 'zoom'), \FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => self::MAX_ZOOM]]);

        return \is_int($zoom) ? $zoom : null;
    }

    /**
     * Reads "lon,lat", as in the configuration, and gives the latitude first.
     *
     * @param array<string, mixed> $query
     *
     * @return array{float, float}|null
     */
    private function center(array $query): ?array
    {
        $parts = explode(',', $this->string($query, 'center') ?? '');
        if (2 !== \count($parts)) {
            return null;
        }

        $longitude = filter_var(trim($parts[0]), \FILTER_VALIDATE_FLOAT);
        $latitude = filter_var(trim($parts[1]), \FILTER_VALIDATE_FLOAT);
        if (!\is_float($longitude) || !\is_float($latitude) || abs($longitude) > 180 || abs($latitude) > 90) {
            return null;
        }

        return [$latitude, $longitude];
    }

    /**
     * Keeps only the ids of layers that the map has. If no id is left, all
     * layers are on.
     *
     * @param array<string, mixed> $query
     *
     * @return list<string>|null
     */
    private function layers(array $query, \stdClass $settings): ?array
    {
        $value = $this->string($query, 'layers');
        if (null === $value) {
            return null;
        }

        $known = [];
        foreach ($settings->layers ?? [] as $layer) {
            if ($layer instanceof \stdClass && \is_string($layer->id ?? null)) {
                $known[] = $layer->id;
            }
        }

        $layers = array_values(array_intersect(array_map(trim(...), explode(',', $value)), $known));

        return [] === $layers ? null : array_values(array_unique($layers));
    }

    /**
     * @param array<string, mixed> $query
     */
    private function select(array $query): ?string
    {
        $value = $this->string($query, 'select');

        return null !== $value && \strlen($value) <= self::MAX_SELECT_LENGTH ? $value : null;
    }
}
