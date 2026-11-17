<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Builder\DataSetChoices;
use App\Builder\DataSetFields;
use App\Builder\Form\ConditionData;
use App\Builder\Form\LayerData;
use App\Builder\Form\MapData;
use App\Builder\Form\MapFormMapper;
use App\Builder\LayerDefaults;
use App\Builder\PreviewStore;
use App\Entity\MapConfiguration;
use App\Form\LayerType;
use App\Form\MapType;
use App\MapConfig\InvalidMapConfigException;
use App\MapConfig\MapConfigurationFactory;
use App\Renderer\Renderer;
use App\Repository\MapConfigurationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\LiveComponent\LiveCollectionTrait;

/**
 * The form of a map in the builder, for a new map and for a stored map.
 *
 * The server renders the form again after each change, so the form can add a
 * layer or a condition, and show the field that an operator needs, without
 * JavaScript of its own.
 *
 * The form has four steps on one page: the settings, the data sets, the
 * layers and the view. The page shows one step and hides the others, but
 * keeps their fields, so that the form has all the values. A tick on a data
 * set adds a layer with the data set. save() validates the form, makes the config with
 * MapFormMapper, and validates it again against the schema. Then it stores
 * the map and shows the map or the embed code.
 */
#[AsLiveComponent]
final class MapEditor extends AbstractController
{
    use DefaultActionTrait;
    use LiveCollectionTrait {
        addCollectionItem as private addEmptyCollectionItem;
    }

    public const string STEP_SETTINGS = 'settings';
    public const string STEP_DATA_SETS = 'datasets';
    public const string STEP_LAYERS = 'layers';
    public const string STEP_VIEW = 'view';

    public const array STEPS = [self::STEP_SETTINGS, self::STEP_DATA_SETS, self::STEP_LAYERS, self::STEP_VIEW];

    /**
     * The fields of the map form on each step. The layers are on their own step.
     */
    private const array STEP_FIELDS = [
        self::STEP_SETTINGS => ['title', 'description', 'locale', 'sidebarPosition', 'search', 'showList', 'categories', 'routeLink', 'loadingSpinner'],
        self::STEP_VIEW => ['viewLongitude', 'viewLatitude', 'viewZoom', 'zoomGate'],
    ];

    /**
     * The values of a new item, for each collection. Without them, a new
     * layer is black, and a new condition has no operator.
     */
    private const array NEW_ITEMS = [
        'layers' => ['colour' => LayerData::DEFAULT_COLOUR, 'combine' => LayerData::COMBINE_AND, 'extra' => '{}', 'title' => ['extra' => '{}'], 'subtitle' => ['format' => 'text', 'extra' => '{}']],
        'detail' => ['format' => 'text', 'extra' => '{}'],
        'conditions' => ['operator' => '=', 'type' => ConditionData::TYPE_TEXT],
    ];

    /**
     * The id of the stored map, or null for a new map.
     */
    #[LiveProp]
    public ?string $mapId = null;

    /**
     * The keys of the layers that are open. On page load, the layers of a
     * stored map are closed. A new layer is open.
     *
     * @var list<string>
     */
    #[LiveProp]
    public array $openLayers = [];

    /**
     * The step that the form shows. See STEPS.
     */
    #[LiveProp]
    public string $step = self::STEP_SETTINGS;

    /**
     * The token of the last preview, or null before the first preview. Without
     * a token, the frame shows the stored map. Each preview has a new token,
     * so the frame loads the new map.
     */
    #[LiveProp]
    public ?string $previewToken = null;

    /**
     * The errors of the schema validation, if the form is valid but the config is not.
     *
     * @var list<string>
     */
    public array $saveErrors = [];

    private ?MapConfiguration $map = null;

    /**
     * @var array<string, array{title: string, label: string, type: string|null}>|null
     */
    private ?array $dataSets = null;

    private bool $dataSetsLoaded = false;

    public function __construct(
        private readonly MapFormMapper $mapper,
        private readonly MapConfigurationFactory $factory,
        private readonly MapConfigurationRepository $repository,
        private readonly TranslatorInterface $translator,
        private readonly DataSetFields $dataSetFields,
        private readonly PreviewStore $previews,
        private readonly DataSetChoices $dataSetChoices,
        private readonly LayerDefaults $layerDefaults,
    ) {
    }

    /**
     * The data sets of Enter, for the check boxes of the data set step: the
     * title, the label and the NGSI-LD type of each, with the ID as key.
     *
     * @return array<string, array{title: string, label: string, type: string|null}>|null null if Enter does not answer
     */
    public function dataSets(): ?array
    {
        if (!$this->dataSetsLoaded) {
            $this->dataSetsLoaded = true;
            $this->dataSets = $this->dataSetChoices->dataSets();
        }

        return $this->dataSets;
    }

    /**
     * The steps that the editor can open. The layers and the view need a
     * title and a data set first. A stored map has them, so all its steps are
     * open. If Enter does not answer, the form has no list of data sets, so
     * the title is sufficient.
     *
     * @return list<string>
     */
    public function availableSteps(): array
    {
        $title = trim((string) ($this->formValues['title'] ?? ''));
        $ready = null !== $this->mapId
            || ('' !== $title && ([] !== $this->selectedDataSets() || null === $this->dataSets()));

        return $ready ? self::STEPS : [self::STEP_SETTINGS, self::STEP_DATA_SETS];
    }

    /**
     * @return list<string> the IDs of the data sets that the layers use
     */
    public function selectedDataSets(): array
    {
        $ids = [];
        foreach ($this->layerValues() as $layer) {
            if (\is_string($layer['dataSet'] ?? null) && '' !== $layer['dataSet']) {
                $ids[] = $layer['dataSet'];
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * The steps that have an error, for the marks in the menu of the steps.
     *
     * @return list<string>
     */
    public function stepsWithErrors(): array
    {
        $steps = [];
        $form = $this->getForm();
        foreach (self::STEP_FIELDS as $step => $fields) {
            foreach ($fields as $field) {
                if ($form->has($field) && \count($form->get($field)->getErrors(true)) > 0) {
                    $steps[$step] = true;
                }
            }
        }
        // An error of the map, for example "no layer", is above all the steps.
        foreach ($form->get('layers') as $layer) {
            if (\count($layer->getErrors(true)) > 0) {
                $steps[self::STEP_LAYERS] = true;
            }
        }
        foreach ($this->saveErrors as $error) {
            $steps[$this->stepOfSchemaError($error)] = true;
        }

        return array_values(array_filter(self::STEPS, static fn (string $step): bool => isset($steps[$step])));
    }

    /**
     * Shows a step of the form, if the step is available. See availableSteps().
     * The view step also updates the map from the form, so that the map shows
     * the changes on the other steps. If the form has an error, the view step
     * stays, and the menu marks the steps with an error.
     *
     * @param string $step one of STEPS
     */
    #[LiveAction]
    public function showStep(#[LiveArg] string $step): void
    {
        if (!\in_array($step, $this->availableSteps(), true)) {
            return;
        }

        $this->step = $step;
        if (self::STEP_VIEW === $step) {
            $this->updatePreview(false);
        }
    }

    /**
     * Adds a layer with a data set, or removes the layers with the data set.
     * If the form has a layer without a data set, the data set goes into that
     * layer. The layer gets the defaults of the type of the data set (see
     * LayerDefaults), and the title of the data set as label.
     *
     * @param string $id the ID of the data set
     */
    #[LiveAction]
    public function toggleDataSet(PropertyAccessorInterface $propertyAccessor, #[LiveArg] string $id): void
    {
        $collection = $this->formName.'[layers]';
        $using = array_keys(array_filter($this->layerValues(), static fn (array $layer): bool => ($layer['dataSet'] ?? null) === $id));
        if ([] !== $using) {
            foreach ($using as $key) {
                $this->removeCollectionItem($propertyAccessor, $collection, (int) $key);
                $this->openLayers = array_values(array_diff($this->openLayers, [(string) $key]));
            }
            // Without data sets, a new map closes the layers and the view again.
            if (!\in_array($this->step, $this->availableSteps(), true)) {
                $this->step = self::STEP_DATA_SETS;
            }

            return;
        }

        $empty = array_key_first(array_filter($this->layerValues(), static fn (array $layer): bool => '' === (string) ($layer['dataSet'] ?? '')));
        if (null === $empty) {
            $this->addCollectionItem($propertyAccessor, $collection);
            $empty = array_key_last($this->layerValues());
        }

        $dataSet = $this->dataSets()[$id] ?? null;
        $layer = $this->mapper->layerToData($this->layerDefaults->forDataSet($id, $dataSet['type'] ?? null));
        $layer->dataSet = $id;
        $layer->label = $dataSet['title'] ?? $id;
        $this->formValues['layers'][$empty] = $this->extractFormValues($this->createForm(LayerType::class, $layer)->createView());
    }

    /**
     * The paths of the properties of a data set, for the suggestions of the
     * path fields of a layer.
     *
     * @return array<string, string> the example of each path
     */
    public function paths(?string $dataSetId): array
    {
        return $this->dataSetFields->paths($dataSetId);
    }

    public function mount(?string $mapId = null): void
    {
        $this->mapId = '' === $mapId ? null : $mapId;
    }

    /**
     * Adds an item to a collection, with the values in NEW_ITEMS.
     */
    #[LiveAction]
    public function addCollectionItem(PropertyAccessorInterface $propertyAccessor, #[LiveArg] string $name): void
    {
        $this->addEmptyCollectionItem($propertyAccessor, $name);

        $collection = preg_match('/\[(\w+)\]$/', $name, $matches) ? $matches[1] : '';
        $path = substr($name, \strlen($this->formName));
        $items = $propertyAccessor->getValue($this->formValues, $path);
        if (isset(self::NEW_ITEMS[$collection]) && \is_array($items) && [] !== $items) {
            $key = (string) array_key_last($items);
            $propertyAccessor->setValue($this->formValues, $path.'['.$key.']', self::NEW_ITEMS[$collection]);
            // A new layer is open, so that the editor can fill it in.
            if ('[layers]' === $path) {
                $this->openLayers[] = $key;
            }
        }
    }

    /**
     * Opens or closes a layer of the form.
     *
     * @param string $key the key of the layer in the collection
     */
    #[LiveAction]
    public function toggleLayer(#[LiveArg] string $key): void
    {
        $this->openLayers = \in_array($key, $this->openLayers, true)
            ? array_values(array_diff($this->openLayers, [$key]))
            : [...$this->openLayers, $key];
    }

    /**
     * Stores the map, and shows it. The embed code is a link in the menu of the steps.
     */
    #[LiveAction]
    public function save(): ?Response
    {
        $json = $this->submittedConfig();
        $map = $this->map();

        try {
            if (!$map instanceof MapConfiguration) {
                $map = $this->factory->create($json, [Renderer::Leaflet]);
            } else {
                $this->factory->update($map, $json);
            }
        } catch (InvalidMapConfigException $exception) {
            $this->saveErrors = $exception->errors;
            $this->openLayersOfSchemaErrors($exception->errors);
            $this->showStepWithError();

            return null;
        }

        $this->repository->save($map);
        $this->addFlash('success', $this->translator->trans('builder.form.saved', ['%title%' => $map->getConfig()->title ?? '']));

        return $this->redirectToRoute('app_builder_preview', ['id' => $map->getId()]);
    }

    /**
     * Shows the map from the form in the frame of the view step, without a
     * save. The map that other sites show does not change.
     */
    #[LiveAction]
    public function preview(): void
    {
        $this->updatePreview(true);
    }

    /**
     * @param bool $showErrorStep if true, the form shows the first step with an error
     */
    private function updatePreview(bool $showErrorStep): void
    {
        $json = $this->submittedConfig($showErrorStep);

        try {
            $draft = $this->factory->create($json, [Renderer::Leaflet]);
        } catch (InvalidMapConfigException $exception) {
            $this->saveErrors = $exception->errors;
            $this->openLayersOfSchemaErrors($exception->errors);
            if ($showErrorStep) {
                $this->showStepWithError();
            }

            return;
        }

        /** @var \stdClass $settings the converter of the renderer ran, so the settings are there */
        $settings = $draft->getMapJson(Renderer::Leaflet);
        $this->previewToken = $this->previews->add($settings);
        $this->step = self::STEP_VIEW;
    }

    /**
     * Validates the form and gives the config as JSON.
     *
     * @param bool $showErrorStep if true and the form is not valid, the form shows the first step with an error
     *
     * @throws UnprocessableEntityHttpException if the form is not valid
     */
    private function submittedConfig(bool $showErrorStep = true): string
    {
        try {
            $this->submitForm();
        } catch (UnprocessableEntityHttpException $exception) {
            // The errors must be visible, so a closed layer with an error opens.
            foreach ($this->getForm()->get('layers') as $key => $layer) {
                if (!$layer->isValid()) {
                    $this->openLayer($key);
                }
            }
            if ($showErrorStep) {
                $this->showStepWithError();
            }

            throw $exception;
        }

        /** @var MapData $data */
        $data = $this->getForm()->getData();

        return json_encode($this->mapper->toConfig($data, $this->map()?->getConfig()), \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
    }

    /**
     * Opens the layers that the errors of the schema point to, for example
     * "/layers/1/row/title/path: …". The number is the position of the layer.
     *
     * @param list<string> $errors
     */
    private function openLayersOfSchemaErrors(array $errors): void
    {
        $keys = array_keys($this->getForm()->get('layers')->all());
        foreach ($errors as $error) {
            if (1 === preg_match('#^/layers/(\d+)#', $error, $matches) && isset($keys[(int) $matches[1]])) {
                $this->openLayer((string) $keys[(int) $matches[1]]);
            }
        }
    }

    /**
     * Shows the first step with an error, if the step that the form shows has none.
     */
    private function showStepWithError(): void
    {
        $steps = $this->stepsWithErrors();
        if ([] !== $steps && !\in_array($this->step, $steps, true)) {
            $this->step = $steps[0];
        }
    }

    /**
     * Gives the step of an error of the schema, for example "/layers/1/row/title/path: …".
     */
    private function stepOfSchemaError(string $error): string
    {
        return match (true) {
            1 === preg_match('#^/layers\b#', $error) => self::STEP_LAYERS,
            1 === preg_match('#^/(view|zoomGate)\b#', $error) => self::STEP_VIEW,
            default => self::STEP_SETTINGS,
        };
    }

    /**
     * The values of the layers in the form, with the key of each layer.
     *
     * @return array<int|string, array<string, mixed>>
     */
    private function layerValues(): array
    {
        $layers = $this->formValues['layers'] ?? [];

        return \is_array($layers) ? array_filter($layers, \is_array(...)) : [];
    }

    private function openLayer(string $key): void
    {
        if (!\in_array($key, $this->openLayers, true)) {
            $this->openLayers[] = $key;
        }
    }

    protected function instantiateForm(): FormInterface
    {
        return $this->createForm(MapType::class, $this->mapper->toData($this->map()?->getConfig()));
    }

    private function map(): ?MapConfiguration
    {
        if (!$this->map instanceof MapConfiguration && null !== $this->mapId) {
            $this->map = $this->repository->find($this->mapId);
        }

        return $this->map;
    }
}
