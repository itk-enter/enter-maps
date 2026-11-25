<?php

declare(strict_types=1);

namespace App\Tests\Unit\Security;

use App\Security\OidcClient;
use App\Security\OidcException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class OidcClientTest extends TestCase
{
    private const string ISSUER = 'https://idp.example.com';

    private const array DISCOVERY = [
        'authorization_endpoint' => 'https://idp.example.com/authorize',
        'token_endpoint' => 'https://idp.example.com/token',
        'end_session_endpoint' => 'https://idp.example.com/logout',
    ];

    public function testGivesTheLoginPageWithPkce(): void
    {
        $url = $this->client([new JsonMockResponse(self::DISCOVERY)])->authorizationUrl('https://maps.example.com/login/callback', 'state-1', 'dBjftJeZ4CVP-mJ92K9rjCN7lTX3yp4uNixpkLHG6a4');

        $this->assertStringStartsWith('https://idp.example.com/authorize?', $url);
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        $this->assertSame([
            'response_type' => 'code',
            'client_id' => 'enter-maps',
            'redirect_uri' => 'https://maps.example.com/login/callback',
            'scope' => 'openid profile email',
            'state' => 'state-1',
            // SHA-256 of the verifier in base64url without padding, as RFC 7636 tells.
            'code_challenge' => 'oG_2amD-8NqelY6c1aUVMMcTOn956KH00X1-Mw0A9qA',
            'code_challenge_method' => 'S256',
        ], $query);
    }

    public function testChangesTheCodeIntoAnIdToken(): void
    {
        $token = new JsonMockResponse(['id_token' => 'header.payload.signature', 'access_token' => 'x']);
        $client = $this->client([new JsonMockResponse(self::DISCOVERY), $token]);

        $this->assertSame('header.payload.signature', $client->idToken('code-1', 'https://maps.example.com/login/callback', 'verifier-1'));

        $this->assertSame('POST', $token->getRequestMethod());
        $this->assertSame('https://idp.example.com/token', $token->getRequestUrl());
        $this->assertContains('Authorization: Basic '.base64_encode('enter-maps:secret'), $token->getRequestOptions()['headers']);
        parse_str($token->getRequestOptions()['body'], $body);
        $this->assertSame(['grant_type' => 'authorization_code', 'code' => 'code-1', 'redirect_uri' => 'https://maps.example.com/login/callback', 'code_verifier' => 'verifier-1'], $body);
    }

    public function testFailsWithoutAnIdToken(): void
    {
        $client = $this->client([new JsonMockResponse(self::DISCOVERY), new JsonMockResponse(['access_token' => 'x'])]);

        $this->expectException(OidcException::class);
        $this->expectExceptionMessageIsOrContains('The answer of the provider has no ID token.');
        $client->idToken('code-1', 'https://maps.example.com/login/callback', 'verifier-1');
    }

    public function testFailsIfTheProviderRejectsTheCode(): void
    {
        $client = $this->client([new JsonMockResponse(self::DISCOVERY), new JsonMockResponse(['error' => 'invalid_grant'], ['http_code' => 400])]);

        $this->expectException(OidcException::class);
        $this->expectExceptionMessageIsOrContains('The provider did not change the code into a token');
        $client->idToken('code-1', 'https://maps.example.com/login/callback', 'verifier-1');
    }

    public function testGivesTheLogoutOfTheProviderIfItHasOne(): void
    {
        $this->assertSame(
            'https://idp.example.com/logout?client_id=enter-maps&post_logout_redirect_uri=https%3A%2F%2Fmaps.example.com%2Flogged-out',
            $this->client([new JsonMockResponse(self::DISCOVERY)])->endSessionUrl('https://maps.example.com/logged-out'),
        );
        $this->assertNull($this->client([new JsonMockResponse(['authorization_endpoint' => 'x'])])->endSessionUrl('https://maps.example.com/logged-out'));
    }

    public function testReadsTheDiscoveryDocumentOnceAndCachesIt(): void
    {
        $discovery = new JsonMockResponse(self::DISCOVERY);
        $client = $this->client([$discovery]);

        $client->authorizationUrl('https://maps.example.com/login/callback', 's', 'v');
        $client->authorizationUrl('https://maps.example.com/login/callback', 's', 'v');

        $this->assertSame('https://idp.example.com/.well-known/openid-configuration', $discovery->getRequestUrl());
    }

    public function testFailsIfTheDiscoveryDocumentHasNoEndpoint(): void
    {
        $this->expectException(OidcException::class);
        $this->expectExceptionMessageIsOrContains('The discovery document of the provider has no "authorization_endpoint".');
        $this->client([new JsonMockResponse(['token_endpoint' => 'x'])])->authorizationUrl('https://maps.example.com/login/callback', 's', 'v');
    }

    public function testFailsIfTheProviderDoesNotAnswer(): void
    {
        $this->expectException(OidcException::class);
        $this->expectExceptionMessageIsOrContains('The provider did not give its discovery document');
        $this->client([new MockResponse('', ['http_code' => 503])])->authorizationUrl('https://maps.example.com/login/callback', 's', 'v');
    }

    /**
     * @param list<MockResponse> $responses
     */
    private function client(array $responses): OidcClient
    {
        return new OidcClient(new MockHttpClient($responses), new ArrayAdapter(), self::ISSUER, 'enter-maps', 'secret');
    }
}
