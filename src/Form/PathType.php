<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * A path into the properties of a feature, for example
 * "address.streetAddress". The form theme shows a select with the paths of
 * the data set, if the template gives them in the "paths" variable. Without
 * paths, for example if Enter does not answer, the field is a text field.
 *
 * Example:
 *
 *     {{ form_row(layer.title.path, {paths: {'name': 'Dokk1'}}) }}
 */
final class PathType extends AbstractType
{
    public function getParent(): string
    {
        return TextType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'path';
    }
}
