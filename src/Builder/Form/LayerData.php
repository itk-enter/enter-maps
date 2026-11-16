<?php

declare(strict_types=1);

namespace App\Builder\Form;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * One layer in the map form.
 *
 * $id is empty for a new layer. The mapper makes it from the label on the
 * first save, and it does not change after that, because embed codes use it.
 *
 * $extra and $rawFilter hold the parts of the layer that the form cannot
 * edit, as JSON. They are hidden fields, so they stay with the layer when the
 * editor removes another layer.
 */
final class LayerData
{
    public const string COMBINE_AND = 'and';
    public const string COMBINE_OR = 'or';

    public const string DEFAULT_COLOUR = '#3661d8';

    public ?string $id = null;

    #[Assert\NotBlank(message: 'builder.form.error.required')]
    #[Assert\Regex('/^[A-Za-z0-9_.:-]+$/')]
    public ?string $dataSet = null;

    #[Assert\NotBlank(message: 'builder.form.error.required')]
    public ?string $label = null;

    #[Assert\Regex('/^#[0-9a-fA-F]{6}$/')]
    public string $colour = self::DEFAULT_COLOUR;

    public ?string $icon = null;

    #[Assert\Valid]
    public FieldData $title;

    #[Assert\Valid]
    public FieldData $subtitle;

    /**
     * @var list<FieldData>
     */
    #[Assert\Valid]
    #[Assert\Count(max: 50)]
    public array $detail = [];

    public string $combine = self::COMBINE_AND;

    /**
     * @var list<ConditionData>
     */
    #[Assert\Valid]
    #[Assert\Count(max: 20)]
    public array $conditions = [];

    /**
     * A filter that has more than one level, as JSON, or null.
     */
    public ?string $rawFilter = null;

    /**
     * The other keys of the layer, as a JSON object.
     */
    public string $extra = '{}';

    public function __construct()
    {
        $this->title = new FieldData();
        $this->subtitle = new FieldData();
    }

    /**
     * The title of a row and each field of the detail view need a path. The
     * subtitle is optional.
     */
    #[Assert\Callback]
    public function validatePaths(ExecutionContextInterface $context): void
    {
        $fields = ['title' => $this->title];
        foreach ($this->detail as $index => $field) {
            $fields[\sprintf('detail[%d]', $index)] = $field;
        }

        foreach ($fields as $path => $field) {
            if (null === $field->path || '' === trim($field->path)) {
                $context->buildViolation('builder.form.error.required')->atPath($path.'.path')->addViolation();
            }
        }
    }
}
