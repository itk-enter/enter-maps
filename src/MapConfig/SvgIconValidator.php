<?php

declare(strict_types=1);

namespace App\MapConfig;

/**
 * Checks an SVG icon from an editor against a list of permitted elements and
 * attributes.
 *
 * The validator rejects an SVG that holds anything else. It does not clean
 * the SVG, so the stored SVG is the file of the editor. The map also shows the
 * SVG as an image, not as markup in the page, and a browser does not run
 * scripts in an SVG image. Thus an error in this check cannot let a script
 * run.
 */
final readonly class SvgIconValidator
{
    public const int MAX_LENGTH = 10240;

    private const string SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

    private const array ELEMENTS = ['svg', 'g', 'path', 'circle', 'ellipse', 'rect', 'line', 'polyline', 'polygon'];

    private const array ATTRIBUTES = [
        'viewBox', 'width', 'height', 'd', 'cx', 'cy', 'r', 'rx', 'ry', 'x', 'y', 'x1', 'y1', 'x2', 'y2', 'points',
        'transform', 'fill', 'fill-rule', 'clip-rule', 'stroke', 'stroke-width', 'stroke-linecap', 'stroke-linejoin',
        'opacity', 'fill-opacity', 'stroke-opacity',
    ];

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

        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($svg, \LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->documentElement;
        if (!$loaded || !$root instanceof \DOMElement) {
            return ['The SVG is not valid XML.'];
        }

        if ('svg' !== $root->localName || self::SVG_NAMESPACE !== $root->namespaceURI) {
            return ['The SVG must start with <svg xmlns="http://www.w3.org/2000/svg">.'];
        }

        if (!$root->hasAttribute('viewBox')) {
            return ['The SVG must have a viewBox attribute, for example viewBox="0 0 24 24".'];
        }

        $errors = [];
        foreach ($document->childNodes as $node) {
            if ($node instanceof \DOMProcessingInstruction) {
                $errors[] = \sprintf('The SVG holds the instruction "%s", which is not permitted.', $node->target);
            }
        }

        return [...$errors, ...$this->checkElement($root)];
    }

    /**
     * @return list<string>
     */
    private function checkElement(\DOMElement $element): array
    {
        if (self::SVG_NAMESPACE !== $element->namespaceURI || !\in_array($element->localName, self::ELEMENTS, true)) {
            return [\sprintf('The SVG holds the element "%s", which is not permitted.', $element->nodeName)];
        }

        $errors = [];
        foreach ($element->attributes ?? [] as $attribute) {
            if (null !== $attribute->namespaceURI || !\in_array($attribute->localName, self::ATTRIBUTES, true)) {
                $errors[] = \sprintf('The SVG holds the attribute "%s", which is not permitted.', $attribute->nodeName);
            } elseif (1 === preg_match('/url\s*\(|javascript:|data:/i', $attribute->value)) {
                $errors[] = \sprintf('The attribute "%s" refers to something else. Use a colour or a number.', $attribute->nodeName);
            }
        }

        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $errors = [...$errors, ...$this->checkElement($child)];
            } elseif ($child instanceof \DOMProcessingInstruction) {
                $errors[] = \sprintf('The SVG holds the instruction "%s", which is not permitted.', $child->target);
            } elseif ($child instanceof \DOMText && '' !== trim($child->textContent)) {
                $errors[] = 'The SVG holds text. Change the text into paths.';
            }
        }

        return $errors;
    }
}
