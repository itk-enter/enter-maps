<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use ItkDev\OpenIdConnect\Exception\OpenIdConnectExceptionInterface;
use ItkDev\OpenIdConnectBundle\Exception\OpenIdConnectBundleExceptionInterface;
use ItkDev\OpenIdConnectBundle\Security\OpenIdConfigurationProviderManager;
use ItkDev\OpenIdConnectBundle\Security\OpenIdLoginAuthenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

/**
 * The login of the employees with OpenID Connect, on itk-dev/openid-connect-bundle.
 * The bundle runs the authorization code flow with PKCE, and validates the
 * ID token. This class makes the User from the claims (see OidcUserFactory),
 * and sends a user without a login to the provider.
 *
 * The class keeps no data of a request in a property, because one instance
 * serves all requests.
 */
class EmployeeAuthenticator extends OpenIdLoginAuthenticator
{
    /**
     * The key of the provider in config/packages/itkdev_openid_connect.yaml.
     */
    public const string PROVIDER = 'employee';

    public function __construct(
        OpenIdConfigurationProviderManager $providerManager,
        private readonly OidcUserFactory $users,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
        parent::__construct($providerManager);
    }

    public function authenticate(Request $request): Passport
    {
        try {
            $user = $this->users->fromClaims($this->validateClaims($request));
        } catch (OpenIdConnectExceptionInterface|OpenIdConnectBundleExceptionInterface|\InvalidArgumentException $exception) {
            // The bundle reads the cause, so it must be chained.
            throw new CustomUserMessageAuthenticationException($exception->getMessage(), code: (int) $exception->getCode(), previous: $exception);
        }

        return new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), static fn (): User => $user));
    }

    /**
     * Back to the page that needed the login, or else the list of maps.
     */
    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): RedirectResponse
    {
        return $this->createTargetPathRedirect($request, $firewallName, $this->urlGenerator->generate('app_builder'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): RedirectResponse
    {
        return new RedirectResponse($this->urlGenerator->generate('itkdev_openid_connect_login', ['providerKey' => self::PROVIDER]));
    }
}
