<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The routes of the login that are not in itk-dev/openid-connect-bundle.
 * The firewall handles the callback and the logout, so their methods do not run.
 */
final class LoginController extends AbstractController
{
    /**
     * The provider sends the user back here. EmployeeAuthenticator handles the request.
     */
    #[Route('/openidconnect/callback', name: 'app_login_callback', methods: [Request::METHOD_GET])]
    public function callback(): never
    {
        throw new \LogicException('EmployeeAuthenticator handles the callback of the login.');
    }

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
