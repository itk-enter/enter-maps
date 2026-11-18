<?php

declare(strict_types=1);

namespace App\Embed;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Translation\LocaleSwitcher;
use Twig\Environment;

/**
 * Renders the embed page of the Leaflet renderer from the settings in
 * map_json.leaflet and the embed options in the query string. The embed page
 * of a stored map and the preview in the builder use it.
 */
final readonly class LeafletPage
{
    public function __construct(
        private Environment $twig,
        private EmbedOptionsParser $optionsParser,
        private LocaleSwitcher $localeSwitcher,
    ) {
    }

    /**
     * @param array<mixed> $query the query string of the request
     */
    public function render(\stdClass $settings, array $query): Response
    {
        $options = $this->optionsParser->parse($query, $settings);

        // The script gets the objects, so that "{}" stays an object. Twig cannot
        // loop over the properties of an object, so the template gets arrays.
        $map = json_decode(json_encode($settings, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        return new Response($this->localeSwitcher->runWithLocale(
            $options->locale,
            fn (): string => $this->twig->render('embed/leaflet.html.twig', ['settings' => $settings, 'map' => $map, 'options' => $options]),
        ));
    }

    /**
     * The page for a map that does not exist or is deleted, with status 404.
     *
     * @param array<mixed> $query the query string of the request
     */
    public function notAvailable(array $query): Response
    {
        $locale = $this->optionsParser->locale($query);

        return new Response($this->localeSwitcher->runWithLocale(
            $locale,
            fn (): string => $this->twig->render('embed/not_available.html.twig', ['locale' => $locale]),
        ), Response::HTTP_NOT_FOUND);
    }
}
