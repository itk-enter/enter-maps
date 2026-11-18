<?php

declare(strict_types=1);

namespace App\Command;

use App\Builder\MapConfigurationImporter;
use App\MapConfig\InvalidMapConfigException;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
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
 *
 * With --format=json, the command writes a JSON list of the imported maps
 * for scripts, and writes the errors to stderr. Example:
 *
 *     [{"file": "a.json", "id": "01M7…", "url": "https://…/embed/leaflet/01M7…", "fixture": false}]
 */
#[AsCommand(
    name: 'app:map-config:import',
    description: 'Import map configurations from JSON files',
    help: <<<'HELP'
        Imports each file as a new map, and gives its id and its embed URL.

        The fixtures in tests/Fixtures/map-config are test data, not real maps:

          <info>%command.full_name% tests/Fixtures/map-config/*.json</info>

        For a script, give the result as JSON. The errors go to stderr:

          <info>%command.full_name% --format=json tests/Fixtures/map-config/*.json</info>
        HELP,
)]
final readonly class ImportMapConfigCommand
{
    public const string FORMAT_TEXT = 'text';
    public const string FORMAT_JSON = 'json';

    public function __construct(
        private MapConfigurationImporter $importer,
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
        #[Option('The format of the output: "text" for people, or "json" for scripts')] string $format = self::FORMAT_TEXT,
    ): int {
        if (!\in_array($format, [self::FORMAT_TEXT, self::FORMAT_JSON], true)) {
            $io->error(\sprintf('The format "%s" is not known. Use "text" or "json".', $format));

            return Command::INVALID;
        }

        // With JSON, the errors go to stderr, so that stdout has only the JSON.
        $errors = self::FORMAT_JSON === $format ? $io->getErrorStyle() : $io;
        $result = Command::SUCCESS;
        $imported = [];

        foreach ($files as $file) {
            try {
                $mapConfiguration = $this->importer->import(new Filesystem()->readFile($file));
            } catch (IOException $exception) {
                $errors->error($exception->getMessage());
                $result = Command::FAILURE;

                continue;
            } catch (InvalidMapConfigException $exception) {
                $errors->error([\sprintf('%s is not a valid map configuration.', $file), ...$exception->errors]);
                $result = Command::FAILURE;

                continue;
            }

            $id = $mapConfiguration->getId();
            $url = $this->urlGenerator->generate('app_embed_leaflet', ['id' => $id], UrlGeneratorInterface::ABSOLUTE_URL);
            $imported[] = ['file' => $file, 'id' => (string) $id, 'url' => $url, 'fixture' => $this->isFixture($file)];

            if (self::FORMAT_TEXT === $format) {
                $io->success([
                    \sprintf('Imported %s as %s.', $file, $id),
                    ...$this->isFixture($file) ? ['This is fixture data for tests, not a real map.'] : [],
                    \sprintf('Id in the database: %s', strtoupper(bin2hex($id->toBinary()))),
                    $url,
                ]);
            }
        }

        if (self::FORMAT_JSON === $format) {
            $io->writeln(json_encode($imported, \JSON_THROW_ON_ERROR | \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES));
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
