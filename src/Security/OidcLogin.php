<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Keeps the state of a login with the OpenID Connect provider in the session.
 *
 * start() sends the user to the provider. finish() checks the answer, and
 * keeps the ID token in the session. On the next request, token() gives the
 * token to the access token authenticator of Symfony, which checks it. After
 * the check, forgetToken() removes it, and the session keeps the user.
 */
final readonly class OidcLogin
{
    private const string STATE = 'oidc_state';

    private const string CODE_VERIFIER = 'oidc_code_verifier';

    private const string TARGET = 'oidc_target';

    private const string ID_TOKEN = 'oidc_id_token';

    public function __construct(
        private OidcClient $client,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    /**
     * @param string|null $target the path to go to after the login. Only a path on this site is used.
     *
     * @return string the address of the login page of the provider
     */
    public function start(Request $request, ?string $target): string
    {
        $session = $request->getSession();
        $state = bin2hex(random_bytes(16));
        $codeVerifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $session->set(self::STATE, $state);
        $session->set(self::CODE_VERIFIER, $codeVerifier);
        $session->set(self::TARGET, $this->isLocalPath($target) ? $target : null);

        return $this->client->authorizationUrl($this->redirectUri(), $state, $codeVerifier);
    }

    /**
     * Checks the answer of the provider, and keeps the ID token for the
     * authenticator.
     *
     * @return string the path to go to after the login
     *
     * @throws OidcException if the answer is not from the login that start() began, or has no code
     */
    public function finish(Request $request): string
    {
        $session = $request->getSession();
        $state = $session->remove(self::STATE);
        $codeVerifier = $session->remove(self::CODE_VERIFIER);
        $target = $session->remove(self::TARGET);

        if (!\is_string($state) || !\is_string($codeVerifier) || !hash_equals($state, $request->query->getString('state'))) {
            throw new OidcException('The answer of the provider is not from this login.');
        }
        $code = $request->query->getString('code');
        if ('' === $code) {
            throw new OidcException('The provider gave no code: '.$request->query->getString('error', 'no error'));
        }

        $session->set(self::ID_TOKEN, $this->client->idToken($code, $this->redirectUri(), $codeVerifier));

        return \is_string($target) ? $target : $this->urlGenerator->generate('app_builder');
    }

    /**
     * The ID token from finish(), until the authenticator has checked it.
     * The authenticator reads it more than once, so this does not remove it.
     */
    public function token(Request $request): ?string
    {
        if (!$request->hasPreviousSession()) {
            return null;
        }
        $token = $request->getSession()->get(self::ID_TOKEN);

        return \is_string($token) ? $token : null;
    }

    /**
     * Removes the ID token after the check. Then the session keeps the user.
     */
    public function forgetToken(Request $request): void
    {
        if ($request->hasSession()) {
            $request->getSession()->remove(self::ID_TOKEN);
        }
    }

    private function redirectUri(): string
    {
        return $this->urlGenerator->generate('app_login_callback', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    /**
     * A path such as "/builder", but not "//other.example.com", which a browser reads as another site.
     *
     * @phpstan-assert-if-true string $target
     */
    private function isLocalPath(?string $target): bool
    {
        return null !== $target && str_starts_with($target, '/') && !str_starts_with($target, '//') && !str_contains($target, '\\');
    }
}
