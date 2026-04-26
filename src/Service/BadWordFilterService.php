<?php

namespace App\Service;

final class BadWordFilterService
{
    /**
     * @var array<int, array{label: string, pattern: string, compact?: string}>
     */
    private const BLOCKED_PATTERNS = [
        ['label' => 'merde', 'pattern' => '/\bmerd(?:e|es)\b/ui', 'compact' => 'merde'],
        ['label' => 'connard', 'pattern' => '/\bconnard(?:e|s|es)?\b/ui', 'compact' => 'connard'],
        ['label' => 'con', 'pattern' => '/\bcons?\b/ui'],
        ['label' => 'salope', 'pattern' => '/\bsalop(?:e|es)?\b/ui', 'compact' => 'salope'],
        ['label' => 'pute', 'pattern' => '/\bput(?:e|es)?\b/ui', 'compact' => 'pute'],
        ['label' => 'encule', 'pattern' => '/\bencul(?:e|er|ee|ees|es)?\b/ui', 'compact' => 'encule'],
        ['label' => 'batard', 'pattern' => '/\bbatard(?:s)?\b/ui', 'compact' => 'batard'],
        ['label' => 'fuck', 'pattern' => '/\bfuck(?:ing|er|ed)?\b/ui', 'compact' => 'fuck'],
        ['label' => 'shit', 'pattern' => '/\bshit(?:ty)?\b/ui', 'compact' => 'shit'],
        ['label' => 'bitch', 'pattern' => '/\bbitch(?:es)?\b/ui', 'compact' => 'bitch'],
        ['label' => 'asshole', 'pattern' => '/\basshole\b/ui', 'compact' => 'asshole'],
        ['label' => 'zebi', 'pattern' => '/\b(?:zebi|zeby|zebbi)\b/ui', 'compact' => 'zebi'],
        ['label' => 'kahba', 'pattern' => '/\b(?:kahba|qahba|qa7ba|9ahba|9a7ba)\b/ui', 'compact' => 'qahba'],
        ['label' => 'nik', 'pattern' => '/\b(?:nik|nique)(?:er|e|ek|ok)?\b/ui', 'compact' => 'nik'],
        ['label' => 'kalb', 'pattern' => '/\b(?:kalb|kelb)\b/ui', 'compact' => 'kalb'],
        ['label' => 'hmar', 'pattern' => '/\bhmar\b/ui', 'compact' => 'hmar'],
        ['label' => 'ta7an', 'pattern' => '/\b(?:tahan|ta7an)\b/ui', 'compact' => 'tahan'],
    ];

    public function findFirstBlockedWord(string ...$values): ?string
    {
        foreach ($values as $value) {
            $normalizedValue = $this->normalize($value);
            if ($normalizedValue === '') {
                continue;
            }

            $compactValue = $this->compact($normalizedValue);
            foreach (self::BLOCKED_PATTERNS as $rule) {
                if (preg_match($rule['pattern'], $normalizedValue) === 1) {
                    return $rule['label'];
                }

                $compactPattern = (string) ($rule['compact'] ?? '');
                if ($compactPattern !== '' && str_contains($compactValue, $compactPattern)) {
                    return $rule['label'];
                }
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $normalized = strtr($normalized, [
            'à' => 'a',
            'á' => 'a',
            'â' => 'a',
            'ä' => 'a',
            'ã' => 'a',
            'å' => 'a',
            'ç' => 'c',
            'è' => 'e',
            'é' => 'e',
            'ê' => 'e',
            'ë' => 'e',
            'ì' => 'i',
            'í' => 'i',
            'î' => 'i',
            'ï' => 'i',
            'ñ' => 'n',
            'ò' => 'o',
            'ó' => 'o',
            'ô' => 'o',
            'ö' => 'o',
            'õ' => 'o',
            'ù' => 'u',
            'ú' => 'u',
            'û' => 'u',
            'ü' => 'u',
            '@' => 'a',
            '$' => 's',
            '!' => 'i',
            '0' => 'o',
            '1' => 'i',
            '3' => 'e',
            '4' => 'a',
            '5' => 's',
            '7' => 'h',
            '9' => 'q',
        ]);
        $normalized = preg_replace('/[\r\n\t]+/u', ' ', $normalized) ?? '';
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized) ?? '';

        return $normalized;
    }

    private function compact(string $normalizedValue): string
    {
        return preg_replace('/[^a-z0-9]+/u', '', $normalizedValue) ?? '';
    }
}
