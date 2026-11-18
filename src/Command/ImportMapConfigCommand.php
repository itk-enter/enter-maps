<?php

declare(strict_types=1);

namespace App\Command;

use App\MapConfig\InvalidMapConfigException;
use App\MapConfig\MapConfigurationFactory;
use App\Renderer\Renderer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Stores map configurations from JSON files, for the Leaflet renderer, and
 * gives the embed URL of each. Use it until the builder can make
 * configurations.
 *
 * If a file is not valid, the command gives an error for it, imports the
 * other files and returns a failure.
 *
 * The files in tests/Fixtures/map-config are test data, not real maps. The
 * output tells when a file comes from there.
 */
#[AsCommand(
    name: 'app:map-config:import',
    description: 'Import map configurations from JSON files',
    help: <<<'HELP'
        Imports each file as a new map, and gives its id and its embed URL.

        The fixtures in tests/Fixtures/map-config are test data, not real maps:

          <info>%command.full_name% tests/Fixtures/map-config/*.json</info>
        HELP,
)]
final readonly class ImportMapConfigCommand
{
    public function __construct(
        private MapConfigurationFactory $factory,
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire('%kernel.project_dir%/tests/Fixtures')]
        private string $fixtureDirectory,
    ) {
    }

    /**
     * @param list<string> $files
     */
    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The JSON files with the map configurations')] array $files,
    ): int {
        $result = Command::SUCCESS;

        foreach ($files as $file) {
            try {
                $mapConfiguration = $this->factory->create(new Filesystem()->readFile($file), [Renderer::Leaflet]);
            } catch (IOException $exception) {
                $io->error($exception->getMessage());
                $result = Command::FAILURE;

                continue;
            } catch (InvalidMapConfigException $exception) {
                $io->error([\sprintf('%s is not a valid map configuration.', $file), ...$exception->errors]);
                $result = Command::FAILURE;

                continue;
            }

            $this->entityManager->persist($mapConfiguration);
            $this->entityManager->flush();

            $io->success([
                \sprintf('Imported %s as %s.', $file, $mapConfiguration->getId()),
                ...$this->isFixture($file) ? ['This is fixture data for tests, not a real map.'] : [],
                \sprintf('Id in the database: %s', strtoupper(bin2hex($mapConfiguration->getId()->toBinary()))),
                $this->urlGenerator->generate('app_embed_leaflet', ['id' => $mapConfiguration->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);
        }

        return $result;
    }

    private function isFixture(string $file): bool
    {
        $path = realpath($file);
        $fixtures = realpath($this->fixtureDirectory);

        return false !== $path && false !== $fixtures && str_starts_with($path, $fixtures.\DIRECTORY_SEPARATOR);
    }
}
