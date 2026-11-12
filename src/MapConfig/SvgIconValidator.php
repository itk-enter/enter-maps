<?php

declare(strict_types=1);

namespace App\MapConfig;

use enshrined\svgSanitize\Sanitizer;

/**
 * Checks an SVG icon from an editor.
 *
 * enshrined/svg-sanitize does the security check. The sanitizer compares the
 * elements and attributes with allowlists and removes references to other
 * servers. This class does not keep the clean SVG. It rejects the SVG if the
 * sanitizer reports an item that it removed, so the stored SVG is the file of
 * the editor.
 *
 * The sanitizer removes a document type and PHP tags but does not report
 * them, so this class rejects them first. It also adds the rules of an icon: a
 * maximum size, and a viewBox so that the map can scale the icon.
 *
 * The map also shows the SVG as an image, not as markup in the page, and a
 * browser does not run scripts in an SVG image.
 */
final readonly class SvgIconValidator
{
    public const int MAX_LENGTH = 10240;

    private const string SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

    /**
     * @return list<string> the errors; empty if the SVG is permitted
     */
    public function validate(string $svg): array
    {
        if (\strlen($svg) > self::MAX_LENGTH) {
            return [\sprintf('The SVG is larger than %d bytes.', self::MAX_LENGTH)];
        }

        // A document type can declare entities that load other files.
        if (1 === preg_match('/<!DOCTYPE|<!ENTITY/i', $svg)) {
            return ['The SVG has a document type. Remove the <!DOCTYPE> line.'];
        }

        // Only the XML declaration is permitted. Other instructions can run code or load a style sheet.
        if (1 === preg_match('/<\?(?!xml\s)/i', $svg)) {
            return ['The SVG holds a processing instruction. Remove all "<?" lines except "<?xml ...?>".'];
        }

        $sanitizer = new Sanitizer();
        $sanitizer->removeRemoteReferences(true);
        $clean = $sanitizer->sanitize($svg);

        $errors = [];
        foreach ($sanitizer->getXmlIssues() as $issue) {
            // The sanitizer removes comments. A comment cannot run a script, so it is not an error.
            if ("Suspicious node '#comment'" === $issue['message']) {
                continue;
            }
            $message = rtrim(trim($issue['message']), '.').'.';
            $errors[] = $issue['line'] > 0 ? \sprintf('Line %d: %s', $issue['line'], $message) : $message;
        }

        if (false === $clean) {
            return [] === $errors ? ['The SVG is not valid XML.'] : $errors;
        }

        if ([] !== $errors) {
            return $errors;
        }

        // The sanitizer accepted the SVG, so it is well-formed XML without a document type.
        $document = new \DOMDocument();
        $document->loadXML($svg, \LIBXML_NONET);
        $root = $document->documentElement;
        if (!$root instanceof \DOMElement || 'svg' !== $root->localName || self::SVG_NAMESPACE !== $root->namespaceURI) {
            return ['The SVG must start with <svg xmlns="http://www.w3.org/2000/svg">.'];
        }

        if (!$root->hasAttribute('viewBox')) {
            return ['The SVG must have a viewBox attribute, for example viewBox="0 0 24 24".'];
        }

        return [];
    }
}
