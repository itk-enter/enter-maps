<?php

declare(strict_types=1);

namespace App\Security;

use ItkDev\OpenIdConnectBundle\Exception\OpenIdConnectBundleExceptionInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

use function Symfony\Component\Translation\t;

/**
 * Shows the page "Login mislykkedes" when the login with the provider fails,
 * for example because the ID token is not valid or the user cancelled at the
 * provider. The bundle throws these errors outside the security component on
 * purpose, so that the browser does not go back to the provider in a loop.
 *
 * The status comes from the error if it has one, for example 403 when the
 * user cancelled. Else it is 401.
 */
#[AsEventListener(KernelEvents::EXCEPTION, priority: 1)]
final readonly class LoginFailureListener
{
    public function __construct(
        private AccessDeniedPage $page,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        if (!$exception instanceof OpenIdConnectBundleExceptionInterface) {
            return;
        }

        $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : Response::HTTP_UNAUTHORIZED;
        $event->setResponse($this->page->render(t('builder.security.login_failed.title'), t('builder.security.login_failed.text'), $status));
    }
}
