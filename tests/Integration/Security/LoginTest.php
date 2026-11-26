<?php

declare(strict_types=1);

namespace App\Tests\Integration\Security;

use App\Controller\LoginController;
use App\Repository\UserRepository;
use App\Security\OidcClient;
use App\Tests\Support\Database;
use App\Tests\Support\OidcTestLogin;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * The login with OpenID Connect, with a fake provider. The ID tokens are
 * signed with the test key, so the access token authenticator checks them
 * as on a server.
 */
final class LoginTest extends WebTestCase
{
    private const string PROVIDER = 'https://idp.example.com';

    private KernelBrowser $browser;

    /**
     * The ID token that the fake provider gives for the next code.
     */
    private string $idToken = '';

    protected function setUp(): void
    {
        $this->browser = self::createClient();
        // The fake provider must stay in the container between the requests.
        $this->browser->disableReboot();
        Database::reset(self::getContainer()->get(EntityManagerInterface::class));
        $discovery = [
            'authorization_endpoint' => self::PROVIDER.'/authorize',
            'token_endpoint' => self::PROVIDER.'/token',
            'end_session_endpoint' => self::PROVIDER.'/logout',
        ];
        $httpClient = new MockHttpClient(fn (string $method, string $url): JsonMockResponse => str_ends_with($url, '/token')
            ? new JsonMockResponse(['id_token' => $this->idToken])
            : new JsonMockResponse($discovery));
        self::getContainer()->set(OidcClient::class, new OidcClient($httpClient, new ArrayAdapter(), self::PROVIDER, 'enter-maps', 'secret'));
    }

    public function testSendsAUserWithoutALoginToTheProvider(): void
    {
        $this->browser->request('GET', '/builder/new');
        $this->assertResponseRedirects('/login?target=/builder/new');

        $this->browser->followRedirect();

        $location = (string) $this->browser->getResponse()->headers->get('Location');
        $this->assertStringStartsWith(self::PROVIDER.'/authorize?', $location);
        $this->assertStringContainsString('redirect_uri=http%3A%2F%2Flocalhost%2Flogin%2Fcallback', $location);
    }

    public function testLogsAnEmployeeInAndComesBackToThePage(): void
    {
        $this->idToken = OidcTestLogin::idToken();

        $this->login('/builder/new');

        $this->assertResponseRedirects('/builder/new');
        $crawler = $this->browser->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextSame('h1', 'Nyt kort');
        $this->assertStringContainsString('Medarbejder', $crawler->filter('header')->text());

        // The session keeps the user, so the next page needs no token.
        $this->browser->request('GET', '/builder');
        $this->assertResponseIsSuccessful();

        // The first login stored the user, and a later login finds the same user.
        $users = self::getContainer()->get(UserRepository::class);
        $this->assertCount(1, $users->findAll());
        $this->browser->getCookieJar()->clear();
        $this->idToken = OidcTestLogin::idToken(['name' => 'Medarbejder Ny']);
        $this->login('/builder');
        $this->browser->followRedirect();
        $this->assertCount(1, $users->findAll());
        $this->assertStringContainsString('Medarbejder Ny', $this->browser->getCrawler()->filter('header')->text());
    }

    public function testDoesNotLetAUserInWhoIsNotInTheGroup(): void
    {
        $this->idToken = OidcTestLogin::idToken(['sub' => 'colleague', 'name' => 'Kollega', 'groups' => ['Other']]);

        $this->login('/builder');
        $this->browser->followRedirect();

        $this->assertResponseStatusCodeSame(403);
        $this->assertSelectorTextSame('h1', 'Ingen adgang');
    }

    public function testRejectsATokenThatIsNotValid(): void
    {
        foreach ([
            'other audience' => OidcTestLogin::idToken(['aud' => ['other-app']]),
            'expired' => OidcTestLogin::idToken(['exp' => time() - 60]),
            'other issuer' => OidcTestLogin::idToken(['iss' => 'https://evil.example.com']),
            'not signed' => 'eyJhbGciOiJub25lIn0.eyJzdWIiOiJlbXBsb3llZSJ9.',
        ] as $case => $token) {
            $this->idToken = $token;
            $this->browser->getCookieJar()->clear();

            $this->login('/builder');
            $this->browser->followRedirect();

            $this->assertResponseStatusCodeSame(401, $case);
            $this->assertSelectorTextSame('h1', 'Login mislykkedes', $case);
        }
    }

    public function testRejectsAnAnswerFromAnotherLogin(): void
    {
        $this->browser->request('GET', '/login');

        $this->browser->request('GET', '/login/callback', ['state' => 'other', 'code' => 'code-1']);
        $this->assertResponseStatusCodeSame(400);

        // The state is used only once.
        $this->browser->request('GET', '/login/callback', ['state' => 'other', 'code' => 'code-1']);
        $this->assertResponseStatusCodeSame(400);
    }

    public function testRejectsAnAnswerWithoutACode(): void
    {
        $this->browser->request('GET', '/login');
        $state = $this->state((string) $this->browser->getResponse()->headers->get('Location'));

        $this->browser->request('GET', '/login/callback', ['state' => $state, 'error' => 'access_denied']);

        $this->assertResponseStatusCodeSame(400);
    }

    public function testGoesOnlyToAPageOnThisSite(): void
    {
        $this->idToken = OidcTestLogin::idToken();

        // A browser removes tabs and newlines from an address, so "/\t/evil" becomes "//evil", which is another site.
        foreach (['//evil.example.com/page', "/\t/evil.example.com", "/\n/evil.example.com", "/\r/evil.example.com", '/\\evil.example.com', '/ /evil.example.com', "/\x00/evil.example.com", 'https://evil.example.com', 'evil.example.com'] as $target) {
            $this->browser->getCookieJar()->clear();

            $this->login($target);

            $this->assertResponseRedirects('/builder', message: json_encode($target, \JSON_THROW_ON_ERROR));
        }

        // A path on this site, with a query string, is kept.
        $this->browser->getCookieJar()->clear();
        $this->login('/builder/new?step=layers');
        $this->assertResponseRedirects('/builder/new?step=layers');
    }

    public function testLogsOutAtTheProviderToo(): void
    {
        $this->idToken = OidcTestLogin::idToken();
        $this->login('/builder');
        $this->browser->followRedirect();

        $this->browser->request('GET', '/logout');

        $this->assertResponseRedirects(self::PROVIDER.'/logout?client_id=enter-maps&post_logout_redirect_uri=http%3A%2F%2Flocalhost%2Flogged-out');
        $this->browser->request('GET', '/logged-out');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextSame('h1', 'Du er logget ud');
        $this->browser->request('GET', '/builder');
        $this->assertResponseRedirects('/login?target=/builder');
    }

    public function testLeavesTheLogoutToTheFirewall(): void
    {
        $this->expectException(\LogicException::class);

        self::getContainer()->get(LoginController::class)->logout();
    }

    public function testLetsTheMapAndItsDataThroughWithoutALogin(): void
    {
        $this->browser->request('GET', '/embed/leaflet/01ARZ3NDEKTSV4RRFFQ69G5FAV');

        $this->assertResponseStatusCodeSame(404);
        $this->assertSelectorTextSame('h1', 'Kortet er ikke tilgængeligt');
        // The embed page is stateless, so the firewall must not start a session.
        $this->assertResponseNotHasHeader('Set-Cookie');
    }

    /**
     * Starts the login, and comes back from the provider with a code.
     */
    private function login(string $target): void
    {
        $this->browser->request('GET', '/login', ['target' => $target]);
        $state = $this->state((string) $this->browser->getResponse()->headers->get('Location'));

        $this->browser->request('GET', '/login/callback', ['state' => $state, 'code' => 'code-1']);
    }

    private function state(string $location): string
    {
        parse_str((string) parse_url($location, \PHP_URL_QUERY), $query);
        $this->assertIsString($query['state'] ?? null);

        return $query['state'];
    }
}
