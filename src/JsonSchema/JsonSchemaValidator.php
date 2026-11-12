<?php

declare(strict_types=1);

namespace App\JsonSchema;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Errors\ValidationError;
use Opis\JsonSchema\Validator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Validates JSON against a schema file in config/schema/.
 *
 * The schemas can refer to cql2-filter.json with its id,
 * urn:enter-maps:cql2-filter.
 */
final readonly class JsonSchemaValidator
{
    private const int MAX_ERRORS = 10;

    private const array SHARED_SCHEMAS = [
        'urn:enter-maps:cql2-filter' => 'cql2-filter.json',
    ];

    public function __construct(
        #[Autowire('%kernel.project_dir%/config/schema')]
        private string $schemaDir,
    ) {
    }

    /**
     * @param mixed  $data       JSON decoded with objects as \stdClass, not as arrays
     * @param string $schemaFile the name of a file in config/schema/
     *
     * @return list<string> one message for each error, with the JSON pointer of the value first; empty if the data is valid
     */
    public function validate(mixed $data, string $schemaFile): array
    {
        $validator = new Validator(max_errors: self::MAX_ERRORS, stop_at_first_error: false);
        foreach (self::SHARED_SCHEMAS as $id => $file) {
            $validator->resolver()?->registerFile($id, $this->schemaDir.'/'.$file);
        }

        $schema = $validator->loader()->loadObjectSchema(
            json_decode((string) file_get_contents($this->schemaDir.'/'.$schemaFile), false, 512, \JSON_THROW_ON_ERROR),
        );
        $error = $validator->validate($data, $schema)->error();
        if (!$error instanceof ValidationError) {
            return [];
        }

        $errors = [];
        foreach (new ErrorFormatter()->format($error) as $pointer => $messages) {
            foreach ($messages as $message) {
                $errors[] = ('' === $pointer ? '/' : $pointer).': '.$message;
            }
        }

        return $errors;
    }
}
