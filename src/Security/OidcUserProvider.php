<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\AttributesBasedUserProviderInterface;
use Symfony\Component\Security\Core\User\OidcUser;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Makes the user from the claims of the ID token. A user in the group of
 * employees gets ROLE_EMPLOYEE, which the builder needs. The groups come
 * from the claim in OIDC_GROUPS_CLAIM, for example "groups".
 *
 * The project has no user entity yet, so the user lives only in the session.
 *
 * @implements AttributesBasedUserProviderInterface<OidcUser>
 */
final readonly class OidcUserProvider implements AttributesBasedUserProviderInterface
{
    public const string ROLE_EMPLOYEE = 'ROLE_EMPLOYEE';

    public function __construct(
        #[Autowire(env: 'OIDC_GROUPS_CLAIM')]
        private string $groupsClaim,
        #[Autowire(env: 'OIDC_EMPLOYEE_GROUP')]
        private string $employeeGroup,
    ) {
    }

    /**
     * @param array<string, mixed> $attributes the claims of the ID token
     */
    public function loadUserByIdentifier(string $identifier, array $attributes = []): OidcUser
    {
        $groups = $attributes[$this->groupsClaim] ?? [];
        $roles = ['ROLE_USER'];
        if (\is_array($groups) && \in_array($this->employeeGroup, $groups, true)) {
            $roles[] = self::ROLE_EMPLOYEE;
        }

        return new OidcUser(
            userIdentifier: $identifier,
            roles: $roles,
            sub: $identifier,
            name: \is_string($attributes['name'] ?? null) ? $attributes['name'] : null,
            email: \is_string($attributes['email'] ?? null) ? $attributes['email'] : null,
        );
    }

    /**
     * The session keeps the whole user, so a refresh gives the same user.
     */
    public function refreshUser(UserInterface $user): OidcUser
    {
        if (!$user instanceof OidcUser) {
            throw new UnsupportedUserException(\sprintf('The class "%s" is not supported.', $user::class));
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return OidcUser::class === $class || is_subclass_of($class, OidcUser::class);
    }
}
