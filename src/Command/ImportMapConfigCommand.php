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

/**
 * Stores a map configuration from a JSON file, for the Leaflet renderer. Use
 * it until the builder can make configurations.
 */
#[AsCommand(name: 'app:map-config:import', description: 'Import a map configuration from a JSON file')]
final readonly class ImportMapConfigCommand
{
    public function __construct(
        private MapConfigurationFactory $factory,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument('The JSON file with the map configuration')] string $file,
    ): int {
        try {
            $json = new Filesystem()->readFile($file);
        } catch (IOException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }

        try {
            $mapConfiguration = $this->factory->create($json, [Renderer::Leaflet]);
        } catch (InvalidMapConfigException $exception) {
            $io->error(['The map configuration is not valid.', ...$exception->errors]);

            return Command::FAILURE;
        }

        $this->entityManager->persist($mapConfiguration);
        $this->entityManager->flush();

        $io->success(\sprintf('Imported the map configuration %s.', $mapConfiguration->getId()));

        return Command::SUCCESS;
    }
}
