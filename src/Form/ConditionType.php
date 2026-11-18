<?php

declare(strict_types=1);

namespace App\Form;

use App\Builder\Form\ConditionData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * One condition of the fixed filter of a layer. The form has a field for
 * each type of value, and the template shows the one that the operator and
 * the type need.
 */
final class ConditionType extends AbstractType
{
    private const array OPERATOR_KEYS = ['=' => 'eq', '<>' => 'ne', '<' => 'lt', '<=' => 'le', '>' => 'gt', '>=' => 'ge', 'in' => 'in', 'isNull' => 'null'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('path', PathType::class, ['label' => 'builder.form.path', 'help' => 'builder.form.path_help'])
            ->add('operator', ChoiceType::class, [
                'label' => 'builder.form.operator',
                'empty_data' => '=',
                'choices' => array_combine(
                    array_map(static fn (string $operator): string => 'builder.form.operator_'.self::OPERATOR_KEYS[$operator], ConditionData::OPERATORS),
                    ConditionData::OPERATORS,
                ),
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'builder.form.type',
                'empty_data' => ConditionData::TYPE_TEXT,
                'choices' => [
                    'builder.form.type_text' => ConditionData::TYPE_TEXT,
                    'builder.form.type_number' => ConditionData::TYPE_NUMBER,
                    'builder.form.type_boolean' => ConditionData::TYPE_BOOLEAN,
                ],
            ])
            ->add('value', TextType::class, ['label' => 'builder.form.value', 'required' => false])
            ->add('flag', ChoiceType::class, [
                'label' => 'builder.form.value',
                'choices' => ['builder.form.yes' => true, 'builder.form.no' => false],
                'empty_data' => '0',
            ])
            ->add('values', TextareaType::class, ['label' => 'builder.form.values', 'help' => 'builder.form.values_help', 'required' => false])
            ->add('open', CheckboxType::class, ['label' => 'builder.form.condition_open', 'help' => 'builder.form.condition_open_help', 'required' => false])
            ->add('label', TextType::class, ['label' => 'builder.form.condition_label', 'help' => 'builder.form.condition_label_help', 'required' => false])
            ->add('checked', CheckboxType::class, ['label' => 'builder.form.condition_checked', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => ConditionData::class]);
    }
}
