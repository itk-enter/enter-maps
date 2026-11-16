<?php

declare(strict_types=1);

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * The upload of a JSON file with a map configuration, on the start page of
 * the builder. The controller validates the content.
 */
final class ImportMapConfigurationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('file', FileType::class, [
            'label' => 'builder.import.file',
            'help' => 'builder.import.help',
            'attr' => ['accept' => '.json,application/json'],
            'constraints' => [
                new NotNull(message: 'builder.import.no_file'),
                new File(
                    maxSize: '1M',
                    extensions: ['json' => ['application/json', 'text/plain']],
                    extensionsMessage: 'builder.import.not_json',
                ),
            ],
        ]);
    }
}
