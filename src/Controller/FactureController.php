<?php

namespace App\Controller;

use App\Repository\FactureRepository;
use App\Repository\PaiementRepository;
use App\Service\InvoiceDeliveryService;
use App\Validation\LegacyValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class FactureController extends AbstractController
{
    public function __construct(
        private readonly FactureRepository $factureRepository,
        private readonly PaiementRepository $paiementRepository,
        private readonly InvoiceDeliveryService $invoiceDeliveryService,
    ) {
    }

    #[Route('/factures', name: 'facture_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $flashBag = $request->getSession()->getFlashBag();
        $hasError = $flashBag->peek('error') !== [];
        $message = $this->consumeFlash($request, 'success')
            ?? $this->consumeFlash($request, 'info')
            ?? $this->consumeFlash($request, 'error')
            ?? '';

        $paiements = $this->paiementRepository->findPaidPayments();
        $paiementsFormatted = array_map(function (array $paiement): array {
            return [
                'id' => $paiement['id'],
                'label' => $paiement['client_nom'].' - '.$paiement['destination'].' ('.number_format((float) $paiement['montant'], 2, '.', ' ').' EUR)',
                'client_nom' => $paiement['client_nom'],
                'destination' => $paiement['destination'],
                'montant' => $paiement['montant'],
                'type_voyage' => $paiement['type_voyage'] ?? 'Aventure',
            ];
        }, $paiements);

        return $this->render('facture/create.html.twig', [
            'paiements' => $paiementsFormatted,
            'message' => $message,
            'messageType' => $hasError ? 'error' : 'success',
        ]);
    }

    #[Route('/factures/generer', name: 'facture_generer', methods: ['POST'])]
    public function generer(Request $request): Response
    {
        $data = [
            'client_nom' => trim((string) $request->request->get('client_nom', '')),
            'client_email' => trim((string) $request->request->get('client_email', '')),
            'client_adresse' => trim((string) $request->request->get('client_adresse', '')),
            'destination' => trim((string) $request->request->get('destination', '')),
            'date_debut' => trim((string) $request->request->get('date_debut', '')),
            'date_fin' => trim((string) $request->request->get('date_fin', '')),
            'nb_personnes' => max(1, (int) $request->request->get('nb_personnes', 1)),
            'montant_transport' => (float) $request->request->get('montant_transport', 0),
            'montant_hebergement' => (float) $request->request->get('montant_hebergement', 0),
            'montant_activites' => (float) $request->request->get('montant_activites', 0),
            'montant_total' => (float) $request->request->get('montant_total', 0),
            'paiement_id' => (int) $request->request->get('paiement_id', 0),
            'type_voyage' => trim((string) $request->request->get('type_voyage', '')),
            'statut' => 'GENEREE',
            'date_emission' => date('Y-m-d'),
        ];
        $intent = trim((string) $request->request->get('intent', 'save'));

        if (
            $data['client_nom'] === ''
            || $data['destination'] === ''
            || !LegacyValidator::isValidEmail($data['client_email'])
        ) {
            $this->addFlash('error', 'Veuillez remplir correctement le nom, la destination et l email du client.');

            return $this->redirectToRoute('facture_index');
        }

        if ($intent === 'send') {
            $data['statut'] = 'ENVOYEE';
        }

        $factureId = $this->factureRepository->create($data);
        $facture = $this->factureRepository->find($factureId);

        if ($intent === 'send' && $facture !== null) {
            $result = $this->invoiceDeliveryService->deliver($facture);
            $this->addFlash($result['ok'] ? 'success' : 'error', $result['message']);
        } else {
            $this->addFlash('success', 'Facture generee avec succes.');
        }

        return $this->redirectToRoute('facture_preview', ['id' => $factureId]);
    }

    #[Route('/factures/previsualiser', name: 'facture_previsualiser', methods: ['POST'])]
    public function previsualiser(Request $request): Response
    {
        $facture = [
            'id' => 0,
            'numero_facture' => 'PREVIEW-'.date('YmdHis'),
            'client_nom' => trim((string) $request->request->get('client_nom', '')),
            'client_email' => trim((string) $request->request->get('client_email', '')),
            'client_adresse' => trim((string) $request->request->get('client_adresse', '')),
            'destination' => trim((string) $request->request->get('destination', '')),
            'date_debut' => trim((string) $request->request->get('date_debut', '')),
            'date_fin' => trim((string) $request->request->get('date_fin', '')),
            'nb_personnes' => max(1, (int) $request->request->get('nb_personnes', 1)),
            'montant_transport' => (float) $request->request->get('montant_transport', 0),
            'montant_hebergement' => (float) $request->request->get('montant_hebergement', 0),
            'montant_activites' => (float) $request->request->get('montant_activites', 0),
            'montant_total' => (float) $request->request->get('montant_total', 0),
            'date_emission' => date('Y-m-d'),
            'statut' => 'PREVIEW',
        ];

        return $this->render('facture/preview.html.twig', [
            'facture' => $facture,
            'isPreview' => true,
            'canSend' => false,
            'invoiceMailtoUrl' => $this->invoiceDeliveryService->buildMailtoUrl($facture),
            'statusMessage' => null,
            'errorMessage' => null,
        ]);
    }

    #[Route('/factures/{id}/preview', name: 'facture_preview', methods: ['GET'])]
    public function preview(int $id, Request $request): Response
    {
        $facture = $this->factureRepository->find($id);

        if (!$facture) {
            return $this->redirectToRoute('facture_index');
        }

        return $this->render('facture/preview.html.twig', [
            'facture' => $facture,
            'isPreview' => false,
            'canSend' => strtoupper(trim((string) ($facture['statut'] ?? 'GENEREE'))) !== 'ENVOYEE',
            'invoiceMailtoUrl' => $this->invoiceDeliveryService->buildMailtoUrl($facture),
            'statusMessage' => $this->consumeFlash($request, 'success') ?? $this->consumeFlash($request, 'info'),
            'errorMessage' => $this->consumeFlash($request, 'error'),
        ]);
    }

    #[Route('/factures/{id}/envoyer', name: 'facture_envoyer', methods: ['POST'])]
    public function envoyer(int $id): Response
    {
        $facture = $this->factureRepository->find($id);

        if (!$facture) {
            $this->addFlash('error', 'Facture introuvable.');

            return $this->redirectToRoute('facture_index');
        }

        $result = $this->invoiceDeliveryService->deliver($facture);
        $this->addFlash($result['ok'] ? 'success' : 'error', $result['message']);

        return $this->redirectToRoute('facture_preview', ['id' => $id]);
    }

    #[Route('/factures/retour-admin', name: 'facture_retour_admin', methods: ['GET'])]
    public function retourAdmin(): Response
    {
        return $this->redirectToRoute('admin_dashboard', ['section' => 'paiements']);
    }

    private function consumeFlash(Request $request, string $type): ?string
    {
        $messages = $request->getSession()->getFlashBag()->get($type, []);

        return $messages[0] ?? null;
    }
}
