<?php

declare(strict_types=1);

namespace App\Controller;

use App\Builder\EmbedCode;
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

    /**
     * The embed script, which replaces a div with the frame of the map and
     * sets the height of the frame. See EmbedCode::script().
     *
     * The address does not change, because the embed codes on other sites
     * load it. A browser keeps it for 5 minutes, so a deploy reaches the
     * sites soon.
     */
    #[Route(path: '/embed/v1.js', name: 'app_embed_script', methods: [Request::METHOD_GET], stateless: true)]
    public function script(Request $request): Response
    {
        $response = $this->render('embed/v1.js.twig', [
            'origin' => $request->getSchemeAndHttpHost(),
            'height' => EmbedCode::HEIGHT,
        ]);
        $response->headers->set('Content-Type', 'text/javascript; charset=UTF-8');
        $response->setPublic();
        $response->setMaxAge(300);
        // A site can load the script with "crossorigin" or "integrity". Then the
        // browser needs this header. The script is public, so all sites get it.
        $response->headers->set('Access-Control-Allow-Origin', '*');

        return $response;
    }
}
