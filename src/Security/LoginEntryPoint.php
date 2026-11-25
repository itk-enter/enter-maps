<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Sends a user who is not logged in to the login. After the login, the user
 * comes back to the page, if it was a page. A form action goes to the list
 * of maps instead, because the browser cannot send it again.
 */
final readonly class LoginEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): RedirectResponse
    {
        $page = $request->isMethod(Request::METHOD_GET) && !str_starts_with($request->getPathInfo(), '/builder/_components');

        return new RedirectResponse($this->urlGenerator->generate('app_login', $page ? ['target' => $request->getRequestUri()] : []));
    }
}
