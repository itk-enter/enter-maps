<?php

declare(strict_types=1);

namespace App\Builder\Form;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * The data of the map form. MapFormMapper makes it from a config and makes a
 * config from it. The form uses one field for each thing that the editor
 * sees on the map, for example one search setting for the one search field.
 */
final class MapData
{
    public const string SEARCH_NONE = 'none';
    public const string SEARCH_LOCAL = 'local';
    public const string SEARCH_ADDRESS = 'address';
    public const string SEARCH_BOTH = 'both';

    #[Assert\NotBlank(message: 'builder.form.error.required')]
    #[Assert\Length(max: 200)]
    public ?string $title = null;

    public ?string $description = null;

    public string $locale = 'da-DK';

    public string $sidebarPosition = 'left';

    public bool $showList = true;

    public bool $categories = false;

    public string $search = self::SEARCH_NONE;

    #[Assert\Range(min: -180, max: 180)]
    public ?float $viewLongitude = null;

    #[Assert\Range(min: -90, max: 90)]
    public ?float $viewLatitude = null;

    #[Assert\Range(min: 0, max: 22)]
    public ?int $viewZoom = null;

    #[Assert\Range(min: 0, max: 22)]
    public ?int $zoomGate = null;

    /**
     * @var list<LayerData>
     */
    #[Assert\Valid]
    #[Assert\Count(min: 1, max: 20, minMessage: 'builder.form.error.no_layer')]
    public array $layers = [];

    /**
     * A start view needs the longitude, the latitude and the zoom.
     */
    #[Assert\Callback]
    public function validateView(ExecutionContextInterface $context): void
    {
        $values = [$this->viewLongitude, $this->viewLatitude, $this->viewZoom];
        $count = \count(array_filter($values, static fn (float|int|null $value): bool => null !== $value));
        if (0 !== $count && 3 !== $count) {
            $context->buildViolation('builder.form.error.view')->atPath('viewZoom')->addViolation();
        }
    }
}
