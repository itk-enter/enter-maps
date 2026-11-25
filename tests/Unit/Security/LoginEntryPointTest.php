<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\LoginEntryPoint;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class LoginEntryPointTest extends TestCase
{
    public function testComesBackToAPageButNotToAFormAction(): void
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(static fn (string $route, array $parameters): string => '/login'.([] === $parameters ? '' : '?'.http_build_query($parameters)));
        $entryPoint = new LoginEntryPoint($urlGenerator);

        $this->assertSame('/login?target=%2Fbuilder%2Fnew', $entryPoint->start(Request::create('/builder/new'))->getTargetUrl());
        $this->assertSame('/login', $entryPoint->start(Request::create('/builder/01ARZ3NDEKTSV4RRFFQ69G5FAV/delete', 'POST'))->getTargetUrl());
        $this->assertSame('/login', $entryPoint->start(Request::create('/builder/_components/MapEditor'))->getTargetUrl());
    }
}
