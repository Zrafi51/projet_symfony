<?php

namespace App\Service;

use App\Repository\FactureRepository;
use App\Repository\UserRepository;
use App\Validation\LegacyValidator;

final class InvoiceDeliveryService
{
    public function __construct(
        private readonly FactureRepository $factureRepository,
        private readonly UserRepository $userRepository,
        private readonly UserNotificationService $notificationService,
    ) {
    }

    public function deliver(array $invoice, string $senderEmail = 'contact@easytravel.tn', string $senderRole = 'ADMIN'): array
    {
        $invoiceId = max(0, (int) ($invoice['id'] ?? 0));
        if ($invoiceId <= 0) {
            return [
                'ok' => false,
                'message' => 'Facture introuvable.',
            ];
        }

        $recipientEmail = trim((string) ($invoice['client_email'] ?? ''));
        if (!LegacyValidator::isValidEmail($recipientEmail)) {
            return [
                'ok' => false,
                'message' => 'Veuillez renseigner un email client valide avant l envoi de la facture.',
            ];
        }

        $wasAlreadySent = strtoupper(trim((string) ($invoice['statut'] ?? ''))) === 'ENVOYEE';

        $payload = [
            ...$invoice,
            'statut' => 'ENVOYEE',
        ];
        unset($payload['id']);

        if (!$this->factureRepository->update($invoiceId, $payload)) {
            return [
                'ok' => false,
                'message' => 'Impossible d envoyer cette facture pour le moment.',
            ];
        }

        $recipientUser = $this->userRepository->getByEmail($recipientEmail);
        $notificationSent = false;

        if ($recipientUser !== null && (bool) ($recipientUser['is_active'] ?? true)) {
            $this->notificationService->notifyUser(
                $recipientEmail,
                (string) ($recipientUser['role'] ?? 'USER'),
                $senderEmail,
                $senderRole,
                'BOOKING',
                $this->buildNotificationTitle($invoice),
                $this->buildNotificationMessage($invoice)
            );
            $notificationSent = true;
        }

        $clientName = trim((string) ($invoice['client_nom'] ?? 'Client'));
        $message = $notificationSent
            ? ($wasAlreadySent
                ? sprintf('Facture renvoyee a %s et deja visible dans son dashboard.', $clientName)
                : sprintf('Facture envoyee a %s et ajoutee a son dashboard.', $clientName))
            : ($wasAlreadySent
                ? sprintf('Facture renvoyee pour %s. Aucun compte client actif lie a %s, utilisez aussi le bouton email.', $clientName, $recipientEmail)
                : sprintf('Facture marquee comme envoyee pour %s. Aucun compte client actif lie a %s, utilisez aussi le bouton email.', $clientName, $recipientEmail));

        return [
            'ok' => true,
            'message' => $message,
            'notification_sent' => $notificationSent,
            'mailto_url' => $this->buildMailtoUrl($invoice),
        ];
    }

    public function buildMailtoUrl(array $invoice): string
    {
        $recipientEmail = trim((string) ($invoice['client_email'] ?? ''));
        if (!LegacyValidator::isValidEmail($recipientEmail)) {
            return '';
        }

        $invoiceNumber = trim((string) ($invoice['numero_facture'] ?? 'Facture EasyTravel'));
        $destination = trim((string) ($invoice['destination'] ?? 'votre voyage'));
        $clientName = trim((string) ($invoice['client_nom'] ?? 'Client'));
        $dateStart = trim((string) ($invoice['date_debut'] ?? ''));
        $dateEnd = trim((string) ($invoice['date_fin'] ?? ''));
        $period = trim($dateStart.' - '.$dateEnd, ' -');
        $amount = $this->formatMoney((float) ($invoice['montant_total'] ?? 0));

        $subject = sprintf('Facture EasyTravel %s', $invoiceNumber);
        $body = implode("\r\n", [
            sprintf('Bonjour %s,', $clientName !== '' ? $clientName : 'Client'),
            '',
            sprintf('Votre facture %s pour %s est prete.', $invoiceNumber, $destination !== '' ? $destination : 'votre voyage'),
            sprintf('Montant total: %s TND', $amount),
            $period !== '' ? sprintf('Periode: %s', $period) : '',
            '',
            'Merci pour votre confiance.',
            'Equipe EasyTravel',
        ]);

        return 'mailto:'
            .str_replace('%40', '@', rawurlencode($recipientEmail))
            .'?subject='
            .rawurlencode($subject)
            .'&body='
            .rawurlencode($body);
    }

    private function buildNotificationTitle(array $invoice): string
    {
        $invoiceNumber = trim((string) ($invoice['numero_facture'] ?? ''));
        if ($invoiceNumber === '') {
            return 'Nouvelle facture EasyTravel';
        }

        return sprintf('Nouvelle facture %s', $invoiceNumber);
    }

    private function buildNotificationMessage(array $invoice): string
    {
        $destination = trim((string) ($invoice['destination'] ?? 'votre voyage'));
        $amount = $this->formatMoney((float) ($invoice['montant_total'] ?? 0));

        return sprintf(
            'Votre facture pour %s est disponible. Montant total: %s TND. Vous pouvez la consulter dans votre dashboard.',
            $destination !== '' ? $destination : 'votre voyage',
            $amount
        );
    }

    private function formatMoney(float $amount): string
    {
        return number_format($amount, 2, '.', ' ');
    }
}
