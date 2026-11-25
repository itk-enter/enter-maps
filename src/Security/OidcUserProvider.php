<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\AttributesBasedUserProviderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Gives the user of a login from the claims of the ID token. The first
 * login of a person makes a User, and a later login finds the same User
 * from the issuer and the "sub" claim. Each login updates the name, the
 * e-mail and the roles.
 *
 * A user in the group of employees gets ROLE_EMPLOYEE, which the builder
 * needs. The groups come from the claim in OIDC_GROUPS_CLAIM, for example
 * "groups".
 *
 * @implements AttributesBasedUserProviderInterface<User>
 */
final readonly class OidcUserProvider implements AttributesBasedUserProviderInterface
{
    public const string ROLE_EMPLOYEE = 'ROLE_EMPLOYEE';

    public function __construct(
        private UserRepository $repository,
        #[Autowire(env: 'OIDC_ISSUER')]
        private string $issuer,
        #[Autowire(env: 'OIDC_GROUPS_CLAIM')]
        private string $groupsClaim,
        #[Autowire(env: 'OIDC_EMPLOYEE_GROUP')]
        private string $employeeGroup,
    ) {
    }

    /**
     * @param string               $identifier the "sub" claim of the ID token
     * @param array<string, mixed> $attributes the claims of the ID token
     */
    public function loadUserByIdentifier(string $identifier, array $attributes = []): User
    {
        $issuer = \is_string($attributes['iss'] ?? null) ? $attributes['iss'] : $this->issuer;
        $user = $this->repository->findOneByLogin($issuer, $identifier) ?? new User($issuer, $identifier);

        $groups = $attributes[$this->groupsClaim] ?? [];
        $employee = \is_array($groups) && \in_array($this->employeeGroup, $groups, true);
        $user->logIn(
            name: \is_string($attributes['name'] ?? null) ? $attributes['name'] : null,
            email: \is_string($attributes['email'] ?? null) ? $attributes['email'] : null,
            roles: $employee ? [self::ROLE_EMPLOYEE] : [],
            at: new \DateTimeImmutable(),
        );
        $this->repository->save($user);

        return $user;
    }

    /**
     * Loads the user of the session again, so that a change in the database shows at once.
     */
    public function refreshUser(UserInterface $user): User
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(\sprintf('The class "%s" is not supported.', $user::class));
        }

        $fresh = $this->repository->find($user->getId());
        if (!$fresh instanceof User) {
            throw new UserNotFoundException(\sprintf('The user "%s" no longer exists.', $user->getUserIdentifier()));
        }

        return $fresh;
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }
}
