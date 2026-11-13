<?php

declare(strict_types=1);

namespace App\Entity;

use App\Renderer\Renderer;
use App\Repository\MapConfigurationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use ITKDev\EntityBundle\Audit\Attribute\Auditable;
use ITKDev\EntityBundle\Audit\Attribute\AuditIgnore;
use ITKDev\EntityBundle\Entity\AbstractITKDevEntity;
use ITKDev\EntityBundle\Entity\Contract\SoftDeletableInterface;
use ITKDev\EntityBundle\Entity\Contract\TimestampableInterface;
use ITKDev\EntityBundle\Entity\Trait\SoftDeletableTrait;
use ITKDev\EntityBundle\Entity\Trait\TimestampableTrait;

/**
 * A stored map configuration. The ULID is the {id} of the embed URL.
 *
 * $config is the source of the map. $mapJson holds the result of the
 * converter of each renderer, so do not change it by hand. Use
 * MapConfigurationFactory to make both from the JSON of an editor.
 *
 * Both are json_object columns, because the json column gives PHP arrays.
 * Then "{}" becomes "[]", the schema rejects the stored configuration, and
 * the renderer gets a list where it expects an object.
 */
#[ORM\Entity(repositoryClass: MapConfigurationRepository::class)]
#[Auditable]
class MapConfiguration extends AbstractITKDevEntity implements TimestampableInterface, SoftDeletableInterface
{
    use SoftDeletableTrait;
    use TimestampableTrait;

    /**
     * @var list<string>
     */
    #[ORM\Column(type: Types::JSON)]
    private array $renderers;

    #[ORM\Column(type: Types::JSON_OBJECT)]
    #[AuditIgnore]
    private \stdClass $mapJson;

    /**
     * @param list<Renderer>                      $renderers
     * @param array<string, array<string, mixed>> $mapJson   the result of the converter of each renderer, with the renderer as key
     */
    public function __construct(
        #[ORM\Column(type: Types::JSON_OBJECT)]
        private \stdClass $config,
        array $renderers,
        array $mapJson,
    ) {
        parent::__construct();
        $this->renderers = array_map(static fn (Renderer $renderer): string => $renderer->value, $renderers);
        // Store the same objects that the database gives back after a load. An encoded object decodes to an object.
        /** @var \stdClass $mapJson */
        $mapJson = json_decode(json_encode((object) $mapJson, \JSON_THROW_ON_ERROR), false, 512, \JSON_THROW_ON_ERROR);
        $this->mapJson = $mapJson;
    }

    public function getConfig(): \stdClass
    {
        return $this->config;
    }

    /**
     * @return list<Renderer>
     */
    public function getRenderers(): array
    {
        return array_map(Renderer::from(...), $this->renderers);
    }

    /**
     * @return \stdClass|null the settings of the renderer, or null if the map does not use it
     */
    public function getMapJson(Renderer $renderer): ?\stdClass
    {
        $settings = $this->mapJson->{$renderer->value} ?? null;

        return $settings instanceof \stdClass ? $settings : null;
    }
}
