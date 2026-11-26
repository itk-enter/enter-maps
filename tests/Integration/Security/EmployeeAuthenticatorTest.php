<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\Entity\User;
use App\Security\EmployeeAuthenticator;
use App\Security\OidcUserFactory;
use App\Tests\Support\Database;
use Doctrine\ORM\EntityManagerInterface;
use ItkDev\OpenIdConnect\Exception\ValidationException;
use ItkDev\OpenIdConnectBundle\Security\OpenIdConfigurationProviderManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;

/**
 * The part of the login that the app adds to itk-dev/openid-connect-bundle.
 * The bundle validates the ID token in validateClaims(), so the tests give the
 * claims directly. The browser tests run the whole flow with the mock provider.
 */
final class EmployeeAuthenticatorTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        Database::reset(self::getContainer()->get(EntityManagerInterface::class));
    }

    public function testMakesTheUserFromTheClaims(): void
    {
        $passport = $this->authenticator(['iss' => 'https://idp.example.com', 'sub' => 'employee', 'name' => 'Medarbejder', 'groups' => ['Employee']])->authenticate(new Request());

        $user = $passport->getUser();
        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('Medarbejder', $user->getName());
        $this->assertContains(OidcUserFactory::ROLE_EMPLOYEE, $user->getRoles());
        $this->assertSame((string) $user->getId(), $passport->getBadge(\Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge::class)?->getUserIdentifier());
    }

    public function testChainsTheCauseOfAFailure(): void
    {
        foreach ([new ValidationException('The state does not match.'), new \InvalidArgumentException('The ID token has no "iss" or no "sub" claim.')] as $cause) {
            try {
                $this->authenticator($cause)->authenticate(new Request());
                $this->fail('No exception for '.$cause::class);
            } catch (CustomUserMessageAuthenticationException $exception) {
                // The bundle reads the cause to choose what the user sees.
                $this->assertSame($cause, $exception->getPrevious());
                $this->assertSame($cause->getMessage(), $exception->getMessageKey());
            }
        }
    }

    public function testSendsAUserWithoutALoginToTheProvider(): void
    {
        $this->assertSame('/openidconnect/login/employee', $this->authenticator([])->start(new Request())->getTargetUrl());
    }

    public function testGoesBackToThePageAfterTheLogin(): void
    {
        $authenticator = $this->authenticator([]);
        $token = new UsernamePasswordToken(new User('https://idp.example.com', 'employee'), 'main');
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->assertSame('/builder', $authenticator->onAuthenticationSuccess($request, $token, 'main')->getTargetUrl());

        $request->getSession()->set('_security.main.target_path', 'http://localhost/builder/new');
        $this->assertSame('http://localhost/builder/new', $authenticator->onAuthenticationSuccess($request, $token, 'main')->getTargetUrl());
    }

    /**
     * An authenticator that gives the claims, or throws the exception, in place of the validation of the bundle.
     *
     * @param array<string, mixed>|\Throwable $claims
     */
    private function authenticator(array|\Throwable $claims): EmployeeAuthenticator
    {
        $container = self::getContainer();

        return new class($container->get(OpenIdConfigurationProviderManager::class), $container->get(OidcUserFactory::class), $container->get(UrlGeneratorInterface::class), $claims) extends EmployeeAuthenticator {
            /**
             * @param array<string, mixed>|\Throwable $claims
             */
            public function __construct(OpenIdConfigurationProviderManager $providerManager, OidcUserFactory $users, UrlGeneratorInterface $urlGenerator, private readonly array|\Throwable $claims)
            {
                parent::__construct($providerManager, $users, $urlGenerator);
            }

            protected function validateClaims(Request $request): array
            {
                if ($this->claims instanceof \Throwable) {
                    throw $this->claims;
                }

                return $this->claims;
            }
        };
    }
}
