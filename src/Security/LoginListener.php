<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Removes the ID token from the session after a valid login. The session
 * then keeps the user, so the authenticator does not check the token again.
 */
#[AsEventListener]
final readonly class LoginListener
{
    public function __construct(
        private OidcLogin $login,
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $this->login->forgetToken($event->getRequest());
    }
}
