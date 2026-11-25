<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\OidcUserProvider;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Signature\Algorithm\RS256;
use Jose\Component\Signature\JWSBuilder;
use Jose\Component\Signature\Serializer\CompactSerializer;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Logins for tests. The test environment checks ID tokens with the public
 * key of tests/Support/oidc-test-key.json (OIDC_TEST_KEYSET in .env.test), so
 * idToken() makes tokens that the app accepts.
 *
 * Example:
 *
 *     OidcTestLogin::asEmployee($client);
 *     $token = OidcTestLogin::idToken(['groups' => []]);
 */
final class OidcTestLogin
{
    /**
     * Logs the client in as an employee, without the login flow. The user is
     * stored, because the session loads it from the database on each request.
     * Call it after Database::reset().
     */
    public static function asEmployee(KernelBrowser $client): User
    {
        $user = new User($_SERVER['OIDC_ISSUER'] ?? $_ENV['OIDC_ISSUER'], 'employee');
        $user->logIn('Medarbejder', 'employee@example.com', [OidcUserProvider::ROLE_EMPLOYEE], new \DateTimeImmutable());
        $client->getContainer()->get(UserRepository::class)->save($user);
        $client->loginUser($user, 'main');

        return $user;
    }

    /**
     * An ID token for the employee, signed with the test key.
     *
     * @param array<string, mixed> $claims claims that replace the claims of the employee
     */
    public static function idToken(array $claims = []): string
    {
        $now = time();
        $payload = $claims + [
            'iss' => $_SERVER['OIDC_ISSUER'] ?? $_ENV['OIDC_ISSUER'],
            'aud' => [$_SERVER['OIDC_CLIENT_ID'] ?? $_ENV['OIDC_CLIENT_ID']],
            'sub' => 'employee',
            'name' => 'Medarbejder',
            'email' => 'employee@example.com',
            'groups' => ['Employee'],
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + 300,
        ];

        $key = new JWK(json_decode((string) file_get_contents(__DIR__.'/oidc-test-key.json'), true, 512, \JSON_THROW_ON_ERROR));
        $jws = new JWSBuilder(new AlgorithmManager([new RS256()]))
            ->create()
            ->withPayload(json_encode($payload, \JSON_THROW_ON_ERROR))
            ->addSignature($key, ['alg' => 'RS256', 'kid' => $key->get('kid')])
            ->build();

        return new CompactSerializer()->serialize($jws, 0);
    }
}
