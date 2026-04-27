<?php

namespace App\Service;

use App\Workflow\DestinationWorkflowSubject;
use Symfony\Component\Workflow\Exception\LogicException;
use Symfony\Component\Workflow\WorkflowInterface;

final class DestinationWorkflowManager
{
    public function __construct(
        private readonly WorkflowInterface $destinationLifecycleStateMachine,
    ) {
    }

    public function getPlaceMeta(string|array $destination): array
    {
        $place = is_array($destination)
            ? $this->normalizePlace((string) ($destination['workflow_place'] ?? 'draft'))
            : $this->normalizePlace($destination);

        return match ($place) {
            'in_review' => ['place' => 'in_review', 'label' => 'En revue', 'tone' => 'warning', 'description' => 'La destination attend une validation editoriale.'],
            'rejected' => ['place' => 'rejected', 'label' => 'A retravailler', 'tone' => 'danger', 'description' => 'Des corrections sont necessaires avant publication.'],
            'published' => ['place' => 'published', 'label' => 'Publiee', 'tone' => 'success', 'description' => 'Visible sur le catalogue public et exploitable par les pages Symfony.'],
            'archived' => ['place' => 'archived', 'label' => 'Archivee', 'tone' => 'neutral', 'description' => 'Conservee pour historique, masquee du catalogue.'],
            default => ['place' => 'draft', 'label' => 'Brouillon', 'tone' => 'info', 'description' => 'Encore en preparation, invisible pour les visiteurs.'],
        };
    }

    public function getEnabledTransitions(array $destination): array
    {
        $subject = new DestinationWorkflowSubject(
            $this->normalizePlace((string) ($destination['workflow_place'] ?? 'draft'))
        );

        $transitions = [];
        foreach ($this->destinationLifecycleStateMachine->getEnabledTransitions($subject) as $transition) {
            $name = $transition->getName();
            $transitions[] = [
                'name' => $name,
                'label' => $this->transitionLabel($name),
                'tone' => $this->transitionTone($name),
            ];
        }

        return $transitions;
    }

    public function applyTransition(string $currentPlace, string $transition): string
    {
        $subject = new DestinationWorkflowSubject($this->normalizePlace($currentPlace));

        if (!$this->destinationLifecycleStateMachine->can($subject, $transition)) {
            throw new LogicException(sprintf('Transition "%s" impossible depuis "%s".', $transition, $currentPlace));
        }

        $this->destinationLifecycleStateMachine->apply($subject, $transition);

        return $this->normalizePlace($subject->getCurrentPlace());
    }

    public function isPubliclyVisible(string|array $destination): bool
    {
        $place = is_array($destination)
            ? (string) ($destination['workflow_place'] ?? 'draft')
            : $destination;

        return $this->normalizePlace($place) === 'published';
    }

    public function buildReadiness(array $payload): array
    {
        $blockers = [];
        $strengths = [];
        $score = 44;

        $name = trim((string) ($payload['nom'] ?? ''));
        $country = trim((string) ($payload['pays'] ?? ''));
        $continent = trim((string) ($payload['continent'] ?? ''));
        $description = trim((string) ($payload['description'] ?? ''));
        $coverImagePath = trim((string) ($payload['cover_image_path'] ?? ''));
        $heroVideoPath = trim((string) ($payload['hero_video_path'] ?? ''));
        $priceBase = max(0.0, (float) ($payload['prix_base'] ?? 0));
        $durationDays = max(0, (int) ($payload['duration_days'] ?? 0));
        $maxTravelers = max(0, (int) ($payload['max_travelers'] ?? 0));
        $interestCount = count($this->parseTagString($payload['interest_tags'] ?? $payload['interest_tags_list'] ?? ''));
        $audienceCount = count($this->parseTagString($payload['audience_tags'] ?? $payload['audience_tags_list'] ?? ''));

        if ($name === '') {
            $blockers[] = 'Le nom de destination manque.';
        } else {
            $score += 6;
            $strengths[] = 'Nom commercial defini.';
        }

        if ($country === '' || $continent === '') {
            $blockers[] = 'Le pays et le continent doivent etre definis.';
        } else {
            $score += 8;
            $strengths[] = 'Geographie catalogue complete.';
        }

        if ($priceBase <= 0) {
            $blockers[] = 'Le prix de base doit etre superieur a zero.';
        } else {
            $score += 8;
            $strengths[] = 'Positionnement prix exploitable.';
        }

        if (mb_strlen($description) < 60) {
            $blockers[] = 'La description doit etre plus riche pour la publication.';
        } else {
            $score += 14;
            $strengths[] = 'Storytelling destination suffisamment detaille.';
        }

        if ($durationDays <= 0) {
            $blockers[] = 'La duree du sejour doit etre indiquee.';
        } else {
            $score += 6;
        }

        if ($maxTravelers <= 0) {
            $blockers[] = 'La capacite voyageurs doit etre renseignee.';
        } else {
            $score += 4;
        }

        if ($interestCount < 2) {
            $blockers[] = 'Ajoute au moins deux tags d interet pour la recommandation.';
        } else {
            $score += 10;
            $strengths[] = 'Ciblage interets exploitable par l IA et les filtres.';
        }

        if ($audienceCount < 1) {
            $blockers[] = 'Ajoute au moins un profil voyageur.';
        } else {
            $score += 5;
        }

        if ($coverImagePath === '') {
            $blockers[] = 'Ajoute une image de couverture pour le catalogue.';
        } else {
            $score += 9;
            $strengths[] = 'Media principal pret pour les cartes.';
        }

        if ($heroVideoPath !== '') {
            $score += 6;
            $strengths[] = 'Video hero disponible pour la page immersive.';
        }

        $score = max(0, min(100, $score));

        return [
            'score' => $score,
            'blockers' => $blockers,
            'strengths' => $strengths,
            'label' => $score >= 85 && $blockers === []
                ? 'Pret pour publication'
                : ($score >= 65 ? 'Base solide a finaliser' : 'Fiche a enrichir'),
        ];
    }

    public function validateTransitionReadiness(array $payload, string $transition): array
    {
        $transition = trim($transition);
        if ($transition === '') {
            return [];
        }

        $readiness = $this->buildReadiness($payload);
        $blockers = $readiness['blockers'];

        return match ($transition) {
            'submit_review' => array_values(array_filter($blockers, static fn (string $message): bool => !str_contains($message, 'Video hero'))),
            'publish' => $blockers,
            default => [],
        };
    }

    private function normalizePlace(string $place): string
    {
        $place = trim($place);

        return in_array($place, ['draft', 'in_review', 'rejected', 'published', 'archived'], true)
            ? $place
            : 'draft';
    }

    private function transitionLabel(string $transition): string
    {
        return match ($transition) {
            'submit_review' => 'Envoyer en revue',
            'reject' => 'Refuser',
            'rework' => 'Remettre en brouillon',
            'publish' => 'Publier',
            'archive' => 'Archiver',
            'unpublish' => 'Retirer du catalogue',
            default => ucfirst(str_replace('_', ' ', $transition)),
        };
    }

    private function transitionTone(string $transition): string
    {
        return match ($transition) {
            'publish' => 'success',
            'reject', 'archive' => 'danger',
            'submit_review' => 'warning',
            default => 'info',
        };
    }

    private function parseTagString(mixed $value): array
    {
        if (is_array($value)) {
            $parts = $value;
        } else {
            $parts = preg_split('/[,|]/', (string) $value) ?: [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $parts
        )));
    }
}
