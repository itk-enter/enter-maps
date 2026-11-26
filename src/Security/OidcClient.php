<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Talks to the OpenID Connect provider for the login of the builder: the
 * authorization code flow with PKCE. The addresses of the provider come from
 * its discovery document, which the cache keeps for an hour.
 *
 * The flow:
 *
 * 1. authorizationUrl() gives the address of the login page of the provider.
 * 2. The provider sends the user back with a code.
 * 3. idToken() changes the code into an ID token. The access token
 *    authenticator of Symfony then checks the token.
 */
final readonly class OidcClient
{
    /**
     * The scopes of the login: "openid" for the ID token, and "profile" and
     * "email" for the name and the e-mail of the user in its claims.
     */
    private const string SCOPE = 'openid profile email';

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        #[Autowire(env: 'OIDC_ISSUER')]
        private string $issuer,
        #[Autowire(env: 'OIDC_CLIENT_ID')]
        private string $clientId,
        #[Autowire(env: 'OIDC_CLIENT_SECRET')]
        private string $clientSecret,
    ) {
    }

    /**
     * @param string $codeVerifier the secret of PKCE, which idToken() sends again
     */
    public function authorizationUrl(string $redirectUri, string $state, string $codeVerifier): string
    {
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $codeVerifier, true)), '+/', '-_'), '=');

        return $this->endpoint('authorization_endpoint').'?'.http_build_query([
            'response_type' => 'code',
            'client_id' => $this->clientId,
            'redirect_uri' => $redirectUri,
            'scope' => self::SCOPE,
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    /**
     * Changes the code from the provider into an ID token.
     *
     * @throws OidcException if the provider does not give an ID token
     */
    public function idToken(string $code, string $redirectUri, string $codeVerifier): string
    {
        try {
            $data = $this->httpClient->request('POST', $this->endpoint('token_endpoint'), [
                'auth_basic' => [$this->clientId, $this->clientSecret],
                'body' => [
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'redirect_uri' => $redirectUri,
                    'code_verifier' => $codeVerifier,
                ],
            ])->toArray();
        } catch (ExceptionInterface $exception) {
            throw new OidcException('The provider did not change the code into a token: '.$exception->getMessage(), $exception->getCode(), previous: $exception);
        }

        if (!\is_string($data['id_token'] ?? null)) {
            throw new OidcException('The answer of the provider has no ID token.');
        }

        return $data['id_token'];
    }

    /**
     * The address that logs the user out at the provider too, or null if the
     * provider does not give one.
     */
    public function endSessionUrl(string $redirectUri): ?string
    {
        $endpoint = $this->configuration()['end_session_endpoint'] ?? null;

        return \is_string($endpoint) ? $endpoint.'?'.http_build_query([
            'client_id' => $this->clientId,
            'post_logout_redirect_uri' => $redirectUri,
        ]) : null;
    }

    /**
     * @throws OidcException if the discovery document does not have the endpoint
     */
    private function endpoint(string $name): string
    {
        $endpoint = $this->configuration()[$name] ?? null;
        if (!\is_string($endpoint)) {
            throw new OidcException(\sprintf('The discovery document of the provider has no "%s".', $name));
        }

        return $endpoint;
    }

    /**
     * @return array<string, mixed> the discovery document of the provider
     *
     * @throws OidcException if the provider does not answer
     */
    private function configuration(): array
    {
        try {
            return $this->cache->get('oidc_configuration_'.hash('xxh3', $this->issuer), function (ItemInterface $item): array {
                $item->expiresAfter(3600);

                return $this->httpClient->request('GET', rtrim($this->issuer, '/').'/.well-known/openid-configuration')->toArray();
            });
        } catch (ExceptionInterface $exception) {
            throw new OidcException('The provider did not give its discovery document: '.$exception->getMessage(), $exception->getCode(), previous: $exception);
        }
    }
}
