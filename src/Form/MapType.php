<?php

declare(strict_types=1);

namespace App\Form;

use App\Builder\Form\MapData;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\LiveComponent\Form\Type\LiveCollectionType;

/**
 * The form of a map in the builder. The labels are translation keys.
 */
final class MapType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, ['label' => 'builder.form.title', 'help' => 'builder.form.title_help'])
            ->add('description', TextareaType::class, ['label' => 'builder.form.description', 'required' => false])
            ->add('locale', ChoiceType::class, [
                'label' => 'builder.form.locale',
                'empty_data' => 'da-DK',
                'choices' => ['builder.form.locale_da' => 'da-DK', 'builder.form.locale_en' => 'en-GB'],
            ])
            ->add('sidebarPosition', ChoiceType::class, [
                'label' => 'builder.form.position',
                'empty_data' => 'left',
                'expanded' => true,
                'choices' => ['builder.form.position_left' => 'left', 'builder.form.position_right' => 'right'],
            ])
            ->add('showList', CheckboxType::class, ['label' => 'builder.form.list', 'required' => false])
            ->add('categories', CheckboxType::class, ['label' => 'builder.form.categories', 'required' => false])
            ->add('search', ChoiceType::class, [
                'label' => 'builder.form.search',
                'empty_data' => MapData::SEARCH_NONE,
                'expanded' => true,
                'choices' => [
                    'builder.form.search_none' => MapData::SEARCH_NONE,
                    'builder.form.search_local' => MapData::SEARCH_LOCAL,
                    'builder.form.search_address' => MapData::SEARCH_ADDRESS,
                    'builder.form.search_both' => MapData::SEARCH_BOTH,
                ],
            ])
            ->add('viewLongitude', NumberType::class, ['label' => 'builder.form.longitude', 'required' => false, 'scale' => 6, 'html5' => true])
            ->add('viewLatitude', NumberType::class, ['label' => 'builder.form.latitude', 'required' => false, 'scale' => 6, 'html5' => true])
            ->add('viewZoom', IntegerType::class, ['label' => 'builder.form.zoom', 'required' => false])
            ->add('zoomGate', IntegerType::class, ['label' => 'builder.form.zoom_gate', 'help' => 'builder.form.zoom_gate_help', 'required' => false])
            ->add('layers', LiveCollectionType::class, [
                'label' => false,
                'entry_type' => LayerType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'button_add_options' => ['label' => 'builder.form.layer_add'],
                'button_delete_options' => ['label' => 'builder.form.layer_delete'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => MapData::class]);
    }
}
