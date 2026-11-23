<?php

declare(strict_types=1);

namespace App\Controller;

use App\Builder\EmbedCode;
use App\Builder\PreviewStore;
use App\Embed\LeafletPage;
use App\Entity\MapConfiguration;
use App\Repository\MapConfigurationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The pages of the builder: the list of the maps, the form, the preview, the
 * embed code, delete and restore.
 */
#[Route('/builder', requirements: ['id' => Requirement::ULID])]
final class BuilderController extends AbstractController
{
    public function __construct(
        private readonly MapConfigurationRepository $repository,
        private readonly EmbedCode $embedCode,
        private readonly TranslatorInterface $translator,
        private readonly PreviewStore $previews,
        private readonly LeafletPage $leafletPage,
    ) {
    }

    #[Route('', name: 'app_builder', methods: [Request::METHOD_GET])]
    public function index(): Response
    {
        return $this->render('builder/index.html.twig', [
            'maps' => $this->repository->findActive(),
            'deleted' => $this->repository->findDeleted(),
        ]);
    }

    #[Route('/new', name: 'app_builder_new', methods: [Request::METHOD_GET])]
    public function new(): Response
    {
        return $this->render('builder/form.html.twig', ['map' => null]);
    }

    #[Route('/{id}/edit', name: 'app_builder_edit', methods: [Request::METHOD_GET])]
    public function edit(MapConfiguration $map): Response
    {
        return $this->render('builder/form.html.twig', ['map' => $map]);
    }

    #[Route('/{id}/preview', name: 'app_builder_preview', methods: [Request::METHOD_GET])]
    public function preview(MapConfiguration $map): Response
    {
        return $this->render('builder/preview.html.twig', ['map' => $map, 'code' => $this->embedCode->html($map)]);
    }

    /**
     * The map from the form, without a save. See MapEditor::preview().
     */
    #[Route('/preview/{token}', name: 'app_builder_draft', requirements: ['token' => '[0-9a-f]{32}'], methods: [Request::METHOD_GET])]
    public function draft(Request $request, string $token): Response
    {
        $settings = $this->previews->get($token) ?? throw $this->createNotFoundException();

        return $this->leafletPage->render($settings, $request->query->all());
    }

    #[Route('/{id}/embed', name: 'app_builder_embed', methods: [Request::METHOD_GET])]
    public function embed(MapConfiguration $map): Response
    {
        return $this->render('builder/embed.html.twig', [
            'map' => $map,
            'script' => $this->embedCode->script($map),
            'code' => $this->embedCode->html($map),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_builder_delete', methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function delete(Request $request, MapConfiguration $map): Response
    {
        if (!$request->isMethod(Request::METHOD_POST)) {
            return $this->render('builder/delete.html.twig', ['map' => $map]);
        }

        if (!$this->isCsrfTokenValid('delete-map', $request->getPayload()->getString('_token'))) {
            return $this->expired();
        }

        $this->repository->delete($map);
        $this->addFlash('success', $this->translator->trans('builder.delete.done', ['%title%' => $this->title($map)]));

        return $this->redirectToRoute('app_builder');
    }

    #[Route('/{id}/restore', name: 'app_builder_restore', methods: [Request::METHOD_POST])]
    public function restore(Request $request, string $id): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('restore-map', $request->getPayload()->getString('_token'))) {
            return $this->expired();
        }

        $map = $this->repository->findDeletedById($id) ?? throw $this->createNotFoundException();

        $this->repository->restore($map);
        $this->addFlash('success', $this->translator->trans('builder.restore.done', ['%title%' => $this->title($map)]));

        return $this->redirectToRoute('app_builder');
    }

    /**
     * The CSRF token is not valid, usually because the session expired. The
     * user tries again from the list.
     */
    private function expired(): RedirectResponse
    {
        $this->addFlash('error', $this->translator->trans('builder.expired'));

        return $this->redirectToRoute('app_builder');
    }

    private function title(MapConfiguration $map): string
    {
        $title = $map->getConfig()->title ?? null;

        return \is_string($title) ? $title : (string) $map->getId();
    }
}
