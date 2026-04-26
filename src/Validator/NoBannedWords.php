<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Rejects text containing profanity or violent language (French + English).
 * The dictionary is intentionally curated, not exhaustive — extend BANNED_WORDS
 * as needed.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
class NoBannedWords extends Constraint
{
    public string $message = 'Votre texte contient un mot interdit : « {{ word }} ». Merci de reformuler.';

    /**
     * Curated French + English list of profanity / violent terms.
     * Matched case-insensitively on Unicode word boundaries, so "FUCK" and
     * "Merde!" both trip. Multi-word entries (e.g. "ta gueule") work because
     * the boundary check treats any non-letter/digit char as a boundary.
     */
    public const BANNED_WORDS = [
        // ——— English: profanity ———
        'fuck','test', 'fucking', 'fucker', 'motherfucker', 'fuckoff',
        'shit', 'bullshit', 'shitty',
        'bitch', 'bitches', 'bastard', 'asshole', 'arsehole',
        'cunt', 'dick', 'pussy', 'prick', 'wanker', 'twat',
        'whore', 'slut',
        'damn', 'goddamn',
        // ——— English: slurs / hate speech ———
        'nigger', 'nigga', 'faggot', 'fag', 'retard', 'retarded',
        'nazi',
        // ——— English: violence ———
        'kill', 'murder', 'rape', 'raping', 'stab', 'strangle',
        'terrorist', 'bomb',

        // ——— Français : insultes ———
        'putain', 'merde', 'merdique',
        'connard', 'connasse', 'con', 'conne',
        'salope', 'salaud', 'pétasse', 'petasse', 'pute', 'putes',
        'enculé', 'encule', 'enculer', 'enculés', 'encules',
        'bite', 'bitte', 'couille', 'couilles',
        'ta gueule', 'ferme-la', 'ferme ta gueule',
        'pédé', 'pede', 'tapette',
        'chier', 'chiant', 'chiante',
        'foutre',
        // ——— Français : insultes racistes / haineuses ———
        'nègre', 'negre', 'négresse', 'negresse',
        'bougnoule', 'bougnoul',
        'raciste',
        // ——— Français : violence ———
        'tuer', 'tue', 'assassiner',
        'violer', 'viole',
        'frapper', 'tabasser',
        'terroriste',
    ];
}
