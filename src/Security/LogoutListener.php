<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Logs the user out at the provider too. Else the provider would log the
 * user in again at once on the next visit. The provider sends the user back
 * to the page that tells that the user is logged out.
 */
#[AsEventListener]
final readonly class LogoutListener
{
    public function __construct(
        private OidcClient $client,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(LogoutEvent $event): void
    {
        $loggedOut = $this->urlGenerator->generate('app_logged_out', [], UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            $url = $this->client->endSessionUrl($loggedOut) ?? $loggedOut;
        } catch (OidcException) {
            $url = $loggedOut;
        }

        $event->setResponse(new RedirectResponse($url));
    }
}
