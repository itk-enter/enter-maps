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
 */
#[AsCommand(name: 'app:map-config:import', description: 'Import map configurations from JSON files')]
final readonly class ImportMapConfigCommand
{
    public function __construct(
        private MapConfigurationFactory $factory,
        private EntityManagerInterface $entityManager,
        private UrlGeneratorInterface $urlGenerator,
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
                \sprintf('Id in the database: %s', strtoupper(bin2hex($mapConfiguration->getId()->toBinary()))),
                $this->urlGenerator->generate('app_embed_leaflet', ['id' => $mapConfiguration->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);
        }

        return $result;
    }
}
