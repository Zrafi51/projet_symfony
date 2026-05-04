<?php

namespace App\Twig;

use App\Validator\NoBannedWords;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            // Exposes the curated banned-words dictionary to client-side JS so
            // the new-post form can flag violations instantly (no refresh).
            new TwigFunction('banned_words', fn (): array => NoBannedWords::BANNED_WORDS),
        ];
    }

    public function getFilters(): array
    {
        return [
            // Renders a description with #hashtags turned into clickable links
            // that run the existing keyword search (no new route needed — the
            // feed's `?keyword=#X` LIKE filter already does the filtering).
            new TwigFilter('hashtagify', [$this, 'hashtagify'], ['is_safe' => ['html']]),
        ];
    }

    /**
     * Escape the description, then wrap every `#tag` in an anchor pointing to
     * the filtered feed. The regex uses `\p{L}` so accented characters (Brésil,
     * Québec…) count as part of the tag.
     */
    public function hashtagify(?string $text): string
    {
        if ($text === null || $text === '') {
            return '';
        }
        // Apply the hashtag regex on RAW text first, then escape each chunk
        // independently. Escaping before matching turns apostrophes into
        // `&#039;` which the regex would then match as "#039".
        $esc = fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $out = '';
        $offset = 0;
        if (preg_match_all('/#([\p{L}0-9_]+)/u', $text, $m, PREG_OFFSET_CAPTURE)) {
            foreach ($m[0] as $i => $full) {
                $start = $full[1];
                $out .= $esc(substr($text, $offset, $start - $offset));
                $tag = $m[1][$i][0];
                $url = '/forum?keyword=%23' . rawurlencode($tag);
                $out .= '<a href="' . $esc($url) . '" class="hashtag-link">#' . $esc($tag) . '</a>';
                $offset = $start + strlen($full[0]);
            }
        }
        $out .= $esc(substr($text, $offset));
        return $out;
    }
}
