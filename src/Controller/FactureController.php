<?php

namespace App\Controller;

use App\Repository\FactureRepository;
use App\Repository\PaiementRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class FactureController extends AbstractController
{
    public function __construct(
        private readonly FactureRepository $factureRepository,
        private readonly PaiementRepository $paiementRepository
    ) {
    }

    #[Route('/factures', name: 'facture_index', methods: ['GET'])]
    public function index(): Response
    {
        // Charger tous les paiements payés pour le ComboBox
        $paiements = $this->paiementRepository->findPaidPayments();
        
        // Formater les paiements pour l'affichage
        $paiementsFormatted = array_map(function ($paiement) {
            return [
                'id' => $paiement['id'],
                'label' => $paiement['client_nom'] . ' - ' . $paiement['destination'] . ' (' . number_format($paiement['montant'], 2) . ' €)',
                'client_nom' => $paiement['client_nom'],
                'destination' => $paiement['destination'],
                'montant' => $paiement['montant'],
                'type_voyage' => $paiement['type_voyage'] ?? 'Aventure',
            ];
        }, $paiements);

        return $this->render('facture/create.html.twig', [
            'paiements' => $paiementsFormatted,
            'message' => '',
            'messageType' => '',
        ]);
    }

    #[Route('/factures/generer', name: 'facture_generer', methods: ['POST'])]
    public function generer(Request $request): Response
    {
        $data = [
            'client_nom' => trim($request->request->get('client_nom', '')),
            'client_email' => trim($request->request->get('client_email', '')),
            'client_adresse' => trim($request->request->get('client_adresse', '')),
            'destination' => trim($request->request->get('destination', '')),
            'date_debut' => trim($request->request->get('date_debut', '')),
            'date_fin' => trim($request->request->get('date_fin', '')),
            'nb_personnes' => max(1, (int) $request->request->get('nb_personnes', 1)),
            'montant_transport' => (float) $request->request->get('montant_transport', 0),
            'montant_hebergement' => (float) $request->request->get('montant_hebergement', 0),
            'montant_activites' => (float) $request->request->get('montant_activites', 0),
            'montant_total' => (float) $request->request->get('montant_total', 0),
            'paiement_id' => (int) $request->request->get('paiement_id', 0),
            'type_voyage' => trim($request->request->get('type_voyage', '')),
            'statut' => 'GENEREE',
            'date_emission' => date('Y-m-d'),
        ];

        // Validation
        if (empty($data['client_nom']) || empty($data['client_email']) || empty($data['destination'])) {
            return $this->redirectToRoute('facture_index');
        }

        // Créer la facture
        $factureId = $this->factureRepository->create($data);
        $facture = $this->factureRepository->find($factureId);

        // Rediriger vers la prévisualisation
        return $this->redirectToRoute('facture_preview', ['id' => $factureId]);
    }

    #[Route('/factures/previsualiser', name: 'facture_previsualiser', methods: ['POST'])]
    public function previsualiser(Request $request): Response
    {
        // Créer une facture temporaire pour la prévisualisation
        $facture = [
            'id' => 0,
            'numero_facture' => 'PREVIEW-' . date('YmdHis'),
            'client_nom' => trim($request->request->get('client_nom', '')),
            'client_email' => trim($request->request->get('client_email', '')),
            'client_adresse' => trim($request->request->get('client_adresse', '')),
            'destination' => trim($request->request->get('destination', '')),
            'date_debut' => trim($request->request->get('date_debut', '')),
            'date_fin' => trim($request->request->get('date_fin', '')),
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
        ]);
    }

    #[Route('/factures/{id}/preview', name: 'facture_preview', methods: ['GET'])]
    public function preview(int $id): Response
    {
        $facture = $this->factureRepository->find($id);

        if (!$facture) {
            return $this->redirectToRoute('facture_index');
        }

        return $this->render('facture/preview.html.twig', [
            'facture' => $facture,
            'isPreview' => false,
            'isAdmin' => false, // Client view - no send button
        ]);
    }

    #[Route('/factures/{id}/envoyer', name: 'facture_envoyer', methods: ['POST'])]
    public function envoyer(int $id): Response
    {
        $facture = $this->factureRepository->find($id);

        if (!$facture) {
            return $this->redirectToRoute('facture_index');
        }

        // Mettre à jour le statut
        $this->factureRepository->update($id, [
            ...$facture,
            'statut' => 'ENVOYEE',
        ]);

        // TODO: Envoyer l'email au client
        // $this->emailService->sendInvoice($facture);

        $this->addFlash('success', '✅ Facture envoyée au client ' . $facture['client_nom']);

        return $this->redirectToRoute('facture_preview', ['id' => $id]);
    }

    #[Route('/factures/retour-admin', name: 'facture_retour_admin', methods: ['GET'])]
    public function retourAdmin(): Response
    {
        return $this->redirectToRoute('admin_dashboard', ['section' => 'paiements']);
    }
}
