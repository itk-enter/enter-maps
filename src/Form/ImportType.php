<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The form of the import in the builder: one field with the JSON of a map
 * config. MapConfigurationImporter validates the JSON against the schema.
 */
final class ImportType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('json', TextareaType::class, [
            'label' => 'builder.import.json',
            'help' => 'builder.import.json_help',
            'attr' => ['class' => 'min-h-[24rem] font-mono text-sm', 'spellcheck' => 'false', 'autocomplete' => 'off'],
            'constraints' => [new Assert\NotBlank()],
        ]);
    }
}
