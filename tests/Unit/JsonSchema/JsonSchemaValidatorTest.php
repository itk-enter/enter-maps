<?php

declare(strict_types=1);

namespace App\Tests\Unit\JsonSchema;

use App\JsonSchema\JsonSchemaValidator;
use PHPUnit\Framework\TestCase;

final class JsonSchemaValidatorTest extends TestCase
{
    private JsonSchemaValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new JsonSchemaValidator(\dirname(__DIR__, 3).'/config/schema');
    }

    public function testGivesNoErrorsForValidData(): void
    {
        $this->assertSame([], $this->validator->validate(json_decode('{"op": "isNull", "args": [{"property": "a"}]}'), 'cql2-filter.json'));
    }

    public function testGivesOneMessageForEachErrorWithItsPointer(): void
    {
        $errors = $this->validator->validate(json_decode('{"op": "not", "args": [], "x": 1}'), 'cql2-filter.json');

        sort($errors);
        $this->assertCount(2, $errors);
        $this->assertStringStartsWith('/: ', $errors[0]);
        $this->assertStringStartsWith('/op: ', $errors[1]);
    }
}
