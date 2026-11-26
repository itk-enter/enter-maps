<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Gives the User of a login from the claims of the ID token. The first login
 * of a person makes the User, and a later login finds the same User from the
 * issuer and the "sub" claim. Each login updates the name, the e-mail and
 * the roles.
 *
 * A user in the group of employees gets ROLE_EMPLOYEE, which the builder
 * needs. The groups come from the claim in OIDC_GROUPS_CLAIM, for example
 * "groups".
 */
final readonly class OidcUserFactory
{
    public const string ROLE_EMPLOYEE = 'ROLE_EMPLOYEE';

    public function __construct(
        private UserRepository $repository,
        #[Autowire(env: 'OIDC_GROUPS_CLAIM')]
        private string $groupsClaim,
        #[Autowire(env: 'OIDC_EMPLOYEE_GROUP')]
        private string $employeeGroup,
    ) {
    }

    /**
     * @param array<string, mixed> $claims the claims of the ID token, which the bundle validated
     *
     * @throws \InvalidArgumentException if the claims have no "iss" or no "sub"
     */
    public function fromClaims(array $claims): User
    {
        $issuer = $claims['iss'] ?? null;
        $subject = $claims['sub'] ?? null;
        if (!\is_string($issuer) || '' === $issuer || !\is_string($subject) || '' === $subject) {
            throw new \InvalidArgumentException('The ID token has no "iss" or no "sub" claim.');
        }

        $user = $this->repository->findOneByLogin($issuer, $subject) ?? new User($issuer, $subject);
        $groups = $claims[$this->groupsClaim] ?? [];
        $employee = \is_array($groups) && \in_array($this->employeeGroup, $groups, true);
        $user->logIn(
            name: \is_string($claims['name'] ?? null) ? $claims['name'] : null,
            email: \is_string($claims['email'] ?? null) ? $claims['email'] : null,
            roles: $employee ? [self::ROLE_EMPLOYEE] : [],
            at: new \DateTimeImmutable(),
        );
        $this->repository->save($user);

        return $user;
    }
}
