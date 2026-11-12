<?php

declare(strict_types=1);

namespace App\MapConfig;

use Itools\SvgSecurityCheck\SvgSecurityCheck;
use Itools\SvgSecurityCheck\Violation;

/**
 * Checks an SVG icon from an editor.
 *
 * itools/svgsecuritycheck does the security check: it compares the elements,
 * attributes, URLs and CSS with allowlists, and rejects an SVG that could run
 * a script or load a file from another server. It does not clean the SVG, so
 * the stored SVG is the file of the editor. This class adds the rules of an
 * icon: a maximum size, and a viewBox so that the map can scale the icon.
 *
 * The map also shows the SVG as an image, not as markup in the page, and a
 * browser does not run scripts in an SVG image.
 */
final readonly class SvgIconValidator
{
    public const int MAX_LENGTH = 10240;

    /**
     * @return list<string> the errors; empty if the SVG is permitted
     */
    public function validate(string $svg): array
    {
        if (\strlen($svg) > self::MAX_LENGTH) {
            return [\sprintf('The SVG is larger than %d bytes.', self::MAX_LENGTH)];
        }

        $result = SvgSecurityCheck::checkString($svg);
        if (!$result->ok) {
            return array_values(array_map(static fn (Violation $violation): string => $violation->message.'.', $result->errors));
        }

        // The check accepted the SVG, so it is well-formed XML without a document type.
        $document = new \DOMDocument();
        $document->loadXML($svg, \LIBXML_NONET);
        if (!$document->documentElement?->hasAttribute('viewBox')) {
            return ['The SVG must have a viewBox attribute, for example viewBox="0 0 24 24".'];
        }

        return [];
    }
}
