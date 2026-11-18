<?php

declare(strict_types=1);

namespace App\Builder;

use App\Entity\MapConfiguration;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Writes the embed code of a map: an iframe that a site puts in its HTML.
 *
 * The title is necessary for screen readers. The height is fixed, and the map
 * fills the frame. The referrer policy is the default of the browsers, but
 * the code writes it, so that a site does not set a stricter value by
 * mistake.
 *
 * Example:
 *
 *     <iframe src="https://maps.example.dk/embed/leaflet/01M7ACK5C4TC8TM4A5Z88ZG2YM"
 *             title="Toiletter i Aarhus"
 *             width="100%" height="700" loading="lazy" style="border:0"
 *             referrerpolicy="strict-origin-when-cross-origin"></iframe>
 */
final readonly class EmbedCode
{
    public const int HEIGHT = 700;

    private const string INDENT = '        ';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function html(MapConfiguration $map): string
    {
        $url = $this->urlGenerator->generate('app_embed_leaflet', ['id' => $map->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $title = $map->getConfig()->title ?? null;

        return implode("\n", [
            \sprintf('<iframe src="%s"', $this->escape($url)),
            \sprintf('%stitle="%s"', self::INDENT, $this->escape(\is_string($title) ? $title : '')),
            \sprintf('%swidth="100%%" height="%d" loading="lazy" style="border:0"', self::INDENT, self::HEIGHT),
            self::INDENT.'referrerpolicy="strict-origin-when-cross-origin"></iframe>',
        ]);
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, \ENT_QUOTES | \ENT_HTML5);
    }
}
