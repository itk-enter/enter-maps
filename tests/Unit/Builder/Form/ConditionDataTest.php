<?php

declare(strict_types=1);

namespace App\Tests\Unit\Builder\Form;

use App\Builder\Form\ConditionData;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;

final class ConditionDataTest extends TestCase
{
    /**
     * @param array<string, string|bool|null> $values
     * @param array<string, string>           $errors the message of each error, with the property as key
     */
    #[DataProvider('conditions')]
    public function testValidatesTheValueForTheOperatorAndTheType(array $values, array $errors): void
    {
        $condition = new ConditionData();
        $condition->path = 'a';
        foreach ($values as $property => $value) {
            $condition->{$property} = $value;
        }

        $violations = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator()->validate($condition);

        $actual = [];
        foreach ($violations as $violation) {
            /* @var ConstraintViolationInterface $violation */
            $actual[$violation->getPropertyPath()] = $violation->getMessageTemplate();
        }
        $this->assertSame($errors, $actual);
    }

    /**
     * @return iterable<string, array{array<string, string|bool|null>, array<string, string>}>
     */
    public static function conditions(): iterable
    {
        yield 'a text' => [['value' => 'x'], []];
        yield 'no text' => [['value' => ' '], ['value' => 'builder.form.error.required']];
        yield 'a number' => [['type' => ConditionData::TYPE_NUMBER, 'value' => '2.5'], []];
        yield 'not a number' => [['type' => ConditionData::TYPE_NUMBER, 'value' => 'many'], ['value' => 'builder.form.error.number']];
        yield 'yes or no' => [['type' => ConditionData::TYPE_BOOLEAN, 'flag' => false], []];
        yield 'a missing value' => [['operator' => 'isNull'], []];
        yield 'a list' => [['operator' => 'in', 'values' => "a\nb"], []];
        yield 'an empty list' => [['operator' => 'in', 'values' => "\n "], ['values' => 'builder.form.error.required']];
        yield 'a list of numbers' => [['operator' => 'in', 'type' => ConditionData::TYPE_NUMBER, 'values' => "1\n2"], []];
        yield 'a list with a text' => [['operator' => 'in', 'type' => ConditionData::TYPE_NUMBER, 'values' => "1\nx"], ['values' => 'builder.form.error.number']];
        yield 'a list of yes and no' => [['operator' => 'in', 'type' => ConditionData::TYPE_BOOLEAN, 'values' => "true\nfalse"], []];
        yield 'a list with ja' => [['operator' => 'in', 'type' => ConditionData::TYPE_BOOLEAN, 'values' => "true\nja"], ['values' => 'builder.form.error.boolean']];
        yield 'no path' => [['path' => null, 'value' => 'x'], ['path' => 'builder.form.error.required']];
        yield 'a path with a space' => [['path' => 'a b', 'value' => 'x'], ['path' => 'builder.form.error.path']];
    }
}
