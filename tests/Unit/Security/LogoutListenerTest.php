<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\LogoutListener;
use App\Security\OidcClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class LogoutListenerTest extends TestCase
{
    public function testLogsOutAtTheProvider(): void
    {
        $event = $this->logout(new JsonMockResponse(['end_session_endpoint' => 'https://idp.example.com/logout']));

        $this->assertSame('https://idp.example.com/logout?client_id=enter-maps&post_logout_redirect_uri=https%3A%2F%2Fmaps.example.com%2Flogged-out', $this->target($event));
    }

    public function testGoesToThePageOfTheLogoutIfTheProviderHasNoLogoutOrDoesNotAnswer(): void
    {
        $this->assertSame('https://maps.example.com/logged-out', $this->target($this->logout(new JsonMockResponse(['authorization_endpoint' => 'x']))));
        $this->assertSame('https://maps.example.com/logged-out', $this->target($this->logout(new MockResponse('', ['http_code' => 503]))));
    }

    private function logout(MockResponse $discovery): LogoutEvent
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('https://maps.example.com/logged-out');
        $client = new OidcClient(new MockHttpClient([$discovery]), new ArrayAdapter(), 'https://idp.example.com', 'enter-maps', 'secret');
        $event = new LogoutEvent(new Request(), null);

        new LogoutListener($client, $urlGenerator)($event);

        return $event;
    }

    private function target(LogoutEvent $event): string
    {
        $response = $event->getResponse();
        $this->assertInstanceOf(RedirectResponse::class, $response);

        return $response->getTargetUrl();
    }
}
