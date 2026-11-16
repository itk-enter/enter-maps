<?php

declare(strict_types=1);

namespace App\Form;

use App\Builder\Form\FieldData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One field of a layer: a path into the properties of a feature, and how the
 * map shows the value. With the option "short", the form has only the path
 * and the fallback, for the title of a row.
 */
final class MapFieldType extends AbstractType
{
    public const array FORMATS = ['text', 'list', 'boolean', 'number', 'date', 'link'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if (!$options['short']) {
            $builder->add('label', TextType::class, ['label' => 'builder.form.field_label', 'required' => false]);
        }
        $builder->add('path', TextType::class, [
            'label' => 'builder.form.path',
            'help' => 'builder.form.path_help',
            'required' => $options['required'],
        ]);
        if (!$options['short']) {
            $builder->add('format', ChoiceType::class, [
                'label' => 'builder.form.format',
                'empty_data' => 'text',
                'choices' => array_combine(array_map(static fn (string $format): string => 'builder.form.format_'.$format, self::FORMATS), self::FORMATS),
            ]);
        }
        $builder
            ->add('fallback', TextType::class, ['label' => 'builder.form.fallback', 'required' => false])
            ->add('extra', HiddenType::class, ['empty_data' => '{}']);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => FieldData::class, 'short' => false]);
        $resolver->setAllowedTypes('short', 'bool');
    }
}
