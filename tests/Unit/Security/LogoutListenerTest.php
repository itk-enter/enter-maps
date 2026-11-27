<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\LogoutListener;
use ItkDev\OpenIdConnect\Security\OpenIdConfigurationProvider;
use ItkDev\OpenIdConnectBundle\Exception\InvalidProviderException;
use ItkDev\OpenIdConnectBundle\Security\OpenIdConfigurationProviderManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class LogoutListenerTest extends TestCase
{
    public function testLogsOutAtTheProvider(): void
    {
        $provider = $this->createMock(OpenIdConfigurationProvider::class);
        $provider->expects($this->once())->method('getEndSessionUrl')->with('https://maps.example.com/logged-out')
            ->willReturn('https://idp.example.com/logout?post_logout_redirect_uri=x');
        $manager = $this->createStub(OpenIdConfigurationProviderManager::class);
        $manager->method('getProvider')->willReturn($provider);

        $this->assertSame('https://idp.example.com/logout?post_logout_redirect_uri=x', $this->logout($manager));
    }

    public function testGoesToThePageOfTheLogoutIfTheProviderIsNotThere(): void
    {
        $manager = $this->createStub(OpenIdConfigurationProviderManager::class);
        $manager->method('getProvider')->willThrowException(new InvalidProviderException('No provider'));

        $this->assertSame('https://maps.example.com/logged-out', $this->logout($manager));
    }

    private function logout(OpenIdConfigurationProviderManager $manager): string
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://maps.example.com/logged-out');
        $event = new LogoutEvent(new Request(), null);

        new LogoutListener($manager, $urlGenerator)($event);

        $response = $event->getResponse();
        $this->assertInstanceOf(RedirectResponse::class, $response);

        return $response->getTargetUrl();
    }
}
