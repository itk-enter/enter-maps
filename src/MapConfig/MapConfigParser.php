<?php

declare(strict_types=1);

namespace App\MapConfig;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Makes a MapConfig from JSON, after validation against the JSON Schema of
 * its version.
 *
 * Each version has its own schema file in config/schema/. Do not remove the
 * schema of an old version, because embed codes stay on other sites for a
 * long time.
 */
final readonly class MapConfigParser
{
    /**
     * The schema file of each version.
     */
    private const array SCHEMAS = [
        1 => 'map-config.v1.json',
    ];

    private const int MAX_ERRORS = 10;

    public function __construct(
        #[Autowire('%kernel.project_dir%/config/schema')]
        private string $schemaDir,
    ) {
    }

    /**
     * @throws InvalidMapConfigException if the JSON is not valid
     */
    public function parse(string $json): MapConfig
    {
        try {
            $data = json_decode($json, false, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidMapConfigException(['The configuration is not valid JSON: '.$exception->getMessage()]);
        }

        $version = $data instanceof \stdClass ? ($data->version ?? null) : null;
        $schemaFile = \is_int($version) ? (self::SCHEMAS[$version] ?? null) : null;
        if (null === $schemaFile) {
            throw new InvalidMapConfigException([\sprintf('/version: The supported versions are %s.', implode(', ', array_keys(self::SCHEMAS)))]);
        }

        $validator = new Validator(max_errors: self::MAX_ERRORS, stop_at_first_error: false);
        $schema = $validator->loader()->loadObjectSchema(
            json_decode((string) file_get_contents($this->schemaDir.'/'.$schemaFile), false, 512, \JSON_THROW_ON_ERROR),
        );
        $error = $validator->validate($data, $schema)->error();
        if ($error instanceof ValidationError) {
            $errors = [];
            foreach (new ErrorFormatter()->format($error) as $pointer => $messages) {
                foreach ($messages as $message) {
                    $errors[] = ('' === $pointer ? '/' : $pointer).': '.$message;
                }
            }

            throw new InvalidMapConfigException($errors);
        }

        return MapConfig::fromArray(json_decode($json, true, 512, \JSON_THROW_ON_ERROR));
    }
}
