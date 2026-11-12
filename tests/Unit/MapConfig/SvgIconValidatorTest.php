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
        yield 'style, class and gradient' => [self::OPEN.'<style>.a{fill:red}</style><defs><linearGradient id="g"><stop offset="0" stop-color="red"/></linearGradient></defs><path class="a" d="M0 0" fill="url(#g)" style="stroke:blue"/></svg>'];
        yield 'reference in the SVG' => [self::OPEN.'<defs><path id="a" d="M0 0h1v1z"/></defs><use href="#a"/></svg>'];
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
        yield 'script' => [self::OPEN.'<script>alert(1)</script></svg>', "tag 'script'"];
        yield 'event attribute' => [self::OPEN.'<path d="M0 0" onload="alert(1)"/></svg>', "attribute 'onload'"];
        yield 'foreign object' => [self::OPEN.'<foreignObject><div xmlns="http://www.w3.org/1999/xhtml">x</div></foreignObject></svg>', "tag 'foreignObject'"];
        yield 'image from another server' => [self::OPEN.'<image href="https://example.com/a.png"/></svg>', "attribute 'href'"];
        yield 'script link' => [self::OPEN.'<a href="javascript:alert(1)"><path d="M0 0"/></a></svg>', "attribute 'href'"];
        yield 'fill from another server' => [self::OPEN.'<path d="M0 0" fill="url(https://example.com/#a)"/></svg>', "attribute 'fill'"];
        yield 'style from another server' => [self::OPEN.'<style>path{fill:url(https://example.com/#a)}</style></svg>', "from 'style'"];
        yield 'element of another namespace' => [self::OPEN.'<x:path xmlns:x="https://example.com" d="M0 0"/></svg>', "tag 'x:path'"];
        yield 'PHP' => [self::OPEN.'<?php echo 1; ?><path d="M0 0"/></svg>', 'processing instruction'];
        yield 'stylesheet instruction' => ['<?xml-stylesheet href="https://example.com/a.css"?>'.self::OPEN.'<path d="M0 0"/></svg>', 'processing instruction'];
        yield 'document type' => ['<!DOCTYPE svg [<!ENTITY a SYSTEM "file:///etc/passwd">]>'.self::OPEN.'<path d="M0 0"/></svg>', 'document type'];
        yield 'not XML' => ['<svg viewBox="0 0 24 24"><path></svg>', 'tag mismatch'];
        yield 'not SVG' => ['<html xmlns="http://www.w3.org/1999/xhtml"/>', 'not in the SVG namespace'];
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
        $errors = new SvgIconValidator()->validate(self::OPEN.'<script/><path d="M0 0" onclick="x()" fill="url(https://example.com/#a)"/></svg>');

        $this->assertCount(3, $errors);
    }

    public function testGivesTheLineOfTheProblem(): void
    {
        $errors = new SvgIconValidator()->validate(self::OPEN."\n<path d=\"M0 0\"/>\n<script/></svg>");

        $this->assertSame(["Line 3: Suspicious tag 'script'."], $errors);
    }
}
