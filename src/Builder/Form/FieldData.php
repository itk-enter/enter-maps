<?php

declare(strict_types=1);

namespace App\Builder\Form;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * One field in the map form: a value from the properties of a feature.
 * $extra holds the keys that the form cannot edit, for example the texts of
 * the codes, as a JSON object.
 */
final class FieldData
{
    #[Assert\Regex(pattern: '/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)*$/', message: 'builder.form.error.path')]
    public ?string $path = null;

    public ?string $label = null;

    public string $format = 'text';

    public ?string $fallback = null;

    public string $extra = '{}';
}
