<?php

declare(strict_types=1);

namespace App\Controller;

use App\Embed\EmbedOptionsParser;
use App\Renderer\Renderer;
use App\Repository\MapConfigurationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Translation\LocaleSwitcher;

/**
 * Shows a stored map in a page that other sites embed in a frame.
 */
final class EmbedController extends AbstractController
{
    public function __construct(
        private readonly MapConfigurationRepository $repository,
        private readonly EmbedOptionsParser $optionsParser,
        private readonly LocaleSwitcher $localeSwitcher,
    ) {
    }

    #[Route(
        path: '/embed/leaflet/{id}',
        name: 'app_embed_leaflet',
        requirements: ['id' => Requirement::ULID],
        methods: [Request::METHOD_GET],
        stateless: true,
    )]
    public function leaflet(Request $request, string $id): Response
    {
        $query = $request->query->all();
        $settings = $this->repository->find($id)?->getMapJson(Renderer::Leaflet);

        if (null === $settings) {
            $locale = $this->optionsParser->locale($query);

            return $this->localeSwitcher->runWithLocale(
                $locale,
                fn (): Response => $this->render('embed/not_available.html.twig', ['locale' => $locale], new Response(status: Response::HTTP_NOT_FOUND)),
            );
        }

        $options = $this->optionsParser->parse($query, $settings);

        // The script gets the objects, so that "{}" stays an object. Twig cannot
        // loop over the properties of an object, so the template gets arrays.
        $map = json_decode(json_encode($settings, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        return $this->localeSwitcher->runWithLocale(
            $options->locale,
            fn (): Response => $this->render('embed/leaflet.html.twig', ['settings' => $settings, 'map' => $map, 'options' => $options]),
        );
    }
}
