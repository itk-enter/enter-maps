<?php

declare(strict_types=1);

namespace App\Controller;

use App\Builder\MapConfigurationExporter;
use App\Builder\MapConfigurationImporter;
use App\Entity\MapConfiguration;
use App\Form\ImportMapConfigurationType;
use App\MapConfig\InvalidMapConfigException;
use App\Repository\MapConfigurationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The list of the maps in the builder, with import, export, delete and restore.
 */
#[Route('/builder', requirements: ['id' => Requirement::ULID])]
final class BuilderController extends AbstractController
{
    public function __construct(
        private readonly MapConfigurationRepository $repository,
        private readonly MapConfigurationImporter $importer,
        private readonly MapConfigurationExporter $exporter,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'app_builder', methods: [Request::METHOD_GET, Request::METHOD_POST])]
    public function index(Request $request): Response
    {
        $form = $this->createForm(ImportMapConfigurationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var UploadedFile $file */
            $file = $form->get('file')->getData();

            try {
                $map = $this->importer->import($file->getContent());
                $this->addFlash('success', $this->translator->trans('builder.import.done', ['%title%' => $this->title($map)]));

                return $this->redirectToRoute('app_builder');
            } catch (InvalidMapConfigException $exception) {
                $form->get('file')->addError(new FormError($this->translator->trans('builder.import.not_valid')));
                foreach ($exception->errors as $error) {
                    $form->get('file')->addError(new FormError($error));
                }
            }
        }

        return $this->render('builder/index.html.twig', [
            'form' => $form,
            'maps' => $this->repository->findActive(),
            'deleted' => $this->repository->findDeleted(),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    #[Route('/{id}/export', name: 'app_builder_export', methods: [Request::METHOD_GET])]
    public function export(MapConfiguration $map): Response
    {
        return new Response($this->exporter->json($map), headers: [
            'Content-Type' => 'application/json',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $this->exporter->filename($map)),
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
    public function restore(Request $request, string $id): Response
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
