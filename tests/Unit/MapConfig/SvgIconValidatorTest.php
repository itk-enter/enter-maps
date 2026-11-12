<?php

declare(strict_types=1);

namespace App\Tests\Unit\MapConfig;

use App\MapConfig\SvgIconValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * itools/svgsecuritycheck does the security check. These tests make sure
 * that the attacks that matter for an icon stay rejected after an update of
 * the package, and that SVGs from drawing programs stay permitted.
 */
final class SvgIconValidatorTest extends TestCase
{
    private const string OPEN = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">';

    /**
     * @return iterable<string, array{string}>
     */
    public static function permittedProvider(): iterable
    {
        yield 'path' => [self::OPEN.'<path d="M4 6h16v2H4z" fill="#fff"/></svg>'];
        yield 'shapes in a group' => [self::OPEN.'<g transform="translate(1 1)" opacity="0.8"><circle cx="12" cy="12" r="4"/><rect x="2" y="2" width="4" height="4" rx="1"/><polygon points="1,1 2,2 3,1"/><line x1="0" y1="0" x2="1" y2="1" stroke="white" stroke-width="2"/></g></svg>'];
        yield 'XML declaration and comment' => ['<?xml version="1.0" encoding="UTF-8"?>'.self::OPEN.'<!-- Bænk --><path d="M0 0h1v1z"/></svg>'];
        yield 'id, class and style from a drawing program' => [self::OPEN.'<style>.a{fill:#fff}</style><path id="p" class="a" style="stroke:red" d="M0 0h1v1z"/></svg>'];
        yield 'gradient in the same file' => [self::OPEN.'<defs><linearGradient id="g"><stop offset="0" stop-color="#fff"/></linearGradient></defs><path fill="url(#g)" d="M0 0h1v1z"/></svg>'];
        yield 'use of an element in the same file' => ['<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 24 24"><path id="a" d="M0 0h1v1z"/><use xlink:href="#a" x="2"/></svg>'];
    }

    #[DataProvider('permittedProvider')]
    public function testAcceptsAPermittedSvg(string $svg): void
    {
        $this->assertSame([], new SvgIconValidator()->validate($svg));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rejectedProvider(): iterable
    {
        yield 'script' => [self::OPEN.'<script>alert(1)</script></svg>', '<script> is not allowed'];
        yield 'event attribute' => [self::OPEN.'<path d="M0 0" onload="alert(1)"/></svg>', 'onload= event handler'];
        yield 'foreign object' => [self::OPEN.'<foreignObject><div xmlns="http://www.w3.org/1999/xhtml">x</div></foreignObject></svg>', '<foreignObject> is not allowed'];
        yield 'image from another server' => [self::OPEN.'<image href="https://example.com/a.png"/></svg>', 'not https://example.com/a.png'];
        yield 'link to another site' => [self::OPEN.'<a href="https://example.com"><path d="M0 0"/></a></svg>', 'not https://example.com'];
        yield 'javascript link' => [self::OPEN.'<a href="javascript:alert(1)"><path d="M0 0"/></a></svg>', 'not javascript:alert(1)'];
        yield 'fill from another server' => [self::OPEN.'<path d="M0 0" fill="url(https://example.com/#a)"/></svg>', 'url() in the fill attribute'];
        yield 'CSS from another server' => [self::OPEN.'<style>@import url(https://example.com/a.css);</style></svg>', 'CSS containing'];
        yield 'element of another namespace' => [self::OPEN.'<x:path xmlns:x="https://example.com" d="M0 0"/></svg>', 'namespace https://example.com'];
        yield 'processing instruction' => [self::OPEN.'<?php echo 1; ?><path d="M0 0"/></svg>', '<?php?>'];
        yield 'stylesheet instruction' => ['<?xml-stylesheet href="https://example.com/a.css"?>'.self::OPEN.'<path d="M0 0"/></svg>', '<?xml-stylesheet?>'];
        yield 'entities' => ['<!DOCTYPE svg [<!ENTITY a SYSTEM "file:///etc/passwd">]>'.self::OPEN.'<path d="M0 0"/></svg>', 'DOCTYPE'];
        yield 'not XML' => ['<svg viewBox="0 0 24 24"><path></svg>', 'not well-formed XML'];
        yield 'not SVG' => ['<html xmlns="http://www.w3.org/1999/xhtml"/>', 'root element must be <svg>'];
        yield 'SVG without namespace' => ['<svg viewBox="0 0 24 24"/>', 'must declare xmlns'];
        yield 'no view box' => ['<svg xmlns="http://www.w3.org/2000/svg"><path d="M0 0"/></svg>', 'viewBox'];
        yield 'too large' => [self::OPEN.'<path d="'.str_repeat('M0 0', 3000).'"/></svg>', 'larger than'];
    }

    #[DataProvider('rejectedProvider')]
    public function testRejectsAnSvgThatIsNotPermitted(string $svg, string $message): void
    {
        $errors = new SvgIconValidator()->validate($svg);

        $this->assertNotSame([], $errors);
        $this->assertStringContainsString($message, implode(' ', $errors));
    }

    public function testGivesAnErrorForEachProblem(): void
    {
        $errors = new SvgIconValidator()->validate(self::OPEN.'<script/><path d="M0 0" onclick="x()"/></svg>');

        $this->assertSame(['<script> is not allowed in uploaded SVGs.', 'onclick= event handler attributes are not allowed.'], $errors);
    }
}
