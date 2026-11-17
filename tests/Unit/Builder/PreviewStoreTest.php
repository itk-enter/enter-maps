<?php

declare(strict_types=1);

namespace App\Tests\Unit\Builder;

use App\Builder\PreviewStore;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

final class PreviewStoreTest extends TestCase
{
    private PreviewStore $store;

    protected function setUp(): void
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = new RequestStack();
        $requestStack->push($request);
        $this->store = new PreviewStore($requestStack);
    }

    public function testKeepsTheSettingsUnderAToken(): void
    {
        $token = $this->store->add((object) ['title' => 'Udkast', 'values' => new \stdClass()]);

        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $token);
        // An empty object stays an object.
        $this->assertSame('{"title":"Udkast","values":{}}', json_encode($this->store->get($token)));
        $this->assertNull($this->store->get(str_repeat('0', 32)));
    }

    public function testKeepsTheLastPreviewsOnly(): void
    {
        $tokens = [];
        for ($number = 1; $number <= PreviewStore::MAX_PREVIEWS + 1; ++$number) {
            $tokens[] = $this->store->add((object) ['title' => 'Udkast '.$number]);
        }

        $this->assertNull($this->store->get($tokens[0]));
        $this->assertSame('Udkast 6', $this->store->get($tokens[5])?->title);
        $this->assertSame('Udkast 2', $this->store->get($tokens[1])?->title);
    }
}
