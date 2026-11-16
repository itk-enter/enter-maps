<?php

declare(strict_types=1);

namespace App\Controller;

use App\Builder\EmbedCode;
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
 * The list of the maps in the builder, with the embed code, delete and restore.
 */
#[Route('/builder', requirements: ['id' => Requirement::ULID])]
final class BuilderController extends AbstractController
{
    public function __construct(
        private readonly MapConfigurationRepository $repository,
        private readonly EmbedCode $embedCode,
        private readonly TranslatorInterface $translator,
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

    #[Route('/{id}/embed', name: 'app_builder_embed', methods: [Request::METHOD_GET])]
    public function embed(MapConfiguration $map): Response
    {
        return $this->render('builder/embed.html.twig', ['map' => $map, 'code' => $this->embedCode->html($map)]);
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
