<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ITKDev\EntityBundle\Entity\AbstractITKDevEntity;
use ITKDev\EntityBundle\Entity\Contract\TimestampableInterface;
use ITKDev\EntityBundle\Entity\Trait\TimestampableTrait;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A user of the builder. The first login with OpenID Connect makes the user,
 * and each later login finds the same user from the issuer and the "sub"
 * claim. OpenID Connect guarantees that "sub" stays the same for a person at
 * one provider, and the issuer keeps the users of two providers apart.
 *
 * The name, the e-mail and the roles come from the ID token, and each login
 * updates them. The ULID is the identifier of the user in the session.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'app_user_issuer_subject', columns: ['issuer', 'subject'])]
class User extends AbstractITKDevEntity implements UserInterface, TimestampableInterface
{
    use TimestampableTrait;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $roles = [];

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $lastLoginAt;

    public function __construct(
        #[ORM\Column(length: 255)]
        private string $issuer,
        #[ORM\Column(length: 255)]
        private string $subject,
    ) {
        parent::__construct();
        $this->lastLoginAt = new \DateTimeImmutable();
    }

    /**
     * Updates the user from the claims of a new login.
     *
     * @param list<string> $roles
     */
    public function logIn(?string $name, ?string $email, array $roles, \DateTimeImmutable $at): void
    {
        $this->name = $name;
        $this->email = $email;
        $this->roles = $roles;
        $this->lastLoginAt = $at;
    }

    public function getIssuer(): string
    {
        return $this->issuer;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function getLastLoginAt(): \DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->getId();
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return array_values(array_unique([...$this->roles, 'ROLE_USER']));
    }
}
