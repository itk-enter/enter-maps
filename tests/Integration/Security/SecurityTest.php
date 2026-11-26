<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\Controller\LoginController;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\LoginFailureListener;
use App\Tests\Support\Database;
use App\Tests\Support\OidcTestLogin;
use Doctrine\ORM\EntityManagerInterface;
use ItkDev\OpenIdConnectBundle\Exception\ProviderErrorException;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * The paths that need a login, and the pages of the login. The provider does
 * not answer in the tests (OIDC_METADATA_URL in .env.test).
 */
final class SecurityTest extends WebTestCase
{
    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        Database::reset(self::getContainer()->get(EntityManagerInterface::class));
    }

    public function testSendsAUserWithoutALoginToTheProvider(): void
    {
        foreach (['/', '/builder', '/builder/new'] as $path) {
            $this->browser->request('GET', $path);

            $this->assertResponseRedirects('/openidconnect/login/employee', message: $path);
        }
    }

    public function testLetsTheMapAndItsDataThroughWithoutALogin(): void
    {
        $this->browser->request('GET', '/embed/leaflet/01ARZ3NDEKTSV4RRFFQ69G5FAV');

        $this->assertResponseStatusCodeSame(404);
        $this->assertSelectorTextSame('h1', 'Kortet er ikke tilgængeligt');
        // The embed page is stateless, so the firewall must not start a session.
        $this->assertResponseNotHasHeader('Set-Cookie');
    }

    public function testDoesNotLetAUserInWhoIsNotInTheGroup(): void
    {
        $user = new User('https://idp.example.com', 'colleague');
        $user->logIn('Kollega', null, [], new \DateTimeImmutable());
        self::getContainer()->get(UserRepository::class)->save($user);
        $this->browser->loginUser($user, 'main');

        $this->browser->request('GET', '/builder');

        $this->assertResponseStatusCodeSame(403);
        $this->assertSelectorTextSame('h1', 'Ingen adgang');
        $this->assertStringContainsString('Kollega', $this->browser->getCrawler()->filter('header')->text());
    }

    public function testShowsAPageWhenTheLoginFails(): void
    {
        // A callback without the state of a login in the session.
        $this->browser->request('GET', '/openidconnect/callback', ['state' => 'other', 'code' => 'code-1']);

        $this->assertResponseStatusCodeSame(401);
        $this->assertSelectorTextSame('h1', 'Login mislykkedes');
    }

    public function testKeepsTheStatusOfARefusalByTheProvider(): void
    {
        $event = new ExceptionEvent(self::$kernel, new Request(), HttpKernelInterface::MAIN_REQUEST, new ProviderErrorException(ProviderErrorException::ACCESS_DENIED));

        self::getContainer()->get(LoginFailureListener::class)($event);

        $this->assertSame(403, $event->getResponse()?->getStatusCode());

        $other = new ExceptionEvent(self::$kernel, new Request(), HttpKernelInterface::MAIN_REQUEST, new \RuntimeException('Something else'));
        self::getContainer()->get(LoginFailureListener::class)($other);
        $this->assertNull($other->getResponse());
    }

    public function testLogsOutAndGoesToThePageOfTheLogoutIfTheProviderDoesNotAnswer(): void
    {
        OidcTestLogin::asEmployee($this->browser);
        $this->browser->request('GET', '/builder');
        $this->assertResponseIsSuccessful();

        $this->browser->request('GET', '/logout');

        $this->assertResponseRedirects('http://localhost/logged-out');
        $this->browser->followRedirect();
        $this->assertSelectorTextSame('h1', 'Du er logget ud');
        $this->assertSame('/openidconnect/login/employee', $this->browser->getCrawler()->selectLink('Log ind')->attr('href'));
        $this->browser->request('GET', '/builder');
        $this->assertResponseRedirects('/openidconnect/login/employee');
    }

    public function testLeavesTheCallbackAndTheLogoutToTheFirewall(): void
    {
        $controller = self::getContainer()->get(LoginController::class);

        foreach (['callback', 'logout'] as $method) {
            try {
                $controller->{$method}();
                $this->fail($method);
            } catch (\LogicException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
