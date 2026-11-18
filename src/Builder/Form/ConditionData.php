<?php

declare(strict_types=1);

namespace App\Builder\Form;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * One condition of the fixed filter of a layer.
 *
 * The type of the value is explicit, because CQL2 compares typed values:
 * true and "true" are different, and so are 5 and "5". The form shows the
 * field for the operator and the type: $value for one value, $flag for a
 * yes-no value and $values for "in", with one value on each line.
 *
 * If $open is true, the user can turn the condition on and off in the
 * sidebar of the map. Then the condition is an open filter of the layer,
 * with $label as the text of its checkbox, and not part of the fixed filter.
 */
final class ConditionData
{
    public const array OPERATORS = ['=', '<>', '<', '<=', '>', '>=', 'in', 'isNull'];

    public const string TYPE_TEXT = 'text';
    public const string TYPE_NUMBER = 'number';
    public const string TYPE_BOOLEAN = 'boolean';

    #[Assert\NotBlank(message: 'builder.form.error.required')]
    #[Assert\Regex(pattern: '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)*$/', message: 'builder.form.error.path')]
    public ?string $path = null;

    public string $operator = '=';

    public string $type = self::TYPE_TEXT;

    public ?string $value = null;

    public bool $flag = true;

    public ?string $values = null;

    public bool $open = false;

    #[Assert\Length(max: 200)]
    public ?string $label = null;

    public bool $checked = false;

    /**
     * A condition that the user can turn on and off needs a text for its checkbox.
     */
    #[Assert\Callback]
    public function validateLabel(ExecutionContextInterface $context): void
    {
        if ($this->open && '' === trim((string) $this->label)) {
            $context->buildViolation('builder.form.error.required')->atPath('label')->addViolation();
        }
    }

    /**
     * The field for the operator and the type needs a value. A number must
     * be a number.
     */
    #[Assert\Callback]
    public function validateValue(ExecutionContextInterface $context): void
    {
        if ('isNull' === $this->operator) {
            return;
        }

        if ('in' === $this->operator) {
            $values = array_filter(array_map(trim(...), explode("\n", (string) $this->values)), static fn (string $value): bool => '' !== $value);
            if ([] === $values) {
                $context->buildViolation('builder.form.error.required')->atPath('values')->addViolation();
            } elseif (self::TYPE_NUMBER === $this->type && [] !== array_filter($values, static fn (string $value): bool => !is_numeric($value))) {
                $context->buildViolation('builder.form.error.number')->atPath('values')->addViolation();
            } elseif (self::TYPE_BOOLEAN === $this->type && [] !== array_diff($values, ['true', 'false'])) {
                $context->buildViolation('builder.form.error.boolean')->atPath('values')->addViolation();
            }

            return;
        }

        if (self::TYPE_BOOLEAN === $this->type) {
            return;
        }

        $value = trim((string) $this->value);
        if ('' === $value) {
            $context->buildViolation('builder.form.error.required')->atPath('value')->addViolation();
        } elseif (self::TYPE_NUMBER === $this->type && !is_numeric($value)) {
            $context->buildViolation('builder.form.error.number')->atPath('value')->addViolation();
        }
    }
}
