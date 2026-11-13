<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\MapConfigurationRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Lists the stored map configurations, with the id, the title and the embed
 * URL of each. Deleted configurations are not in the list.
 *
 * The URLs start with DEFAULT_URI.
 */
#[AsCommand(name: 'app:map-config:list', description: 'List the map configurations and their embed URLs')]
final readonly class ListMapConfigCommand
{
    public function __construct(
        private MapConfigurationRepository $repository,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $rows = [];
        foreach ($this->repository->findBy([], ['createdAt' => 'ASC']) as $mapConfiguration) {
            $title = $mapConfiguration->getConfig()->title ?? null;
            $rows[] = [
                (string) $mapConfiguration->getId(),
                \is_string($title) ? $title : '',
                $this->urlGenerator->generate('app_embed_leaflet', ['id' => $mapConfiguration->getId()], UrlGeneratorInterface::ABSOLUTE_URL),
            ];
        }

        if ([] === $rows) {
            $io->note('There are no map configurations. Import one with app:map-config:import.');

            return Command::SUCCESS;
        }

        $io->table(['Id', 'Title', 'URL'], $rows);

        return Command::SUCCESS;
    }
}
