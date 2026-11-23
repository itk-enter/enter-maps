<?php

declare(strict_types=1);

namespace App\Controller;

use App\Embed\LeafletPage;
use App\Renderer\Renderer;
use App\Repository\MapConfigurationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;

/**
 * Shows a stored map in a page that other sites embed in a frame.
 */
final class EmbedController extends AbstractController
{
    public function __construct(
        private readonly MapConfigurationRepository $repository,
        private readonly LeafletPage $page,
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

        return null === $settings ? $this->page->notAvailable($query) : $this->page->render($settings, $query);
    }
}
