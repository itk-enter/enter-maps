<?php

declare(strict_types=1);

namespace App\Controller;

use App\Security\AccessDeniedPage;
use App\Security\OidcException;
use App\Security\OidcLogin;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The login with the OpenID Connect provider. See OidcLogin.
 */
final class LoginController extends AbstractController
{
    public function __construct(
        private readonly OidcLogin $login,
        private readonly AccessDeniedPage $deniedPage,
    ) {
    }

    #[Route('/login', name: 'app_login', methods: [Request::METHOD_GET])]
    public function login(Request $request): RedirectResponse
    {
        $target = $request->query->get('target');

        return $this->redirect($this->login->start($request, \is_string($target) ? $target : null));
    }

    #[Route('/login/callback', name: 'app_login_callback', methods: [Request::METHOD_GET])]
    public function callback(Request $request): Response
    {
        try {
            return $this->redirect($this->login->finish($request));
        } catch (OidcException) {
            return $this->deniedPage->render('login_failed', Response::HTTP_BAD_REQUEST);
        }
    }

    /**
     * The firewall handles the logout, so this method does not run.
     */
    #[Route('/logout', name: 'app_logout', methods: [Request::METHOD_GET])]
    public function logout(): never
    {
        throw new \LogicException('The firewall handles the logout.');
    }

    #[Route('/logged-out', name: 'app_logged_out', methods: [Request::METHOD_GET])]
    public function loggedOut(): Response
    {
        return $this->render('security/logged_out.html.twig');
    }
}
