<?php

namespace App\Controller;

use App\Repository\ActiviteRepository;
use App\Repository\DestinationRepository;
use App\View\PhpTemplateRenderer;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/activites')]
final class ActiviteController extends AbstractController
{
    public function __construct(
        private readonly ActiviteRepository $activiteRepository,
        private readonly DestinationRepository $destinationRepository,
        private readonly PhpTemplateRenderer $renderer,
    ) {
    }

    #[Route('', name: 'app_activite_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $databaseError = null;
        $activites = [];

        try {
            $activites = $this->activiteRepository->findAll();
        } catch (RuntimeException $exception) {
            $databaseError = $exception->getMessage();
        }

        return new Response($this->renderer->render('activite/index', [
            'title' => 'CRUD Activites',
            'showPageHeading' => false,
            'databaseError' => $databaseError,
            'activites' => $activites,
            'statusMessage' => $this->statusMessage($request->query->get('status')),
        ]));
    }

    #[Route('/new', name: 'app_activite_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $payload = $this->extractPayload($request);
        $errors = [];
        $databaseError = null;
        $destinations = [];

        try {
            $destinations = $this->destinationRepository->findForSelect();
        } catch (RuntimeException $exception) {
            $databaseError = $exception->getMessage();
        }

        if ($request->isMethod('POST')) {
            $errors = $this->validatePayload($payload);

            if ($errors === [] && $databaseError === null) {
                try {
                    $this->activiteRepository->create($payload);

                    return $this->redirectToRoute('app_activite_index', ['status' => 'created']);
                } catch (RuntimeException $exception) {
                    $databaseError = $exception->getMessage();
                }
            }
        }

        return new Response($this->renderer->render('activite/form', [
            'title' => 'Nouvelle activite',
            'showPageHeading' => false,
            'databaseError' => $databaseError,
            'errors' => $errors,
            'activite' => $payload,
            'destinations' => $destinations,
            'formTitle' => 'Ajouter une activite',
            'submitLabel' => 'Enregistrer',
            'action' => $this->generateUrl('app_activite_new'),
        ]));
    }

    #[Route('/{id}/edit', name: 'app_activite_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, int $id): Response
    {
        $databaseError = null;
        $destinations = [];

        try {
            $destinations = $this->destinationRepository->findForSelect();
            $activite = $this->activiteRepository->find($id);

            if ($activite === null) {
                throw $this->createNotFoundException('Activite introuvable.');
            }
        } catch (RuntimeException $exception) {
            $activite = null;
            $databaseError = $exception->getMessage();
        }

        if ($activite === null && $databaseError !== null) {
            return new Response($this->renderer->render('activite/form', [
                'title' => 'Modifier une activite',
                'showPageHeading' => false,
                'databaseError' => $databaseError,
                'errors' => [],
                'activite' => $this->extractPayload($request),
                'destinations' => $destinations,
                'formTitle' => 'Modifier une activite',
                'submitLabel' => 'Mettre a jour',
                'action' => $this->generateUrl('app_activite_edit', ['id' => $id]),
            ]));
        }

        $payload = $request->isMethod('POST') ? $this->extractPayload($request) : $activite;
        $errors = [];

        if ($request->isMethod('POST')) {
            $errors = $this->validatePayload($payload);

            if ($errors === []) {
                try {
                    $this->activiteRepository->update($id, $payload);

                    return $this->redirectToRoute('app_activite_index', ['status' => 'updated']);
                } catch (RuntimeException $exception) {
                    $databaseError = $exception->getMessage();
                }
            }
        }

        return new Response($this->renderer->render('activite/form', [
            'title' => 'Modifier une activite',
            'showPageHeading' => false,
            'databaseError' => $databaseError,
            'errors' => $errors,
            'activite' => $payload,
            'destinations' => $destinations,
            'formTitle' => 'Modifier une activite',
            'submitLabel' => 'Mettre a jour',
            'action' => $this->generateUrl('app_activite_edit', ['id' => $id]),
        ]));
    }

    #[Route('/{id}/delete', name: 'app_activite_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(int $id): RedirectResponse
    {
        try {
            $this->activiteRepository->delete($id);

            return $this->redirectToRoute('app_activite_index', ['status' => 'deleted']);
        } catch (RuntimeException) {
            return $this->redirectToRoute('app_activite_index', ['status' => 'db-error']);
        }
    }

    private function extractPayload(Request $request): array
    {
        return [
            'nom' => trim((string) $request->request->get('nom', '')),
            'destination_id' => (int) $request->request->get('destination_id', 0),
            'categorie' => trim((string) $request->request->get('categorie', '')),
            'prix' => (float) str_replace(',', '.', (string) $request->request->get('prix', '0')),
            'duree_heures' => (int) $request->request->get('duree_heures', 0),
            'description' => trim((string) $request->request->get('description', '')),
        ];
    }

    private function validatePayload(array $payload): array
    {
        $errors = [];

        if ($payload['nom'] === '') {
            $errors[] = 'Le nom est obligatoire.';
        }

        if ($payload['destination_id'] <= 0) {
            $errors[] = 'Choisis une destination.';
        }

        if ($payload['categorie'] === '') {
            $errors[] = 'La categorie est obligatoire.';
        }

        if ($payload['prix'] < 0) {
            $errors[] = 'Le prix ne peut pas etre negatif.';
        }

        if ($payload['duree_heures'] <= 0) {
            $errors[] = 'La duree doit etre superieure a zero.';
        }

        return $errors;
    }

    private function statusMessage(?string $status): ?string
    {
        return match ($status) {
            'created' => 'Activite ajoutee avec succes.',
            'updated' => 'Activite mise a jour avec succes.',
            'deleted' => 'Activite supprimee avec succes.',
            'db-error' => 'Operation impossible: verifie la connexion MySQL.',
            default => null,
        };
    }
}
