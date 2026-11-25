<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\AccessToken\AccessTokenExtractorInterface;

/**
 * Gives the access token authenticator the ID token from the callback of
 * the login, until LoginListener removes it. See OidcLogin.
 */
final readonly class SessionTokenExtractor implements AccessTokenExtractorInterface
{
    public function __construct(
        private OidcLogin $login,
    ) {
    }

    public function extractAccessToken(Request $request): ?string
    {
        return $this->login->token($request);
    }
}
