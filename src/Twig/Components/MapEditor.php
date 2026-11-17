<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Builder\DataSetFields;
use App\Builder\Form\ConditionData;
use App\Builder\Form\LayerData;
use App\Builder\Form\MapData;
use App\Builder\Form\MapFormMapper;
use App\Builder\PreviewStore;
use App\Entity\MapConfiguration;
use App\Form\MapType;
use App\MapConfig\InvalidMapConfigException;
use App\MapConfig\MapConfigurationFactory;
use App\Renderer\Renderer;
use App\Repository\MapConfigurationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Response;
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
 * JavaScript of its own. save() validates the form, makes the config with
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

    public const string THEN_PREVIEW = 'preview';
    public const string THEN_EMBED = 'embed';

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
     * The token of the last preview, or null if the form shows no preview.
     * Each preview has a new token, so the frame loads the new map.
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

    public function __construct(
        private readonly MapFormMapper $mapper,
        private readonly MapConfigurationFactory $factory,
        private readonly MapConfigurationRepository $repository,
        private readonly TranslatorInterface $translator,
        private readonly DataSetFields $dataSetFields,
        private readonly PreviewStore $previews,
    ) {
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
            $propertyAccessor->setValue($this->formValues, $path.'['.array_key_last($items).']', self::NEW_ITEMS[$collection]);
        }
    }

    #[LiveAction]
    public function save(#[LiveArg] string $then = self::THEN_PREVIEW): ?Response
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

            return null;
        }

        $this->repository->save($map);
        $this->addFlash('success', $this->translator->trans('builder.form.saved', ['%title%' => $map->getConfig()->title ?? '']));

        return $this->redirectToRoute(self::THEN_EMBED === $then ? 'app_builder_embed' : 'app_builder_preview', ['id' => $map->getId()]);
    }

    /**
     * Shows the map from the form in a frame, without a save. The map that
     * other sites show does not change.
     */
    #[LiveAction]
    public function preview(): void
    {
        $json = $this->submittedConfig();

        try {
            $draft = $this->factory->create($json, [Renderer::Leaflet]);
        } catch (InvalidMapConfigException $exception) {
            $this->saveErrors = $exception->errors;

            return;
        }

        /** @var \stdClass $settings the converter of the renderer ran, so the settings are there */
        $settings = $draft->getMapJson(Renderer::Leaflet);
        $this->previewToken = $this->previews->add($settings);
    }

    #[LiveAction]
    public function closePreview(): void
    {
        $this->previewToken = null;
    }

    /**
     * Validates the form and gives the config as JSON.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException if the form is not valid
     */
    private function submittedConfig(): string
    {
        $this->submitForm();

        /** @var MapData $data */
        $data = $this->getForm()->getData();

        return json_encode($this->mapper->toConfig($data, $this->map()?->getConfig()), \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);
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
