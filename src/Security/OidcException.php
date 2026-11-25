<?php

declare(strict_types=1);

namespace App\Security;

/**
 * The login with the OpenID Connect provider failed, for example because
 * the provider did not answer or the state did not match.
 */
final class OidcException extends \RuntimeException
{
}
