<?php

declare(strict_types=1);

namespace App\Renderer;

/**
 * The icons that a configuration can use, by name.
 *
 * Each icon is SVG markup for an area of 24 × 24, in white. The map draws it
 * on a square in the colour of the layer. The markup is from this project, so
 * the map can put it into the page as markup. An SVG from an editor is
 * different: the map shows it as an image.
 *
 * These are the first versions. A designer can replace them; keep the names,
 * because stored configurations use them.
 */
final readonly class Icons
{
    public const string DEFAULT = 'pin';

    /**
     * The JSON Schema of the configuration has the same names.
     *
     * @return array<string, string> the markup of each icon
     */
    public function all(): array
    {
        return [
            'toilet' => '<text x="12" y="16" text-anchor="middle" font-family="Arial, sans-serif" font-size="10" font-weight="700">WC</text>',
            'parking' => '<path fill-rule="evenodd" d="M7 4h6a5 5 0 0 1 0 10h-3v6H7V4Zm3 3v4h3a2 2 0 0 0 0-4h-3Z"/>',
            'handicap-parking' => '<path d="M12 4a2 2 0 1 1 0 4 2 2 0 0 1 0-4Zm-1 5.5h2l.6 3H17v2h-3.9l-.4-2H11a4.5 4.5 0 1 0 4.4 5.5h2A6.5 6.5 0 1 1 11 9.5Z"/>',
            'bench' => '<path d="M5 4h1.5v7.5H5V4Zm12.5 0H19v7.5h-1.5V4ZM4 5h16v1.5H4V5Zm0 3h16v1.5H4V8ZM2 11.5h20V14H2v-2.5ZM4 14h1.5v5H4v-5Zm14.5 0H20v5h-1.5v-5Z"/>',
            'pin' => '<path fill-rule="evenodd" d="M12 3a6 6 0 0 0-6 6c0 4.5 6 12 6 12s6-7.5 6-12a6 6 0 0 0-6-6Zm0 8.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5Z"/>',
        ];
    }
}
