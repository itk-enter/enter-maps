<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authorization\AccessDeniedHandlerInterface;
use Twig\Environment;

/**
 * The page for a login that failed, and for a user who is not an employee.
 * It does not send the user to the login again, because the provider would
 * log the same user in again, and the browser would go round in a loop.
 */
final readonly class AccessDeniedPage implements AuthenticationFailureHandlerInterface, AccessDeniedHandlerInterface
{
    public function __construct(
        private Environment $twig,
        private OidcLogin $login,
    ) {
    }

    /**
     * The ID token was not valid, for example because it expired or has another audience.
     */
    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $this->login->forgetToken($request);

        return $this->render('login_failed', Response::HTTP_UNAUTHORIZED);
    }

    /**
     * The user is logged in, but is not in the group of employees.
     */
    public function handle(Request $request, AccessDeniedException $accessDeniedException): Response
    {
        return $this->render('not_employee', Response::HTTP_FORBIDDEN);
    }

    /**
     * @param string $reason the key of the texts in builder.security
     */
    public function render(string $reason, int $status): Response
    {
        return new Response($this->twig->render('security/denied.html.twig', ['reason' => $reason]), $status);
    }
}
