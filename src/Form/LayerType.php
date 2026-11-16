<?php

declare(strict_types=1);

namespace App\Form;

use App\Builder\Form\LayerData;
use App\Enter\EnterClient;
use App\Enter\EnterUnavailableException;
use App\Renderer\Icons;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\ColorType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\LiveComponent\Form\Type\LiveCollectionType;

/**
 * One layer in the form of a map.
 *
 * The data set is a select with the data sets of Enter. If Enter does not
 * answer, it is a text field, so the editor can still save the map. A data
 * set that Enter does not list stays in the select.
 */
final class LayerType extends AbstractType
{
    /**
     * The data sets of Enter, with the label as key, or null if Enter did not answer.
     *
     * @var array<string, string>|null
     */
    private ?array $dataSets = null;

    private bool $dataSetsLoaded = false;

    public function __construct(
        private readonly EnterClient $enter,
        private readonly Icons $icons,
    ) {
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id', HiddenType::class)
            ->add('extra', HiddenType::class, ['empty_data' => '{}'])
            ->add('rawFilter', HiddenType::class)
            ->add('label', TextType::class, ['label' => 'builder.form.layer_label'])
            ->add('colour', ColorType::class, ['label' => 'builder.form.colour', 'empty_data' => LayerData::DEFAULT_COLOUR])
            ->add('icon', ChoiceType::class, [
                'label' => 'builder.form.icon',
                'required' => false,
                'placeholder' => 'builder.form.icon_default',
                'choices' => array_combine(
                    array_map(static fn (string $name): string => 'builder.form.icon_'.str_replace('-', '_', $name), array_keys($this->icons->all())),
                    array_keys($this->icons->all()),
                ),
            ])
            ->add('title', MapFieldType::class, ['label' => 'builder.form.row_title', 'short' => true])
            ->add('subtitle', MapFieldType::class, ['label' => 'builder.form.row_subtitle', 'required' => false])
            ->add('detail', LiveCollectionType::class, [
                'label' => 'builder.form.detail',
                'entry_type' => MapFieldType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'button_add_options' => ['label' => 'builder.form.field_add'],
                'button_delete_options' => ['label' => 'builder.form.field_delete'],
            ])
            ->add('combine', ChoiceType::class, [
                'label' => 'builder.form.combine',
                'empty_data' => LayerData::COMBINE_AND,
                'expanded' => true,
                'choices' => ['builder.form.combine_and' => LayerData::COMBINE_AND, 'builder.form.combine_or' => LayerData::COMBINE_OR],
            ])
            ->add('conditions', LiveCollectionType::class, [
                'label' => false,
                'entry_type' => ConditionType::class,
                'entry_options' => ['label' => false],
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'button_add_options' => ['label' => 'builder.form.condition_add'],
                'button_delete_options' => ['label' => 'builder.form.condition_delete'],
            ]);

        // The select needs the stored data set, also if Enter does not list it.
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function (FormEvent $event): void {
            $data = $event->getData();
            $this->addDataSet($event, $data instanceof LayerData ? $data->dataSet : null);
        });
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            $this->addDataSet($event, \is_array($data) && \is_string($data['dataSet'] ?? null) ? $data['dataSet'] : null);
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => LayerData::class]);
    }

    private function addDataSet(FormEvent $event, ?string $current): void
    {
        $dataSets = $this->dataSets();
        if (null === $dataSets) {
            $event->getForm()->add('dataSet', TextType::class, ['label' => 'builder.form.data_set', 'help' => 'builder.form.data_set_unavailable']);

            return;
        }

        if (null !== $current && '' !== $current && !\in_array($current, $dataSets, true)) {
            $dataSets[$current] = $current;
        }

        $event->getForm()->add('dataSet', ChoiceType::class, [
            'label' => 'builder.form.data_set',
            'placeholder' => 'builder.form.data_set_select',
            'choices' => $dataSets,
            'choice_translation_domain' => false,
        ]);
    }

    /**
     * Reads /sources.json once for each request.
     *
     * @return array<string, string>|null
     */
    private function dataSets(): ?array
    {
        if (!$this->dataSetsLoaded) {
            $this->dataSetsLoaded = true;
            try {
                $this->dataSets = [];
                foreach ($this->enter->getDataSets() as $dataSet) {
                    $this->dataSets[\sprintf('%s (%s)', $dataSet->title, $dataSet->id)] = $dataSet->id;
                }
                ksort($this->dataSets);
            } catch (EnterUnavailableException) {
                $this->dataSets = null;
            }
        }

        return $this->dataSets;
    }
}
