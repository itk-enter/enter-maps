<?php

declare(strict_types=1);

namespace App\Tests\Unit\MapConfig;

use App\MapConfig\SvgIconValidator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

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
        yield 'xlink namespace that is not used' => ['<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 24 24"><path d="M0 0h1v1z"/></svg>'];
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
        yield 'script' => [self::OPEN.'<script>alert(1)</script></svg>', 'element "script"'];
        yield 'event attribute' => [self::OPEN.'<path d="M0 0" onload="alert(1)"/></svg>', 'attribute "onload"'];
        yield 'style' => [self::OPEN.'<style>path{fill:red}</style></svg>', 'element "style"'];
        yield 'style attribute' => [self::OPEN.'<path d="M0 0" style="fill:red"/></svg>', 'attribute "style"'];
        yield 'foreign object' => [self::OPEN.'<foreignObject><div xmlns="http://www.w3.org/1999/xhtml">x</div></foreignObject></svg>', 'element "foreignObject"'];
        yield 'image' => [self::OPEN.'<image href="https://example.com/a.png"/></svg>', 'element "image"'];
        yield 'use' => [self::OPEN.'<use href="#a"/></svg>', 'element "use"'];
        yield 'link' => [self::OPEN.'<a href="https://example.com"><path d="M0 0"/></a></svg>', 'element "a"'];
        yield 'xlink href' => ['<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 24 24"><path d="M0 0" xlink:href="#a"/></svg>', 'attribute "xlink:href"'];
        yield 'fill with url' => [self::OPEN.'<path d="M0 0" fill="url(https://example.com/#a)"/></svg>', 'refers to something else'];
        yield 'element of another namespace' => [self::OPEN.'<x:path xmlns:x="https://example.com" d="M0 0"/></svg>', 'element "x:path"'];
        yield 'text' => [self::OPEN.'<path d="M0 0"/>WC</svg>', 'holds text'];
        yield 'processing instruction' => [self::OPEN.'<?php echo 1; ?><path d="M0 0"/></svg>', 'instruction "php"'];
        yield 'stylesheet instruction' => ['<?xml-stylesheet href="https://example.com/a.css"?>'.self::OPEN.'<path d="M0 0"/></svg>', 'instruction "xml-stylesheet"'];
        yield 'document type' => ['<!DOCTYPE svg [<!ENTITY a SYSTEM "file:///etc/passwd">]>'.self::OPEN.'<path d="M0 0"/></svg>', 'document type'];
        yield 'not XML' => ['<svg viewBox="0 0 24 24"><path></svg>', 'not valid XML'];
        yield 'not SVG' => ['<html xmlns="http://www.w3.org/1999/xhtml"/>', 'must start with <svg'];
        yield 'SVG without namespace' => ['<svg viewBox="0 0 24 24"/>', 'must start with <svg'];
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
        $errors = new SvgIconValidator()->validate(self::OPEN.'<script/><path d="M0 0" onclick="x()" style="a"/></svg>');

        $this->assertCount(3, $errors);
    }
}
