<?php

declare(strict_types=1);

namespace App\Security;

use ItkDev\OpenIdConnect\Exception\OpenIdConnectExceptionInterface;
use ItkDev\OpenIdConnectBundle\Exception\OpenIdConnectBundleExceptionInterface;
use ItkDev\OpenIdConnectBundle\Security\OpenIdConfigurationProviderManager;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Logs the user out at the provider too. Else the provider would log the
 * user in again at once on the next visit. The provider sends the user back
 * to the page that tells that the user is logged out. If the provider does
 * not answer, the user goes to that page at once.
 */
#[AsEventListener]
final readonly class LogoutListener
{
    public function __construct(
        private OpenIdConfigurationProviderManager $providerManager,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(LogoutEvent $event): void
    {
        $loggedOut = $this->urlGenerator->generate('app_logged_out', [], UrlGeneratorInterface::ABSOLUTE_URL);

        try {
            $url = $this->providerManager->getProvider(EmployeeAuthenticator::PROVIDER)->getEndSessionUrl($loggedOut);
        } catch (OpenIdConnectExceptionInterface|OpenIdConnectBundleExceptionInterface) {
            $url = $loggedOut;
        }

        $event->setResponse(new RedirectResponse($url));
    }
}
