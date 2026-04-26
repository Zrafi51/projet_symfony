<?php

namespace App\Controller;

use App\Repository\FactureRepository;
use App\Repository\PaiementRepository;
use App\View\PhpTemplateRenderer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class BillingController extends AbstractController
{
    public function __construct(
        private readonly PhpTemplateRenderer $renderer,
        private readonly PaiementRepository $paiementRepository,
        private readonly FactureRepository $factureRepository,
    ) {
    }

    #[Route('/paiement', name: 'app_paiement', methods: ['GET'])]
    public function paymentPage(Request $request): Response
    {
        $currentUser = $this->getAuthenticatedUser($request);
        $savedPayment = null;
        $reference = trim((string) $request->query->get('reference', ''));
        if ($reference !== '') {
            $savedPayment = $this->paiementRepository->findByReference($reference);
        }

        return new Response($this->renderer->render('paiement/index', [
            'title' => 'Paiement securise',
            'showPageHeading' => false,
            'stylesheets' => ['/billing.css'],
            'currentUser' => $currentUser,
            'savedPayment' => $savedPayment,
            'formData' => $this->buildClientPaymentFormData($request, $currentUser),
        ]));
    }

    #[Route('/paiement', name: 'app_paiement_submit', methods: ['POST'])]
    public function submitPayment(Request $request): Response
    {
        $currentUser = $this->getAuthenticatedUser($request);
        $formData = $this->extractClientPaymentFormData($request, $currentUser);
        $errorMessage = $this->validateClientPaymentForm($formData);

        if ($errorMessage !== null) {
            return new Response($this->renderer->render('paiement/index', [
                'title' => 'Paiement securise',
                'showPageHeading' => false,
                'stylesheets' => ['/billing.css'],
                'currentUser' => $currentUser,
                'formData' => $formData,
                'errorMessage' => $errorMessage,
            ]));
        }

        $paymentId = $this->paiementRepository->create([
            'client_nom' => $formData['client_nom'],
            'destination' => $formData['destination'],
            'montant' => $formData['montant'],
            'date_paiement' => date('Y-m-d H:i:s'),
            'statut' => 'PAYE',
            'package_id' => $formData['package_id'],
            'numero_carte_masque' => $this->maskCardNumber($formData['numero_carte']),
            'type_voyage' => $formData['type_voyage'],
        ]);

        $savedPayment = $this->paiementRepository->find($paymentId);
        $reference = (string) ($savedPayment['reference_transaction'] ?? '');

        return $this->redirectToRoute('app_paiement', [
            'reference' => $reference,
            'destination' => $formData['destination'],
            'montant' => $formData['montant'],
            'package_id' => $formData['package_id'],
            'type_voyage' => $formData['type_voyage'],
        ]);
    }

    #[Route('/admin/paiements', name: 'app_admin_paiements', methods: ['GET'])]
    public function adminPayments(Request $request): Response
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        return $this->renderAdminPaymentsPage($request);
    }

    #[Route('/admin/paiements/save', name: 'app_admin_paiements_save', methods: ['POST'])]
    public function saveAdminPayment(Request $request): Response
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $formData = $this->extractAdminPaymentFormData($request);
        $paymentId = (int) ($formData['id'] ?? 0);
        $errorMessage = $this->validateAdminPaymentForm($formData);

        if ($errorMessage !== null) {
            return $this->renderAdminPaymentsPage($request, $formData, null, $errorMessage);
        }

        if ($paymentId > 0) {
            $this->paiementRepository->update($paymentId, $formData);
            $request->getSession()->getFlashBag()->add('success', 'Paiement mis a jour avec succes.');
        } else {
            $paymentId = $this->paiementRepository->create($formData);
            $request->getSession()->getFlashBag()->add('success', 'Paiement cree avec succes.');
        }

        return $this->redirectToRoute('app_admin_paiements', [
            'edit' => $paymentId,
        ]);
    }

    #[Route('/admin/paiements/{id}/delete', name: 'app_admin_paiements_delete', methods: ['POST'])]
    public function deleteAdminPayment(int $id, Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $this->paiementRepository->delete($id);
        $request->getSession()->getFlashBag()->add('success', 'Paiement supprime avec succes.');

        return $this->redirectToRoute('app_admin_paiements');
    }

    #[Route('/admin/factures', name: 'app_admin_factures', methods: ['GET'])]
    public function adminInvoices(Request $request): Response
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        return $this->renderAdminInvoicesPage($request);
    }

    #[Route('/admin/factures/preview', name: 'app_admin_factures_preview', methods: ['POST'])]
    public function previewInvoice(Request $request): Response
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $formData = $this->extractInvoiceFormData($request);
        $formData = $this->hydrateInvoiceFormWithPayment($formData);
        $formData['montant_total'] = $this->computeInvoiceTotal($formData);
        $errorMessage = $this->validateInvoiceForm($formData);

        if ($errorMessage !== null) {
            return $this->renderAdminInvoicesPage($request, $formData, null, $errorMessage);
        }

        return $this->renderInvoicePreview($request, $formData, false);
    }

    #[Route('/admin/factures/save', name: 'app_admin_factures_save', methods: ['POST'])]
    public function saveInvoice(Request $request): Response
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $formData = $this->extractInvoiceFormData($request);
        $formData = $this->hydrateInvoiceFormWithPayment($formData);
        $formData['montant_total'] = $this->computeInvoiceTotal($formData);
        $errorMessage = $this->validateInvoiceForm($formData);

        if ($errorMessage !== null) {
            return $this->renderAdminInvoicesPage($request, $formData, null, $errorMessage);
        }

        $intent = trim((string) $request->request->get('intent', 'save'));
        if ($intent === 'send') {
            $formData['statut'] = 'ENVOYEE';
        } elseif (trim((string) ($formData['statut'] ?? '')) === '') {
            $formData['statut'] = 'GENEREE';
        }

        $invoiceId = (int) ($formData['id'] ?? 0);
        if ($invoiceId > 0) {
            $this->factureRepository->update($invoiceId, $formData);
            $request->getSession()->getFlashBag()->add('success', $intent === 'send' ? 'Facture envoyee au client.' : 'Facture mise a jour.');
        } else {
            $invoiceId = $this->factureRepository->create($formData);
            $request->getSession()->getFlashBag()->add('success', $intent === 'send' ? 'Facture creee et marquee comme envoyee.' : 'Facture generee avec succes.');
        }

        return $this->redirectToRoute('app_admin_factures_show', ['id' => $invoiceId]);
    }

    #[Route('/admin/factures/{id}/preview', name: 'app_admin_factures_show', methods: ['GET'])]
    public function showInvoice(int $id, Request $request): Response
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $invoice = $this->factureRepository->find($id);
        if ($invoice === null) {
            $request->getSession()->getFlashBag()->add('error', 'Facture introuvable.');

            return $this->redirectToRoute('app_admin_factures');
        }

        return $this->renderInvoicePreview($request, $this->buildInvoiceFormDataFromRecord($invoice), true);
    }

    #[Route('/admin/factures/{id}/delete', name: 'app_admin_factures_delete', methods: ['POST'])]
    public function deleteInvoice(int $id, Request $request): RedirectResponse
    {
        if ($redirect = $this->ensureAdminAccess($request)) {
            return $redirect;
        }

        $this->factureRepository->delete($id);
        $request->getSession()->getFlashBag()->add('success', 'Facture supprimee avec succes.');

        return $this->redirectToRoute('app_admin_factures');
    }

    private function renderAdminPaymentsPage(
        Request $request,
        array $formData = [],
        ?string $statusMessage = null,
        ?string $errorMessage = null,
    ): Response {
        $search = trim((string) $request->query->get('q', ''));
        $editId = max(0, (int) $request->query->get('edit', 0));
        $paymentToEdit = $editId > 0 ? $this->paiementRepository->find($editId) : null;
        if ($formData === []) {
            $formData = $paymentToEdit !== null
                ? $this->buildAdminPaymentFormDataFromRecord($paymentToEdit)
                : $this->buildAdminPaymentDefaultFormData();
        }

        return new Response($this->renderer->render('admin/paiements', [
            'layout' => 'admin-layout',
            'pageClass' => 'admin-page-body',
            'stylesheets' => ['/billing.css'],
            'title' => 'Gestion des paiements',
            'formData' => $formData,
            'payments' => $this->paiementRepository->findAll($search),
            'stats' => $this->paiementRepository->getStats(),
            'search' => $search,
            'editingPayment' => $paymentToEdit,
            'statusMessage' => $statusMessage ?? $this->consumeFlash($request, 'success') ?? $this->consumeFlash($request, 'info'),
            'errorMessage' => $errorMessage ?? $this->consumeFlash($request, 'error'),
        ]));
    }

    private function renderAdminInvoicesPage(
        Request $request,
        array $formData = [],
        ?string $statusMessage = null,
        ?string $errorMessage = null,
    ): Response {
        $search = trim((string) $request->query->get('q', ''));
        $editId = max(0, (int) $request->query->get('edit', 0));
        $paymentId = max(0, (int) $request->query->get('payment_id', 0));
        $invoiceToEdit = $editId > 0 ? $this->factureRepository->find($editId) : null;
        $selectedPayment = $paymentId > 0 ? $this->paiementRepository->find($paymentId) : null;

        if ($formData === []) {
            $formData = $invoiceToEdit !== null
                ? $this->buildInvoiceFormDataFromRecord($invoiceToEdit)
                : $this->buildAdminInvoiceDefaultFormData($selectedPayment);
        }

        return new Response($this->renderer->render('admin/factures', [
            'layout' => 'admin-layout',
            'pageClass' => 'admin-page-body',
            'stylesheets' => ['/billing.css'],
            'title' => 'Generation de facture',
            'formData' => $formData,
            'factures' => $this->factureRepository->findAll($search),
            'paiements' => $this->paiementRepository->findPaidPayments(),
            'stats' => $this->factureRepository->getStats(),
            'search' => $search,
            'editingInvoice' => $invoiceToEdit,
            'selectedPayment' => $selectedPayment,
            'statusMessage' => $statusMessage ?? $this->consumeFlash($request, 'success') ?? $this->consumeFlash($request, 'info'),
            'errorMessage' => $errorMessage ?? $this->consumeFlash($request, 'error'),
        ]));
    }

    private function renderInvoicePreview(Request $request, array $formData, bool $isPersisted): Response
    {
        return new Response($this->renderer->render('admin/facture_preview', [
            'layout' => 'admin-layout',
            'pageClass' => 'admin-page-body',
            'stylesheets' => ['/billing.css'],
            'title' => 'Previsualisation facture',
            'facture' => $formData,
            'previewPayload' => $formData,
            'isPersisted' => $isPersisted,
            'statusMessage' => $this->consumeFlash($request, 'success') ?? $this->consumeFlash($request, 'info'),
            'errorMessage' => $this->consumeFlash($request, 'error'),
        ]));
    }

    private function buildClientPaymentFormData(Request $request, ?array $currentUser): array
    {
        $displayName = trim((string) ($currentUser['display_name'] ?? ''));
        if ($displayName === '') {
            $displayName = trim((string) (($currentUser['prenom'] ?? '').' '.($currentUser['nom'] ?? '')));
        }

        return [
            'client_nom' => trim((string) $request->query->get('client_nom', $displayName)),
            'client_email' => trim((string) $request->query->get('client_email', (string) ($currentUser['email'] ?? ''))),
            'destination' => trim((string) $request->query->get('destination', 'Paris, France')),
            'montant' => (float) $request->query->get('montant', 1200),
            'package_id' => max(0, (int) $request->query->get('package_id', 0)),
            'type_voyage' => trim((string) $request->query->get('type_voyage', 'Aventure')),
            'numero_carte' => '',
            'nom_titulaire' => '',
            'expiration' => '',
            'cvv' => '',
        ];
    }

    private function extractClientPaymentFormData(Request $request, ?array $currentUser): array
    {
        $queryDefaults = $this->buildClientPaymentFormData($request, $currentUser);

        return [
            'client_nom' => trim((string) $request->request->get('client_nom', $queryDefaults['client_nom'])),
            'client_email' => trim((string) $request->request->get('client_email', $queryDefaults['client_email'])),
            'destination' => trim((string) $request->request->get('destination', $queryDefaults['destination'])),
            'montant' => round((float) $request->request->get('montant', $queryDefaults['montant']), 2),
            'package_id' => max(0, (int) $request->request->get('package_id', $queryDefaults['package_id'])),
            'type_voyage' => trim((string) $request->request->get('type_voyage', $queryDefaults['type_voyage'])),
            'numero_carte' => $this->sanitizeDigits((string) $request->request->get('numero_carte', ''), 19),
            'nom_titulaire' => $this->normalizeHolderName((string) $request->request->get('nom_titulaire', '')),
            'expiration' => trim((string) $request->request->get('expiration', '')),
            'cvv' => $this->sanitizeDigits((string) $request->request->get('cvv', ''), 4),
        ];
    }

    private function buildAdminPaymentDefaultFormData(): array
    {
        return [
            'id' => 0,
            'client_nom' => '',
            'destination' => '',
            'montant' => 0.0,
            'date_paiement' => date('Y-m-d\TH:i'),
            'statut' => 'PAYE',
            'reference_transaction' => '',
            'package_id' => 0,
            'numero_carte_masque' => '',
            'type_voyage' => 'Aventure',
        ];
    }

    private function buildAdminPaymentFormDataFromRecord(array $payment): array
    {
        return [
            'id' => (int) ($payment['id'] ?? 0),
            'client_nom' => (string) ($payment['client_nom'] ?? ''),
            'destination' => (string) ($payment['destination'] ?? ''),
            'montant' => round((float) ($payment['montant'] ?? 0), 2),
            'date_paiement' => $this->formatDateTimeLocal((string) ($payment['date_paiement'] ?? '')),
            'statut' => (string) ($payment['statut'] ?? 'PAYE'),
            'reference_transaction' => (string) ($payment['reference_transaction'] ?? ''),
            'package_id' => (int) ($payment['package_id'] ?? 0),
            'numero_carte_masque' => (string) ($payment['numero_carte_masque'] ?? ''),
            'type_voyage' => (string) ($payment['type_voyage'] ?? 'Aventure'),
        ];
    }

    private function extractAdminPaymentFormData(Request $request): array
    {
        return [
            'id' => max(0, (int) $request->request->get('id', 0)),
            'client_nom' => trim((string) $request->request->get('client_nom', '')),
            'destination' => trim((string) $request->request->get('destination', '')),
            'montant' => round((float) $request->request->get('montant', 0), 2),
            'date_paiement' => $this->normalizeDateTimeInput((string) $request->request->get('date_paiement', '')),
            'statut' => strtoupper(trim((string) $request->request->get('statut', 'PAYE'))),
            'reference_transaction' => trim((string) $request->request->get('reference_transaction', '')),
            'package_id' => max(0, (int) $request->request->get('package_id', 0)),
            'numero_carte_masque' => trim((string) $request->request->get('numero_carte_masque', '')),
            'type_voyage' => trim((string) $request->request->get('type_voyage', 'Aventure')),
        ];
    }

    private function buildAdminInvoiceDefaultFormData(?array $selectedPayment): array
    {
        $payment = $selectedPayment ?? [];
        $split = $this->splitInvoiceAmounts((float) ($payment['montant'] ?? 0));

        return [
            'id' => 0,
            'paiement_id' => (int) ($payment['id'] ?? 0),
            'numero_facture' => '',
            'date_emission' => date('Y-m-d'),
            'client_nom' => (string) ($payment['client_nom'] ?? ''),
            'client_email' => '',
            'client_adresse' => '',
            'destination' => (string) ($payment['destination'] ?? ''),
            'date_debut' => '',
            'date_fin' => '',
            'nb_personnes' => 1,
            'montant_transport' => $split['transport'],
            'montant_hebergement' => $split['hebergement'],
            'montant_activites' => $split['activites'],
            'montant_total' => $split['total'],
            'statut' => 'GENEREE',
            'type_voyage' => (string) ($payment['type_voyage'] ?? 'Aventure'),
        ];
    }

    private function buildInvoiceFormDataFromRecord(array $invoice): array
    {
        return [
            'id' => (int) ($invoice['id'] ?? 0),
            'paiement_id' => (int) ($invoice['paiement_id'] ?? 0),
            'numero_facture' => (string) ($invoice['numero_facture'] ?? ''),
            'date_emission' => (string) ($invoice['date_emission'] ?? date('Y-m-d')),
            'client_nom' => (string) ($invoice['client_nom'] ?? ''),
            'client_email' => (string) ($invoice['client_email'] ?? ''),
            'client_adresse' => (string) ($invoice['client_adresse'] ?? ''),
            'destination' => (string) ($invoice['destination'] ?? ''),
            'date_debut' => (string) ($invoice['date_debut'] ?? ''),
            'date_fin' => (string) ($invoice['date_fin'] ?? ''),
            'nb_personnes' => max(1, (int) ($invoice['nb_personnes'] ?? 1)),
            'montant_transport' => round((float) ($invoice['montant_transport'] ?? 0), 2),
            'montant_hebergement' => round((float) ($invoice['montant_hebergement'] ?? 0), 2),
            'montant_activites' => round((float) ($invoice['montant_activites'] ?? 0), 2),
            'montant_total' => round((float) ($invoice['montant_total'] ?? 0), 2),
            'statut' => (string) ($invoice['statut'] ?? 'GENEREE'),
            'type_voyage' => (string) ($invoice['type_voyage'] ?? ''),
        ];
    }

    private function extractInvoiceFormData(Request $request): array
    {
        return [
            'id' => max(0, (int) $request->request->get('id', 0)),
            'paiement_id' => max(0, (int) $request->request->get('paiement_id', 0)),
            'numero_facture' => trim((string) $request->request->get('numero_facture', '')),
            'date_emission' => trim((string) $request->request->get('date_emission', date('Y-m-d'))),
            'client_nom' => trim((string) $request->request->get('client_nom', '')),
            'client_email' => trim((string) $request->request->get('client_email', '')),
            'client_adresse' => trim((string) $request->request->get('client_adresse', '')),
            'destination' => trim((string) $request->request->get('destination', '')),
            'date_debut' => trim((string) $request->request->get('date_debut', '')),
            'date_fin' => trim((string) $request->request->get('date_fin', '')),
            'nb_personnes' => max(1, (int) $request->request->get('nb_personnes', 1)),
            'montant_transport' => round((float) $request->request->get('montant_transport', 0), 2),
            'montant_hebergement' => round((float) $request->request->get('montant_hebergement', 0), 2),
            'montant_activites' => round((float) $request->request->get('montant_activites', 0), 2),
            'montant_total' => round((float) $request->request->get('montant_total', 0), 2),
            'statut' => strtoupper(trim((string) $request->request->get('statut', 'GENEREE'))),
            'type_voyage' => trim((string) $request->request->get('type_voyage', '')),
        ];
    }

    private function hydrateInvoiceFormWithPayment(array $formData): array
    {
        $paymentId = (int) ($formData['paiement_id'] ?? 0);
        if ($paymentId <= 0) {
            return $formData;
        }

        $payment = $this->paiementRepository->find($paymentId);
        if ($payment === null) {
            return $formData;
        }

        $split = $this->splitInvoiceAmounts((float) ($payment['montant'] ?? 0));

        if (trim((string) ($formData['client_nom'] ?? '')) === '') {
            $formData['client_nom'] = (string) ($payment['client_nom'] ?? '');
        }
        if (trim((string) ($formData['destination'] ?? '')) === '') {
            $formData['destination'] = (string) ($payment['destination'] ?? '');
        }
        if (trim((string) ($formData['type_voyage'] ?? '')) === '') {
            $formData['type_voyage'] = (string) ($payment['type_voyage'] ?? 'Aventure');
        }
        if ((float) ($formData['montant_transport'] ?? 0) <= 0) {
            $formData['montant_transport'] = $split['transport'];
        }
        if ((float) ($formData['montant_hebergement'] ?? 0) <= 0) {
            $formData['montant_hebergement'] = $split['hebergement'];
        }
        if ((float) ($formData['montant_activites'] ?? 0) <= 0) {
            $formData['montant_activites'] = $split['activites'];
        }

        return $formData;
    }

    private function splitInvoiceAmounts(float $total): array
    {
        $transport = round($total * 0.40, 2);
        $hebergement = round($total * 0.45, 2);
        $activites = round(max(0, $total - $transport - $hebergement), 2);

        return [
            'transport' => $transport,
            'hebergement' => $hebergement,
            'activites' => $activites,
            'total' => round($transport + $hebergement + $activites, 2),
        ];
    }

    private function computeInvoiceTotal(array $formData): float
    {
        return round(
            (float) ($formData['montant_transport'] ?? 0)
            + (float) ($formData['montant_hebergement'] ?? 0)
            + (float) ($formData['montant_activites'] ?? 0),
            2
        );
    }

    private function validateClientPaymentForm(array $formData): ?string
    {
        if ($formData['destination'] === '') {
            return 'Veuillez renseigner la destination avant le paiement.';
        }

        if ($formData['client_nom'] === '') {
            return 'Veuillez renseigner le nom du client.';
        }

        if ((float) $formData['montant'] <= 0) {
            return 'Le montant doit etre superieur a 0.';
        }

        $cardLength = strlen((string) $formData['numero_carte']);
        if ($cardLength < 13 || $cardLength > 19) {
            return 'Numero de carte invalide (13 a 19 chiffres).';
        }

        if (mb_strlen((string) $formData['nom_titulaire']) < 3) {
            return 'Nom du titulaire invalide.';
        }

        if (!preg_match('/^\d{2}\/\d{2}$/', (string) $formData['expiration'])) {
            return 'Date d expiration invalide (MM/AA).';
        }

        if (!$this->isExpirationValid((string) $formData['expiration'])) {
            return 'La carte est expiree.';
        }

        if (!preg_match('/^\d{3,4}$/', (string) $formData['cvv'])) {
            return 'CVV invalide (3 ou 4 chiffres).';
        }

        return null;
    }

    private function validateAdminPaymentForm(array $formData): ?string
    {
        if (trim((string) ($formData['client_nom'] ?? '')) === '') {
            return 'Le nom client est obligatoire.';
        }

        if (trim((string) ($formData['destination'] ?? '')) === '') {
            return 'La destination est obligatoire.';
        }

        if ((float) ($formData['montant'] ?? 0) <= 0) {
            return 'Le montant doit etre superieur a 0.';
        }

        if (trim((string) ($formData['date_paiement'] ?? '')) === '') {
            return 'La date de paiement est obligatoire.';
        }

        return null;
    }

    private function validateInvoiceForm(array $formData): ?string
    {
        if (trim((string) ($formData['client_nom'] ?? '')) === '') {
            return 'Veuillez entrer le nom du client.';
        }

        if (trim((string) ($formData['client_email'] ?? '')) === '') {
            return 'Veuillez entrer l email du client.';
        }

        if (trim((string) ($formData['destination'] ?? '')) === '') {
            return 'Veuillez entrer la destination.';
        }

        if (trim((string) ($formData['date_debut'] ?? '')) === '' || trim((string) ($formData['date_fin'] ?? '')) === '') {
            return 'Veuillez renseigner les dates du voyage.';
        }

        if ((float) ($formData['montant_transport'] ?? 0) <= 0 || (float) ($formData['montant_hebergement'] ?? 0) <= 0 || (float) ($formData['montant_activites'] ?? 0) < 0) {
            return 'Les montants de facture sont invalides.';
        }

        return null;
    }

    private function sanitizeDigits(string $value, int $maxLength): string
    {
        return substr(preg_replace('/\D+/', '', $value) ?? '', 0, $maxLength);
    }

    private function normalizeHolderName(string $value): string
    {
        $value = strtoupper(trim($value));
        $value = preg_replace('/[^A-Z\s]/', '', $value) ?? '';
        $value = preg_replace('/\s+/', ' ', $value) ?? '';

        return trim($value);
    }

    private function maskCardNumber(string $digits): string
    {
        $digits = $this->sanitizeDigits($digits, 19);
        if (strlen($digits) < 4) {
            return '****';
        }

        return '**** **** **** '.substr($digits, -4);
    }

    private function isExpirationValid(string $expiration): bool
    {
        [$month, $year] = explode('/', $expiration);
        $month = (int) $month;
        $year = 2000 + (int) $year;
        if ($month < 1 || $month > 12) {
            return false;
        }

        $expiryTimestamp = strtotime(sprintf('%04d-%02d-01 +1 month -1 day 23:59:59', $year, $month));

        return $expiryTimestamp !== false && $expiryTimestamp >= time();
    }

    private function normalizeDateTimeInput(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        return str_contains($value, 'T') ? str_replace('T', ' ', $value).':00' : $value;
    }

    private function formatDateTimeLocal(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return date('Y-m-d\TH:i');
        }

        $timestamp = strtotime($value);

        return $timestamp !== false ? date('Y-m-d\TH:i', $timestamp) : date('Y-m-d\TH:i');
    }

    private function ensureAdminAccess(Request $request): ?RedirectResponse
    {
        $user = $this->getAuthenticatedUser($request);
        if ($user === null) {
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isAdminRole((string) ($user['role'] ?? 'USER'))) {
            return $this->redirectToRoute('app_dashboard');
        }

        return null;
    }

    private function getAuthenticatedUser(Request $request): ?array
    {
        $user = $request->getSession()->get('auth_user');

        return is_array($user) && trim((string) ($user['email'] ?? '')) !== '' ? $user : null;
    }

    private function isAdminRole(string $role): bool
    {
        return in_array(strtoupper(trim($role)), ['ADMIN', 'SUPER_ADMIN'], true);
    }

    private function consumeFlash(Request $request, string $type): ?string
    {
        $messages = $request->getSession()->getFlashBag()->get($type);

        return $messages !== [] ? implode(' ', $messages) : null;
    }
}
