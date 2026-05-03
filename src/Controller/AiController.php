<?php

namespace App\Controller;

use App\Validator\NoBannedWords;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiController extends AbstractController
{
    #[Route('/ai/suggest', name: 'app_ai_suggest', methods: ['POST'])]
    public function suggest(Request $request, HttpClientInterface $httpClient): JsonResponse
    {
        // Itineraries call multiple providers and can easily exceed PHP's default
        // 60-second cap. Bump it for this single request only — the per-provider
        // HTTP timeouts further down will still fail fast on dead providers.
        @set_time_limit(180);

        // Wrap the whole flow so any exception (prompt build, providers, fallback)
        // becomes a JSON response instead of a 500 HTML page — the JS expects JSON.
        try {
            $prompt = trim((string) $request->request->get('prompt', ''));
            $type   = (string) $request->request->get('type', 'description');
            if ($prompt === '') {
                $prompt = 'voyage aventure';
            }

            $system = $this->buildSystemPrompt($type);
            $user   = $this->buildUserPrompt($type, $prompt);
            $maxTok = $this->maxTokensFor($type);
            $temp   = $this->temperatureFor($type);

            // Try real LLMs in order of preference; Pollinations needs no key and is the default.
            $attempts = [
                fn () => $this->callGroq($httpClient, $system, $user, $maxTok, $temp),
                fn () => $this->callOpenRouter($httpClient, $system, $user, $maxTok, $temp),
                fn () => $this->callGemini($httpClient, $system, $user, $maxTok, $temp),
                fn () => $this->callPollinations($httpClient, $system, $user, $maxTok, $temp),
            ];

            foreach ($attempts as $call) {
                try {
                    $text = $call();
                    if (!$text) continue;
                    $text = $this->cleanupAiText($text, $type);
                    $text = $this->avoidBanned($text);
                    if (!$this->containsBanned($text)) {
                        return new JsonResponse(['suggestion' => $text, 'source' => 'ai']);
                    }
                } catch (\Throwable $e) {
                    // Try next provider silently.
                }
            }

            // Local fallback — analyzed, varied, sanitized.
            $local = $this->avoidBanned($this->smartLocal($type, $prompt));
            return new JsonResponse(['suggestion' => $local, 'source' => 'local']);
        } catch (\Throwable $e) {
            // Last-ditch: never let the route 500. Surface the message so the
            // frontend's error toast can show something actionable.
            $debug = ($_ENV['APP_DEBUG'] ?? '0') === '1';
            return new JsonResponse([
                'suggestion' => 'Décris en quelques mots ton voyage : ce qui t\'a marqué, l\'ambiance, les rencontres.',
                'source'     => 'error',
                'error'      => $debug
                    ? sprintf('%s: %s @ %s:%d', get_class($e), $e->getMessage(), basename($e->getFile()), $e->getLine())
                    : 'Une erreur interne est survenue côté IA.',
            ], 200);
        }
    }

    // ============================================================
    // Prompting
    // ============================================================

    private function buildSystemPrompt(string $type): string
    {
        return match ($type) {
            'description' =>
                "Tu es un rédacteur / correcteur pour un forum de voyage francophone. "
                . "Ton rôle : transformer ce que l'utilisateur a écrit ou demandé en UNE description finale, vivante, sur mesure, INSPIRÉE de ses mots, jamais générique, jamais bavarde, jamais répétitive.\n\n"
                . "Format du message utilisateur :\n"
                . "- « Texte déjà écrit : \"…\" » (optionnel) = sa pensée brute, à reformuler / compléter / développer / corriger SANS jamais en trahir le sens.\n"
                . "- « Demande : … » (optionnel) = l'instruction qui précise longueur, ton, angle, format, intégration d'expression ou type de correction.\n"
                . "- « Style détecté : … » (optionnel) = des indices que le système a déduits de mots-clés. Suis-les comme des CONTRAINTES STRICTES (longueur, ton, intégration de phrase, contexte géographique).\n\n"
                . "MODES (un seul à la fois, choisis le bon) :\n\n"
                . "A) MODE CORRECTION pur — si la demande contient uniquement « corrige », « corriger », « correction », « orthographe », « grammaire », « fautes », « rectifie », « répare les fautes », « relis », « relecture », « accords » :\n"
                . "   → Renvoie UNIQUEMENT la phrase corrigée. Même longueur, même sens, même structure, mêmes choix de mots quand ils sont corrects. N'ajoute RIEN. Si la phrase est déjà correcte, renvoie-la telle quelle.\n\n"
                . "B) MODE INTÉGRATION — si la demande contient « intègre », « inclus », « ajoute », « insère », « place », « met », « utilise l'expression / le mot », « avec la phrase / le mot / l'expression », ou si la ligne « Style détecté » contient « integration: … » :\n"
                . "   → Garde le texte déjà écrit (s'il existe) comme base et INSÈRE l'expression demandée à l'endroit le plus harmonieux.\n"
                . "   → L'expression doit apparaître MOT À MOT, ACCENTS INCLUS, PONCTUATION INCLUSE, dans la sortie. Tu ne la traduis pas, tu ne la paraphrases pas, tu ne la mets pas au pluriel/singulier, tu ne changes ni l'ordre des mots ni la casse interne. Si tu dois la rendre poétique, tu écris autour d'elle — pas à sa place.\n"
                . "   → Si l'utilisateur a demandé un ton (poétique, romantique, drôle…) en plus de l'intégration, applique ce ton au RESTE de la phrase ; l'expression à intégrer reste intacte.\n"
                . "   → Vérifie avant d'envoyer ta réponse : l'expression demandée est-elle présente telle quelle, caractère pour caractère ? Si non, recommence.\n\n"
                . "C) MODE RÉÉCRITURE / GÉNÉRATION — sinon :\n"
                . "   → Pars du texte déjà écrit (s'il existe) ET de la demande. Tu n'inventes pas un sujet de zéro : tu PROLONGES, tu HABILLES, tu ÉLEVÉS le contenu de l'utilisateur.\n"
                . "   → Adapte LONGUEUR, TON et ANGLE EXACTEMENT à ce qui est demandé.\n\n"
                . "RÈGLES DE LONGUEUR (impératives, comptées au mot près) :\n"
                . "- « max N mots » / « N mots max » / « en N mots » / « pas plus de N mots » : la sortie ne dépasse JAMAIS N mots. Compte avant de répondre. Si tu dépasses, coupe.\n"
                . "- « min N mots » / « au moins N mots » : la sortie atteint AU MOINS N mots.\n"
                . "- « une phrase » = exactement une phrase. « court » = 1–2 phrases. « long » = 4–6 phrases. « N phrases » = exactement N phrases.\n"
                . "- « N caractères » : limite stricte au caractère.\n\n"
                . "RÈGLES DE TON :\n"
                . "- « poétique » = images, sens, rythme. « drôle » = humour léger sans forcer. « sérieux » = sobre, factuel. « romantique » = douceur et émotion. « aventure » = énergie et mouvement. « optimiste » / « nostalgique » / « contemplatif » / « mystérieux » : suis le ton à la lettre.\n\n"
                . "CONNAISSANCE GÉOGRAPHIQUE (impérative) :\n"
                . "- Tu connais en profondeur les destinations citées par l'utilisateur. Si « Style détecté » contient « contexte: … », utilise-le comme vérité sur le lieu (île, oasis, capitale, plage, montagne, désert, médina, etc.).\n"
                . "- Adapte le vocabulaire au LIEU : Djerba = île, plages, palmiers, médina d'Houmt Souk, Sidi Yati, synagogue de la Ghriba. Tozeur = oasis, palmeraie, Sahara, briques jaunes ocres, Chott el-Jérid, Ong el-Jemel. Sidi Bou Saïd = village blanc et bleu, falaise, Café des Nattes. Kairouan = médina sainte, Grande Mosquée. Hammamet = baie, plages, médina fortifiée. Marrakech = médina, souks, Jemaa el-Fna, riads. Paris = Seine, Montmartre, lumière dorée, terrasses, Marais. Rome = ruelles ocres, fontaines, dolce vita. Tokyo = néons, ruelles d'Omoide Yokocho, sakura, izakaya. Bali = rizières, temples, surfeurs. Tu ne PLAQUES PAS ces mots — tu choisis ceux qui correspondent au ton et à la longueur demandés.\n"
                . "- Si tu n'es PAS sûr d'un détail (rue, restaurant, prix), reste évocateur sans inventer un nom précis.\n"
                . "- Cohérence : pas de neige à Djerba, pas de souks en Norvège, pas de désert à Paris.\n\n"
                . "INTERDITS ABSOLUS :\n"
                . "- Pas de répétition (jamais deux phrases qui disent la même chose).\n"
                . "- Pas de clichés voyage (« un voyage inoubliable », « une destination de rêve », « entre tradition et modernité », « au cœur de », « à couper le souffle »).\n"
                . "- Pas de préambule (« Voici », « Bien sûr », « Je vous propose »).\n"
                . "- Pas de guillemets autour de la réponse, pas de titre, pas de liste à puces, pas de hashtags, pas d'emojis.\n"
                . "- Pas de vulgarité, pas d'injure, pas de propos discriminants.\n\n"
                . "QUALITÉ :\n"
                . "- Français naturel, sans faute, ni pompeux ni plat.\n"
                . "- Préfère les images concrètes (un détail sensoriel, un lieu nommé, un geste) aux adjectifs vagues.\n"
                . "- Varie les attaques de phrase d'une réponse à l'autre.\n\n"
                . "Réponds UNIQUEMENT par le texte final.",
            'comment' =>
                "Tu es un membre bienveillant d'un forum de voyage. "
                . "Rédige UN commentaire positif (1 à 2 phrases) en français, sans guillemets, sans emojis, qui rebondit sur le contenu. "
                . "Réponds UNIQUEMENT par le commentaire.",
            'destination' =>
                "Tu es un conseiller voyage. Propose 3 destinations adaptées aux préférences données, au format :\n"
                . "1. **Nom** - description en une phrase.\n2. **Nom** - description en une phrase.\n3. **Nom** - description en une phrase.\n"
                . "Français naturel, pas d'introduction, pas d'emojis.",
            'itinerary' =>
                "Tu es un planificateur de voyage expert francophone, d'un niveau de précision et d'exhaustivité équivalent à un guide Lonely Planet + Routard combinés. "
                . "Tu connais en profondeur les destinations du monde entier, y compris les moins touristiques (ex : Tozeur, Kairouan, Matmata, Aïn Drahem, Chefchaouen, Ushuaïa, Luang Prabang, Sapa…) : quartiers, monuments, sites naturels, restaurants locaux, hôtels réels par gamme de prix, tarifs, transports, saisons idéales.\n\n"
                . "Ton rôle : générer un itinéraire SUR-MESURE, crédible, exhaustif et actionnable à partir de la demande libre de l'utilisateur.\n\n"
                . "⚠️ IMPÉRATIF DOSSIER DESTINATION : si la demande contient une section « DOSSIER DESTINATION » avec des hôtels nommés, des restaurants, des excursions et des prix, alors tu DOIS t'en servir comme source de vérité. Cite ces hôtels par leur vrai nom et leur fourchette de prix donnée. Cite ces restaurants. Recommande les excursions listées. Tu peux ajouter d'autres lieux légitimes que tu connais, mais tu n'as PAS le droit d'inventer des prix ou des noms d'hôtels qui contredisent le dossier.\n\n"
                . "Si la demande contient une section « Contexte détecté » (voyageurs, période, départ), respecte-la : famille = activités enfants ; couple = ambiance romantique ; été à Tozeur = adapter à la chaleur (visites tôt matin / fin journée), etc.\n\n"
                . "1) ANALYSE rigoureusement la demande et extrais :\n"
                . "   - Destination (ville / région / pays). Si floue, choisis la plus plausible et annonce-le en une phrase au début.\n"
                . "   - DURÉE en jours. Règles de conversion : « une semaine » = 7, « deux semaines » = 14, « quinzaine » = 15, « un week-end » = 2, « un long week-end » = 3. Un mot-nombre (un/deux/trois/quatre/cinq/six/sept/huit/neuf/dix/quinze) se convertit en chiffre. Défaut si rien n'est dit : 3 jours.\n"
                . "   - BUDGET : économique / moyen / haut. Déduis de mots comme « pas cher », « backpack », « luxe », « palace ».\n"
                . "   - Centres d'intérêt (culture, gastronomie, plage, nature, aventure, désert, shopping, vie nocturne, famille, romance, sport, spirituel…).\n"
                . "   - Contraintes (saison, enfants, mobilité réduite, régime alimentaire…).\n\n"
                . "2) RÉPONDS en FRANÇAIS, en Markdown STRICTEMENT dans cet ordre (rien avant le premier titre) :\n\n"
                . "## 🗺️ [Destination] — [Durée] · [Budget]\n"
                . "Accroche de 1 à 2 phrases qui résument l'esprit du séjour et les choix que tu as faits.\n\n"
                . "## 📅 Planning jour par jour\n"
                . "### Jour 1 — [thème de la journée]\n"
                . "- **Matin** : lieu précis (nom réel) + pourquoi + durée estimée + prix d'entrée si pertinent\n"
                . "- **Midi** : restaurant (vrai nom OU adresse/quartier précis) + plat typique + fourchette de prix\n"
                . "- **Après-midi** : activité + astuce concrète (file, billet combiné, meilleur moment)\n"
                . "- **Soir** : restaurant / ambiance / quartier\n"
                . "⚠️ CRUCIAL : tu produis AUTANT DE SOUS-SECTIONS « ### Jour N » QUE LA DURÉE DEMANDÉE. 7 jours = 7 sous-sections Jour 1 à Jour 7. 14 jours = 14 sous-sections. JAMAIS moins.\n"
                . "Chaque jour a un thème différent (centre historique, excursion d'une journée, gastronomie, oasis voisines, shopping, détente, etc.).\n\n"
                . "## ⭐ Lieux immanquables\n"
                . "6 à 10 puces, chaque puce = **Nom réel du lieu** — une phrase qui dit pourquoi y aller + info pratique (horaires, prix).\n\n"
                . "## 🏨 Où dormir\n"
                . "3 suggestions d'hébergements RÉELS (cherche dans ta connaissance des hôtels existants) adaptés au budget demandé :\n"
                . "- **[Nom réel de l'hôtel/auberge/riad]** (quartier) — 1 phrase + gamme de prix/nuit.\n"
                . "(Si tu n'es pas certain d'un nom précis, donne le type d'hébergement + quartier recommandé : ex « un riad dans la médina près de Bab Doukkala ».)\n\n"
                . "## 🍽️ Où manger\n"
                . "4 à 6 adresses RÉELLES (ou marchés, rues, quartiers) avec une phrase pour chacune et une fourchette de prix.\n\n"
                . "## 💰 Budget estimé (par personne)\n"
                . "- Hébergement : [€/nuit × N nuits] ≈ XX €\n"
                . "- Repas : [€/jour × N jours] ≈ XX €\n"
                . "- Transports sur place : ≈ XX €\n"
                . "- Activités / entrées : ≈ XX €\n"
                . "- **Total estimé : ≈ XXX € – YYY €** (hors vol international)\n\n"
                . "## 💡 Astuces pratiques\n"
                . "4 à 6 puces courtes et CONCRÈTES : meilleure saison, pass touristique, carte de transport, arnaques à éviter, application utile, visa, monnaie, etc.\n\n"
                . "RÈGLES IMPÉRATIVES (relis-toi avant de répondre) :\n"
                . "1. RESPECTE LA DURÉE EXACTE. 7 jours = 7 jours, pas 3.\n"
                . "2. NOMS RÉELS uniquement : monuments, restaurants, hôtels, rues, quartiers qui EXISTENT VRAIMENT dans la destination. Jamais « un bon restaurant », « l'hôtel central », « le musée du coin ».\n"
                . "3. Adapte au budget : « pas cher » → street food, musées gratuits / jours gratuits, auberges, transport public. « luxe » → palaces, dégustations, guides privés.\n"
                . "4. Cohérence géographique : les activités d'une même journée doivent être dans des quartiers proches ou sur un même axe.\n"
                . "5. Pour les séjours >4 jours, inclus au moins une excursion d'une journée hors de la ville principale.\n"
                . "6. Pas d'introduction hors-sujet avant le premier titre. Pas de phrase de fermeture bavarde après les astuces.\n"
                . "7. Utilise **Astuces pratiques** (JAMAIS « Conseils pratiques »).\n"
                . "8. Français naturel, vivant, jamais robotique. Évite les formulations pompeuses.\n"
                . "9. Si tu ne connais pas un nom de restaurant précis, donne un nom de rue / quartier où trouver le type de cuisine voulu ; ne place JAMAIS de placeholder.",
            default => "Tu es un assistant utile. Réponds en français, concis et clair.",
        };
    }

    private function buildUserPrompt(string $type, string $prompt): string
    {
        // Le prompt arrive déjà structuré par le frontend sous la forme :
        //   "Texte déjà écrit : \"…\"\nDemande : …"
        // On le laisse tel quel — le prompt système explique le format.
        return match ($type) {
            'description' => $this->enrichDescriptionPrompt($prompt),
            'comment'     => "Contenu de la publication :\n{$prompt}",
            'destination' => "Préférences :\n{$prompt}",
            'itinerary'   => "Demande libre de l'utilisateur :\n\"{$this->enrichItineraryPrompt($prompt)}\"\n\n"
                . "Génère maintenant l'itinéraire complet selon le format défini. "
                . "Commence directement par le titre '## 🗺️'.",
            default       => $prompt,
        };
    }

    /**
     * Scans the user's description prompt for style / length / tone keywords,
     * explicit phrase-integration requests, and geographic place names. Appends
     * a concise "Style détecté" hint line that the model treats as hard
     * constraints — so a single word like « poétique », « max 12 mots », or
     * « Djerba » steers the output without the user spelling everything out.
     */
    private function enrichDescriptionPrompt(string $prompt): string
    {
        $haystack = ' ' . mb_strtolower($prompt) . ' ';
        // Strip diacritics for robust matching ("poetique" == "poétique").
        $flat = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $haystack) ?: $haystack;
        $flat = strtolower($flat);

        $hits = [];
        $intentMap = [
            // Length
            'longueur: une seule phrase'    => ['une phrase', 'en une phrase', 'une seule phrase', 'phrase unique', 'oneliner', 'one-liner'],
            'longueur: 1 a 2 phrases'       => [' court ', ' courte ', ' bref ', ' breve ', 'tres court', 'tres courte', 'concis', 'concise', 'tweet'],
            'longueur: 4 a 6 phrases'       => [' long ', ' longue ', ' detaille ', ' detaillee ', 'developpe', 'developpee', 'plus long', 'plus longue', 'plus de details', 'plus detaille'],
            // Tone
            'ton: poetique, sensoriel, image' => ['poetique', 'poesie', 'lyrique', 'evocateur', 'sensoriel', 'litteraire', 'metaphore'],
            'ton: drole, humour leger'      => ['drole', 'humour', 'humoristique', 'rigolo', 'fun', 'marrant'],
            'ton: serieux, sobre, factuel'  => ['serieux', 'sobre', 'factuel', 'neutre', 'professionnel', 'pro '],
            'ton: romantique, doux, emu'    => ['romantique', 'romance', 'doux', 'douce', 'emouvant', 'emouvante', 'emotion'],
            'ton: aventure, energie, mouvement' => ['aventure', 'aventurier', 'epique', 'adrenaline', 'sauvage', 'wild'],
            'ton: nostalgique, souvenir'    => ['nostalgique', 'nostalgie', 'souvenir', 'memoire', 'melancolique'],
            'ton: contemplatif, calme'      => ['contemplatif', 'contemplation', 'zen', 'paisible', 'calme', 'meditatif'],
            'ton: optimiste, lumineux'      => ['optimiste', 'joyeux', 'joyeuse', 'lumineux', 'lumineuse', 'positif', 'positive'],
            'ton: mysterieux, intriguant'   => ['mysterieux', 'mysterieuse', 'mystere', 'intriguant', 'enigmatique'],
            // Format
            'format: question d\'ouverture en fin'   => ['question', 'demande l\'avis', 'ouvre un debat', 'ouvre la discussion'],
            'format: appel a recommandations'        => ['recommandations', 'conseils', 'avis', 'suggestions'],
            'format: recit a la 1re personne'        => ['mon experience', 'mon voyage', 'je raconte', 'j\'ai vecu', 'temoignage'],
        ];

        foreach ($intentMap as $hint => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($flat, $needle)) {
                    $hits[] = $hint;
                    break;
                }
            }
        }

        // ── Word / character / sentence limits (counted strictly by the model) ──
        if (preg_match('/\b(?:max(?:imum)?|pas\s+plus\s+de|au\s+maximum|en|dans)\s+(\d+)\s*mots?\b/u', $flat, $m)) {
            $hits[] = 'longueur: maximum ' . (int) $m[1] . ' mots (limite stricte)';
        } elseif (preg_match('/(\d+)\s*mots?\s*(?:max|maximum)\b/u', $flat, $m)) {
            $hits[] = 'longueur: maximum ' . (int) $m[1] . ' mots (limite stricte)';
        } elseif (preg_match('/\b(?:min(?:imum)?|au\s+moins|plus\s+de)\s+(\d+)\s*mots?\b/u', $flat, $m)) {
            $hits[] = 'longueur: minimum ' . (int) $m[1] . ' mots';
        } elseif (preg_match('/\b(?:exactement|environ|autour\s+de|~)\s*(\d+)\s*mots?\b/u', $flat, $m)) {
            $hits[] = 'longueur: ' . (int) $m[1] . ' mots';
        } elseif (preg_match('/\b(\d+)\s*mots?\b/u', $flat, $m)) {
            $hits[] = 'longueur: ' . (int) $m[1] . ' mots';
        }
        if (preg_match('/\b(\d+)\s*caracter(?:e|es)\b/u', $flat, $m)) {
            $hits[] = 'longueur: ' . (int) $m[1] . ' caracteres maximum';
        }
        if (preg_match('/(\d+)\s*(phrase|phrases|ligne|lignes)/u', $flat, $m)) {
            $hits[] = 'longueur: ' . (int) $m[1] . ' ' . $m[2];
        }

        // ── Phrase integration ──
        // Capture the EXACT user-provided phrase to inject into the description.
        // Two passes: (1) prefer text inside quotes (most precise), (2) otherwise
        // grab everything after an integration verb up to a sentence break — that
        // way « ajoute l'île des rêves » works just like « ajoute "l'île des rêves" ».
        $insertPhrase = null;

        // Pass 1 — quoted (any quote style). Look in the ORIGINAL prompt (with accents).
        if (preg_match(
            '/(?:int[eè]gre|inclus|ajoute|ins[eè]re|place|utilise(?:\s+l[\'’]expression|\s+le\s+mot)?|met[s]?(?:\s+l[\'’]expression|\s+le\s+mot)?|avec\s+(?:la\s+phrase|le\s+mot|l[\'’]expression))\s*(?:l[\'’]expression\s*|le\s+mot\s*)?[:\-]?\s*(?:"([^"]{2,120})"|«\s*([^»]{2,120})\s*»|“([^”]{2,120})”|\'([^\']{2,120})\')/iu',
            $prompt,
            $m
        )) {
            $insertPhrase = trim($m[1] ?: $m[2] ?? '') ?: trim($m[3] ?? '') ?: trim($m[4] ?? '');
        }

        // Pass 2 — no quotes. Capture from the verb up to a sentence boundary.
        // « ajoute l'île des rêves » / « intègre le mot soleil et rends ça poétique »
        if ($insertPhrase === null && preg_match(
            '/(?:int[eè]gre|inclus|ajoute|ins[eè]re|place|utilise(?:\s+l[\'’]expression|\s+le\s+mot)?|met[s]?(?:\s+l[\'’]expression|\s+le\s+mot)?)\s+(?:l[\'’]expression\s+|le\s+mot\s+|la\s+phrase\s+)?([^.!?\n,;]{2,120})/iu',
            $prompt,
            $m
        )) {
            $candidate = trim($m[1]);
            // Strip trailing instructions: "et rends ça...", "puis...", "stp", positional hints, tone hints.
            $candidate = preg_replace(
                '/\s+(?:et|puis|ensuite|stp|s\'?il\s+te\s+plait|s\'?il\s+vous\s+plait|au\s+d[eé]but|a\s+la\s+fin|au\s+milieu|dans\s+la\s+phrase|dans\s+le\s+texte|reste\s+\w+|en\s+gardant|en\s+restant)\b.*$/iu',
                '',
                $candidate
            );
            $candidate = trim($candidate, " \t\n\r\0\x0B.,;:");
            // Reject if it's a generic filler word.
            if (mb_strlen($candidate) >= 2 && !preg_match('/^(?:ca|cela|ce|cette|cet|le|la|les|du|de|des|un|une)$/iu', $candidate)) {
                $insertPhrase = $candidate;
            }
        }

        if ($insertPhrase !== null && $insertPhrase !== '') {
            $hits[] = 'integration: garder le texte de base et inserer LITTERALEMENT (mot a mot, accents inclus) l\'expression « ' . $insertPhrase . ' » sans la modifier ni la paraphraser';
        }

        // ── Geographic context — boost the LLM with a precise short tag for the
        // most-cited destinations (Tunisia + a few global hits). The model
        // already knows these, but a one-liner « contexte: … » keeps it on-track
        // and prevents generic clichés. Match in the FULL prompt (texte + demande).
        $places = [
            'djerba'         => 'ile mediterraneenne tunisienne, plages, palmiers, medina d\'Houmt Souk, Sidi Yati, synagogue de la Ghriba',
            'tozeur'         => 'oasis du sud tunisien, palmeraie, briques jaunes ocres, porte du Sahara, Chott el-Jerid, Ong el-Jemel',
            'tataouine'      => 'sud tunisien, ksour berberes, decors de Star Wars (Ksar Ouled Soltane)',
            'matmata'        => 'sud tunisien, maisons troglodytes berberes, paysages lunaires',
            'kairouan'       => 'medina sainte, Grande Mosquee, tapis tisses, citerne aghlabide',
            'sousse'         => 'medina UNESCO, ribat, plages, Port El Kantaoui',
            'hammamet'       => 'baie tranquille, plages, medina fortifiee, jasmin',
            'sidi bou said'  => 'village blanc et bleu sur falaise, Cafe des Nattes, vue sur la baie de Tunis',
            'sidi bou'       => 'village blanc et bleu sur falaise, Cafe des Nattes, vue sur la baie de Tunis',
            'tunis'          => 'capitale, medina UNESCO, souks, Bardo, ruelles de la Hafsia',
            'carthage'       => 'site antique punique et romain, vestiges des thermes d\'Antonin, vue sur le golfe',
            'monastir'       => 'ribat, port, plage, mausolee Bourguiba',
            'mahdia'         => 'cote Est, ville fatimide, plages calmes, port de peche',
            'el jem'         => 'amphitheatre romain colossal, l\'un des mieux conserves au monde',
            'douz'           => 'porte du desert, dunes, dromadaires, festival du Sahara',
            'chenini'        => 'village berbere perche du sud, ksar et greniers fortifies',
            'bizerte'        => 'vieux port pittoresque, kasbah, lacs salés, plages du nord',
            'tabarka'        => 'cote nord, foret de chenes-lieges, corail, festival de jazz',
            'cap bon'        => 'peninsule fertile, vignobles, vergers, plages de Kelibia',
            'paris'          => 'capitale, Seine, Montmartre, Marais, lumiere doree, terrasses, ponts',
            'marrakech'      => 'medina rouge, souks, Jemaa el-Fna, riads, Atlas en toile de fond',
            'fes'            => 'medina labyrinthique UNESCO, tanneries, ruelles bleues',
            'chefchaouen'    => 'medina entierement bleue, montagnes du Rif',
            'rome'           => 'ruelles ocres, fontaines, Trastevere, dolce vita',
            'venise'         => 'canaux, gondoles, palais sur l\'eau, calli etroites',
            'florence'       => 'capitale toscane, Duomo, Ponte Vecchio, Renaissance',
            'barcelone'      => 'Sagrada Familia, Gaudi, ramblas, plage de la Barceloneta',
            'lisbonne'       => 'collines, tramway 28, Alfama, fado, azulejos',
            'istanbul'       => 'Bosphore, Sainte-Sophie, Grand Bazar, traversee Europe-Asie',
            'tokyo'          => 'neons, ruelles d\'Omoide Yokocho, sakura, izakaya',
            'kyoto'          => 'temples, geishas, bambou d\'Arashiyama, jardins zen',
            'bali'           => 'rizieres en terrasses, temples hindous, surf, Ubud',
            'new york'       => 'gratte-ciel, Brooklyn Bridge, Central Park, Times Square',
            'londres'        => 'pubs, Tamise, Camden, brouillard et briques rouges',
        ];
        $found    = [];
        $seenTags = [];
        // Sort needles by length DESC so the longer variant ("sidi bou said") wins
        // over its prefix ("sidi bou"). Dedup by tag so the same place isn't listed twice.
        $sortedPlaces = $places;
        uksort($sortedPlaces, fn($a, $b) => mb_strlen($b) - mb_strlen($a));
        foreach ($sortedPlaces as $needle => $tag) {
            if (str_contains($flat, $needle) && !isset($seenTags[$tag])) {
                $found[] = ucwords($needle) . ' = ' . $tag;
                $seenTags[$tag] = true;
            }
        }
        if ($found) {
            // Cap to avoid prompt bloat when a long list of cities is mentioned.
            $found = array_slice($found, 0, 4);
            $hits[] = 'contexte: ' . implode(' | ', $found);
        }

        if (empty($hits)) {
            return $prompt;
        }

        // Dedupe while preserving order.
        $hits = array_values(array_unique($hits));
        return $prompt . "\nStyle détecté : " . implode(' ; ', $hits) . '.';
    }

    /**
     * Detailed knowledge dossiers for the most-cited destinations on the forum.
     * Each entry feeds the LLM with REAL hotels (3 budget tiers), REAL
     * restaurants/markets, transport options, day-trips, average prices in
     * € and TND, and the best season. The model is instructed to USE these
     * facts as ground truth instead of guessing.
     *
     * Keys are normalized place names (lowercase, no diacritics).
     */
    private function destinationDossiers(): array
    {
        return [
            'djerba' => "DJERBA (île, sud-est tunisien, golfe de Gabès) — Aéroport Djerba-Zarzis (DJE).\n"
                . "  • Saisons : avr-juin et sept-oct = idéal (24-30°C). Juil-août très chaud (38°C+) et bondé. Hiver doux mais mer froide.\n"
                . "  • Quartiers clés : Houmt Souk (capitale, médina + souk), Midoun (vie locale), zone touristique Sidi Mahres / Aghir / Sidi Yati (plages + hôtels).\n"
                . "  • Hôtels (par nuit, 2 personnes) :\n"
                . "    - Économique : Hôtel Marhala (médina Houmt Souk, 35-55 €), Hôtel Djerba Erriadh (40-60 €), maisons d'hôtes Erriadh village.\n"
                . "    - Moyen : Iberostar Mehari Djerba (90-150 €), Vincci Hélios Beach (80-130 €), Seabel Aladin (70-110 €).\n"
                . "    - Haut : Iberostar Selection Royal El Mansour (180-280 €), Hasdrubal Prestige Thalassa (200-320 €), Radisson Blu Palace (180-260 €).\n"
                . "  • Restos/marchés : Restaurant Haroun (Houmt Souk, poisson grillé, 18-30 €/p), El Khalifa (Midoun, couscous), souk de Houmt Souk (mardi/jeudi/dimanche pour épices).\n"
                . "  • À voir : médina de Houmt Souk, synagogue de la Ghriba (Erriadh), Djerbahood (street art, Erriadh), musée Guellala, Borj El Kebir (forteresse), plage de Sidi Yati, Ras R'mel (lagune des flamants roses), île de Flamants.\n"
                . "  • Excursions : Zarzis (1h), Matmata (3h), Tataouine + ksour (3h), traversée vers Boughrara.\n"
                . "  • Transport : louage Houmt Souk-Midoun ~2 TND, taxi à la course ~10-25 TND, location voiture 35-55 €/jour. Bac Ajim-Jorf gratuit (15 min).\n"
                . "  • Coût moyen jour/p hors logement : 25-50 € (repas locaux), 60-100 € (restos touristiques).",

            'tozeur' => "TOZEUR (porte du Sahara, oasis du Jérid) — Aéroport Tozeur-Nefta (TOE).\n"
                . "  • Saisons : oct-avril (15-25°C). Été insupportable (45°C+). Festival du Sahara fin déc.\n"
                . "  • Hôtels :\n"
                . "    - Éco : Hôtel Résidence Niefta (25-45 €), Dar Saida (maison d'hôtes médina, 40-60 €).\n"
                . "    - Moyen : Diar Abou Habibi (60-90 €), Ksar Rouge (70-110 €).\n"
                . "    - Haut : Anantara Tozeur Resort (180-300 €, palmeraie + dunes vue), Palm Beach Palace (140-220 €).\n"
                . "  • Restos : Le Petit Prince (cuisine bédouine, 15-25 €/p), Restaurant du Paradis (couscous oasien), Café Berbère.\n"
                . "  • À voir : médina d'Ouled El Hadef (briques jaunes en relief), palmeraie (200 000 palmiers), musée Dar Cheraït, zoo du désert (Tijani), Belvédère.\n"
                . "  • Excursions IMMANQUABLES (en 4×4, demi-journée 50-90 €/p) : Chott el-Jérid (lac de sel, lever de soleil), Ong el-Jemel (« cou du chameau », décors Star Wars), Mos Espa (Star Wars), oasis de montagne Chebika + Tamerza + Mides (cascade), Nefta (zaouïa Sidi Bou Ali).\n"
                . "  • Transport : 4×4 avec chauffeur indispensable pour le désert. Louage Tunis-Tozeur ~25 TND (8h). Vols intérieurs Tunis-Tozeur 60-90 €.\n"
                . "  • Coût jour/p : 35-60 € (sans 4×4), +40-80 € jour avec excursion désert.",

            'ain drahem' => "AÏN DRAHEM (montagnes de Kroumirie, nord-ouest tunisien, à 25 km de Tabarka) — pas d'aéroport, accès via Tabarka (TBJ) ou Tunis (3h route).\n"
                . "  • Saisons : printemps (mai-juin) pour fleurs et balades, automne (sept-nov) pour champignons et chasse, hiver (déc-fév) parfois sous la neige (rare en Tunisie). Été frais (22-28°C) — refuge anti-canicule.\n"
                . "  • Quartier : un seul village perché à 800 m, ambiance suisse-tunisienne (toits rouges, chalets).\n"
                . "  • Hôtels :\n"
                . "    - Éco : Hôtel Beau Séjour (40-65 €), maisons d'hôtes du village (35-55 €).\n"
                . "    - Moyen : Hôtel Les Chênes (70-110 €, cuisine de gibier réputée), Hôtel Rihana (60-90 €).\n"
                . "    - Haut : Hôtel La Forêt (90-150 €, vue panoramique), Dar Ennassim (boutique).\n"
                . "  • À voir : col des Ruines (vue sur les chênes-lièges), source d'Ain Soltane, forêt de chênes (randonnées, sangliers), Hammam Bourguiba (thermes, à 20 km, 5-15 €).\n"
                . "  • Excursions : Tabarka (25 km, plages + corail + jazz festival juillet), Bulla Regia (site romain souterrain, 1h30), Chemtou (carrières marbre romain), parc national d'El Feija.\n"
                . "  • Spécialités : sanglier rôti, champignons, miel de châtaignier, vins de Thibar.\n"
                . "  • Transport : louage Tunis-Aïn Drahem ~15 TND (3h). Voiture indispensable sur place. Routes sinueuses.\n"
                . "  • Coût jour/p : 30-55 € (repas montagnards inclus), idéal couples et familles fuyant la canicule.",

            'paris' => "PARIS (capitale française) — Aéroports CDG, Orly, Beauvais.\n"
                . "  • Saisons : avril-juin et sept-oct (15-22°C). Évite août (chaud + locaux partis). Décembre = marchés de Noël.\n"
                . "  • Quartiers : Marais (4e, branché), Saint-Germain (6e, chic littéraire), Montmartre (18e, bohème), République/Canal (10-11e, jeune), Latin (5e, étudiant).\n"
                . "  • Hôtels :\n"
                . "    - Éco : Generator Hostel (35-65 €/lit), Hôtel Tiquetonne (90-130 € chambre, 2e), MIJE auberges Marais (50-70 €/lit).\n"
                . "    - Moyen : Hôtel Jeanne d'Arc Marais (130-180 €), Hôtel Du Petit Moulin (170-240 €, Marais), Hôtel Henriette (140-200 €, 13e).\n"
                . "    - Haut : Hôtel Costes (450-700 €), Le Bristol (1000+ €), Le Royal Monceau (700-1200 €).\n"
                . "  • Restos cultes (en plus des grandes tables) : Bouillon Pigalle (18-25 €/p, sans réservation), L'As du Fallafel (rue des Rosiers, 10-15 €), Breizh Café (galettes), Holybelly (brunch), Septime (étoilé, 100+ €/p, résa 3 semaines), Frenchie.\n"
                . "  • Lieux : tour Eiffel (28 € sommet ascenseur), Louvre (22 € en ligne, ferme mardi), musée d'Orsay (16 €), Sainte-Chapelle (11 €), Versailles (TER C, 21 €), Centre Pompidou (15 €).\n"
                . "  • Transport : Métro/RER ticket 2,15 €, carnet 17,35 €. Pass Navigo Easy ou Paris Visite (12,95 €/jour zones 1-3). Vélib'.\n"
                . "  • Astuces : musées gratuits 1er dimanche du mois (oct-mars). Réserver Tour Eiffel + Louvre 2 semaines à l'avance.\n"
                . "  • Coût jour/p : 50 € (auberge + sandwich), 90-130 € (hôtel moyen + 2 restos), 250+ € (hôtel + étoilé).",

            'marrakech' => "MARRAKECH (Maroc, ville rouge) — Aéroport Marrakech-Ménara (RAK).\n"
                . "  • Saisons : oct-nov et mars-mai (20-28°C). Été 40°C+. Hiver doux jour, froid nuit.\n"
                . "  • Quartiers : Médina (souks + riads + Jemaa el-Fna), Guéliz (ville moderne, restos chics), Hivernage (luxe), Palmeraie (resorts).\n"
                . "  • Hôtels (riads = maisons d'hôtes traditionnelles dans la médina) :\n"
                . "    - Éco : Riad Dar Anika (35-60 €), Equity Point Hostel (15-25 €/lit), Riad Le Marocain (40-70 €).\n"
                . "    - Moyen : Riad Yasmine (90-150 €, piscine, Insta-célèbre), Riad BE (110-170 €), Riad El Fenn (haut moyen, 200-300 €).\n"
                . "    - Haut : La Mamounia (palace, 600-1500 €), Royal Mansour (1000+ €), Selman Marrakech (350-600 €).\n"
                . "  • Restos : Nomad (cuisine moderne marocaine, terrasse, 25-40 €/p), Café des Épices, Le Jardin (médina), Al Fassia (Guéliz, cuisine classique 100 % féminine, 30-50 €), street food de Jemaa el-Fna le soir (8-15 €).\n"
                . "  • À voir : Jemaa el-Fna (place mythique, énergie max après 18h), souks (cuir, épices, lampes — négocier −60 %), Madrasa Ben Youssef (50 MAD), Jardin Majorelle + musée YSL (170 MAD combiné), tombeaux Saadiens, palais Bahia (70 MAD), mosquée Koutoubia (extérieur).\n"
                . "  • Excursions : Atlas + vallée d'Ourika (45 €/p journée), désert d'Agafay (dîner berbère sous tente, 35-65 €), Essaouira (3h route, océan), cascades d'Ouzoud (75 €/p journée).\n"
                . "  • Transport : petit taxi ~30-50 MAD course en ville (~3-5 €), grand taxi pour excursions, bus CTM. Louer voiture pas utile en médina.\n"
                . "  • Coût jour/p : 30 € (riad éco + souk), 70-100 € (riad moyen + restos + 1 visite), 250+ € (palace).",

            'rome' => "ROME (Italie, capitale) — Aéroports Fiumicino (FCO), Ciampino (CIA).\n"
                . "  • Saisons : avr-juin et sept-oct (18-26°C). Août : 38°C + boutiques fermées. Hiver doux (10-15°C).\n"
                . "  • Quartiers : Trastevere (ruelles charmantes), Monti (branché), Centro Storico (Panthéon, Navona), Vatican (Prati), Testaccio (food + nuit).\n"
                . "  • Hôtels :\n"
                . "    - Éco : The RomeHello hostel (30-55 €/lit), Hotel Ferraro (Testaccio, 80-120 €).\n"
                . "    - Moyen : Hotel Quirinale (130-200 €), Relais Forum Roma (140-220 €), Hotel Artemide (150-230 €).\n"
                . "    - Haut : Hotel de Russie (450-700 €), Hassler Roma (500-900 €), Hotel Eden (450+ €).\n"
                . "  • Restos : Pizzarium (Bonci, parts au poids, 5-10 €), Da Enzo al 29 (Trastevere, cacio e pepe, résa, 20-30 €/p), Roscioli (carbonara culte, 35-50 €), Trapizzino (street food, 5-8 €), gelato Fatamorgana ou Otaleg.\n"
                . "  • Lieux : Colisée + Forum + Palatin (combo 18 €, résa créneau), Vatican + Sixtine (20 €, résa OBLIGATOIRE), Panthéon (5 €), Galerie Borghese (20 €, résa 1 mois), fontaine de Trevi (gratuit, vide à 7h), place Navone, escaliers Trinité-des-Monts.\n"
                . "  • Excursions : Tivoli (Villa d'Este + Adriana, 1h train), Ostia Antica (40 min train, 12 €), Naples + Pompéi (1h15 TGV).\n"
                . "  • Transport : métro ticket 1,50 €, Roma Pass 72h 52 € (transports + 2 musées + réductions).\n"
                . "  • Coût jour/p : 50-80 € (auberge + pizza), 110-160 € (hôtel moyen + 2 restos), 300+ €.",

            'florence' => "FLORENCE (Toscane) — Aéroport Florence (FLR) ou Pise (PSA, 1h train).\n"
                . "  • Saisons : avril-juin, sept-oct. Été touristique max + chaleur.\n"
                . "  • Hôtels : Plus Florence (auberge, 30-50 €/lit), Hotel Davanzati (centre, 130-190 €), Portrait Firenze (450+ €).\n"
                . "  • À voir : Duomo (gratuit, dôme 30 €), Galerie des Offices (25 € résa), Académie (David, 16 €), Ponte Vecchio, Palazzo Pitti + Boboli (16 €), Piazzale Michelangelo (gratuit, vue).\n"
                . "  • Restos : All'Antico Vinaio (sandwiches, 7-10 €, file), Trattoria Mario (12-18 €), Osteria del Cinghiale Bianco (sanglier, 30-40 €).\n"
                . "  • Excursions : Sienne (1h30 bus), San Gimignano, Pise, Chianti (vignobles, journée).",

            'venise' => "VENISE — Aéroport Marco Polo (VCE).\n"
                . "  • Saisons : avril-juin, sept-oct. Carnaval févr. Acqua alta nov-mars.\n"
                . "  • Hôtels : Generator Venice (auberge île Giudecca, 35-60 €), Hotel Antiche Figure (Cannaregio, 130-200 €), Aman Venice (1500+ €).\n"
                . "  • À voir : Place Saint-Marc + Basilique (gratuit, payant pour Pala d'Oro), Palais des Doges (30 € combo), pont Rialto, Accademia (15 €), Murano + Burano (vaporetto, journée).\n"
                . "  • Restos : Cantina Do Mori (cicchetti 2-3 € pièce), Trattoria Anzolo Raffael, gelato Nico.\n"
                . "  • Transport : vaporetto 9,50 € la course, pass 24h 25 €. Marcher = mieux.\n"
                . "  • Astuce : éviter les restos avec menu touristique sur grand-place. Préférer San Polo / Cannaregio.",

            'sidi bou said' => "SIDI BOU SAÏD (banlieue chic de Tunis, 20 km nord) — accès TGM (train de banlieue) depuis Tunis Marine (45 min, 1,1 TND), ou taxi 15-25 TND.\n"
                . "  • Saisons : avril-juin, sept-oct. Mars frais et fleuri. Été 30-35°C.\n"
                . "  • Pas d'aéroport, dort généralement à Tunis ou y passe la journée.\n"
                . "  • Hôtels (sur place ou proches) : Dar Said (boutique 4*, 120-200 €), La Villa Bleue (180-280 €), Hôtel Bou Fares (60-90 €).\n"
                . "  • À voir : ruelles bleues et blanches, Café des Nattes (thé pignons 4-6 TND), Café Sidi Chabaane (vue mer), Dar el-Annabi (maison-musée 5 TND), galerie A.Gorgi.\n"
                . "  • Restos : Au Bon Vieux Temps (cuisine tunisienne, 20-35 €/p), Dar Zarrouk (vue + cocktails, 25-40 €), Le Pirate (port de plaisance La Marsa, fruits de mer 30-50 €).\n"
                . "  • À combiner : Carthage (4 km, sites antiques 12 TND), La Marsa (plage urbaine), musée Bardo à Tunis (mosaïques romaines, 12 TND).",

            'tunis' => "TUNIS (capitale tunisienne) — Aéroport Tunis-Carthage (TUN).\n"
                . "  • Saisons : avril-juin, sept-oct.\n"
                . "  • Quartiers : Médina (UNESCO), centre-ville (av. Bourguiba), La Marsa et Sidi Bou Saïd (banlieues nord chic), Carthage (sites antiques).\n"
                . "  • Hôtels : Dar El Medina (boutique médina, 100-160 €), Hôtel Belvédère Fourati (40-70 €), Movenpick Gammarth (180-260 €).\n"
                . "  • À voir : médina + souk (matin), Bardo (musée, mosaïques romaines 12 TND), Carthage (ruines puniques + Antonin 12 TND), Sidi Bou Saïd, cathédrale Saint-Vincent.\n"
                . "  • Restos : Dar Belhadj (médina, 20-30 €), Le Petit Beyrouth (centre), Au Lapin (centre, fruits de mer).\n"
                . "  • Transport : Métro léger 0,7 TND, taxi compteur (~2 TND prise + 0,7 TND/km).",

            'kairouan' => "KAIROUAN (4e ville sainte de l'Islam, centre Tunisie) — pas d'aéroport.\n"
                . "  • Visite d'1 jour suffit, depuis Sousse (1h30) ou Tunis (2h30).\n"
                . "  • Hôtels : La Kasbah Boutique (4*, 110-180 €, médina), Hôtel Splendid (35-55 €).\n"
                . "  • À voir : Grande Mosquée Sidi Oqba (la plus ancienne d'Afrique, 8 TND combo), bassins aghlabides, médina + souks de tapis (négocier ferme), zaouïa Sidi Sahbi (« mosquée du Barbier »).\n"
                . "  • Spécialités : tapis kairouanais, makroudh (gâteau dattes-semoule), restaurant Sabra.",

            'hammamet' => "HAMMAMET (côte est tunisienne) — Aéroport Enfidha-Hammamet (NBE).\n"
                . "  • Saisons : mai-oct.\n"
                . "  • Hôtels : Hôtel Sindbad (40-65 €), Méhari Hammamet (70-100 €), La Badira (5*, 180-300 €).\n"
                . "  • À voir : médina fortifiée, plages, Yasmine Hammamet (marina + parc Carthage Land), cap Bon (excursion vignobles + Kelibia).",

            'sousse' => "SOUSSE (Sahel tunisien, perle du Sahel) — Aéroport Monastir (MIR).\n"
                . "  • Médina UNESCO, Ribat (fort), Grande Mosquée, plage de Boujaffar.\n"
                . "  • Hôtels : Mövenpick Sousse (90-150 €), Marhaba Royal Salem, Hôtel Sahara Beach (éco).\n"
                . "  • Excursions : El Jem (amphithéâtre romain, 1h, 12 TND), Monastir (mausolée Bourguiba), Port El Kantaoui.",

            'chefchaouen' => "CHEFCHAOUEN (Maroc, ville bleue, montagnes du Rif) — pas d'aéroport, accès via Fès (4h bus) ou Tanger (3h).\n"
                . "  • Hôtels : Casa Hassan (50-80 €), Riad Cherifa (90-140 €), Lina Ryad & Spa (130-200 €).\n"
                . "  • À voir : médina entièrement bleue, Plaza Uta el-Hammam, kasbah, mosquée espagnole (vue panoramique), cascades d'Akchour (excursion 25 €/p).\n"
                . "  • Restos : Bab Ssour (15-25 €), Café Clock.",

            'fes' => "FÈS (Maroc, capitale spirituelle) — Aéroport Fès-Saïs (FEZ).\n"
                . "  • Hôtels : Riad Maison Bleue (90-160 €), Riad Anata (100-180 €), Palais Faraj (180-300 €).\n"
                . "  • À voir : médina Fès el-Bali (la plus grande zone piétonne du monde), tanneries Chouara (vue depuis terrasses), médersa Bou Inania (20 MAD), porte Bab Boujloud, mausolée Moulay Idriss.\n"
                . "  • À noter : guide local recommandé pour la médina (labyrinthe), 200-400 MAD demi-journée.",

            'tokyo' => "TOKYO (Japon) — Aéroports Narita (NRT) et Haneda (HND).\n"
                . "  • Saisons : sakura mars-avril, momiji nov, été chaud-humide.\n"
                . "  • Quartiers : Shibuya (carrefour), Shinjuku (gratte-ciel + nuit), Asakusa (traditionnel), Harajuku (mode jeune), Ginza (luxe), Akihabara (otaku).\n"
                . "  • Hôtels : capsule Nine Hours (35-60 €), Hotel Gracery Shinjuku (vue Godzilla, 130-200 €), Park Hyatt (Lost in Translation, 600+ €).\n"
                . "  • À voir : Senso-ji (Asakusa, gratuit), parc Yoyogi + Meiji-jingu, observatoire Tokyo Skytree (¥3100), TeamLab Planets (¥3800), marché Tsukiji externe (sushi).\n"
                . "  • Restos : Ichiran (ramen 12 €), conveyor sushi Genki, izakaya d'Omoide Yokocho.\n"
                . "  • Transport : JR Pass 7j 50 000 ¥ (~310 €) si Tokyo + Kyoto/Osaka. Suica/Pasmo recharge.",
        ];
    }

    /**
     * Enriches an itinerary prompt with: detected destination(s) + their full
     * dossier, parsed duration / budget / group size / season. The result is
     * appended after the user's free-form ask so the LLM uses real, accurate
     * facts instead of hallucinating.
     */
    private function enrichItineraryPrompt(string $prompt): string
    {
        $haystack = ' ' . mb_strtolower($prompt) . ' ';
        $flat = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $haystack) ?: $haystack;
        $flat = strtolower($flat);

        $hints = [];

        // ── Group size : « 2 personnes », « famille de 4 », « couple », « solo », « entre amis »
        if (preg_match('/\b(\d+)\s*(?:personne|personnes|adulte|adultes|voyageur|voyageurs|p\b|pax)\b/u', $flat, $m)) {
            $hints[] = 'voyageurs: ' . (int) $m[1];
        } elseif (preg_match('/\bfamille(?:\s+de\s+(\d+))?\b/u', $flat, $m)) {
            $hints[] = 'voyageurs: famille' . (isset($m[1]) ? ' (' . (int) $m[1] . ' personnes)' : ' (4 personnes par défaut)') . ' — inclure activités enfants';
        } elseif (str_contains($flat, 'couple') || str_contains($flat, 'lune de miel') || str_contains($flat, 'amoureux')) {
            $hints[] = 'voyageurs: couple — privilégier ambiances romantiques, dîners aux chandelles';
        } elseif (str_contains($flat, ' solo ') || str_contains($flat, 'seul ') || str_contains($flat, 'voyage solo')) {
            $hints[] = 'voyageurs: solo — auberges sociales, activités de groupe';
        } elseif (str_contains($flat, 'entre amis') || str_contains($flat, 'groupe d\'amis') || str_contains($flat, 'avec mes amis')) {
            $hints[] = 'voyageurs: groupe amis — sorties soir, activités collectives';
        }

        // ── Departure city (helps with flights) ──
        foreach (['tunis', 'paris', 'lyon', 'marseille', 'casablanca', 'alger', 'bruxelles', 'geneve', 'londres'] as $city) {
            if (preg_match('/\b(?:depuis|au\s+depart\s+de|partant\s+de|de)\s+' . preg_quote($city, '/') . '\b/u', $flat)) {
                $hints[] = 'depart: ' . ucfirst($city);
                break;
            }
        }

        // ── Dates / season ──
        $monthMap = [
            'janvier' => 'janv', 'fevrier' => 'fev', 'mars' => 'mars', 'avril' => 'avril',
            'mai' => 'mai', 'juin' => 'juin', 'juillet' => 'juil', 'aout' => 'aout',
            'septembre' => 'sept', 'octobre' => 'oct', 'novembre' => 'nov', 'decembre' => 'dec',
        ];
        foreach ($monthMap as $needle => $tag) {
            if (str_contains($flat, $needle)) {
                $hints[] = 'periode: ' . $tag . ' (adapter saison: tarifs, météo, foule)';
                break;
            }
        }
        if (preg_match('/\bete\b/u', $flat))    $hints[] = 'saison: ete (juin-aout) — adapter chaleur';
        elseif (preg_match('/\bhiver\b/u', $flat)) $hints[] = 'saison: hiver (dec-fev)';
        elseif (preg_match('/\bautomne\b/u', $flat)) $hints[] = 'saison: automne (sept-nov)';
        elseif (preg_match('/\bprintemps\b/u', $flat)) $hints[] = 'saison: printemps (mars-mai)';

        // ── Destination dossier(s) ──
        $dossiers = $this->destinationDossiers();
        $found    = [];
        $seenTags = [];
        $sortedDossiers = $dossiers;
        uksort($sortedDossiers, fn($a, $b) => mb_strlen($b) - mb_strlen($a));
        foreach ($sortedDossiers as $needle => $dossier) {
            if (str_contains($flat, $needle) && !isset($seenTags[$needle])) {
                $found[$needle] = $dossier;
                $seenTags[$needle] = true;
                if (count($found) >= 3) break; // au plus 3 dossiers détaillés (sinon prompt trop long)
            }
        }

        $output = $prompt;
        if (!empty($hints)) {
            $output .= "\n\nContexte détecté : " . implode(' ; ', $hints) . '.';
        }
        if (!empty($found)) {
            $output .= "\n\nDOSSIER DESTINATION (vérité terrain — utilise ces faits, ne les invente pas) :\n";
            foreach ($found as $place => $dossier) {
                $output .= "\n[" . strtoupper($place) . "]\n" . $dossier . "\n";
            }
        }

        return $output;
    }

    private function maxTokensFor(string $type): int
    {
        return match ($type) {
            'itinerary'   => 3200,
            'description' => 900,
            'destination' => 400,
            'comment'     => 180,
            default       => 500,
        };
    }

    private function temperatureFor(string $type): float
    {
        // Itineraries must be factual (real place names) — lower = less hallucination.
        return $type === 'itinerary' ? 0.5 : 0.9;
    }

    // ============================================================
    // Real AI providers
    // ============================================================

    private function callPollinations(HttpClientInterface $http, string $system, string $user, int $maxTokens = 900, float $temperature = 0.85): ?string
    {
        // Pollinations — free, no auth. We hit the OpenAI-compatible endpoint so the
        // response is always a proper chat-completion JSON (choices[0].message.content).
        // We force a non-reasoning model by default to avoid raw chain-of-thought leaks.
        $models = array_values(array_unique(array_filter([
            $_ENV['POLLINATIONS_MODEL'] ?? null,
            'openai',       // stable avec reasoning_effort=low
            'openai-fast',
            'llama',
        ])));

        foreach ($models as $model) {
            try {
                // Pour les longues sorties (itinéraires) on bascule en reasoning_effort=low :
                // le dossier injecté fournit déjà les faits, le modèle n'a plus à « raisonner »
                // pour deviner — il rédige. Gain : 3-5x plus rapide, qualité comparable.
                $reasoning = $maxTokens >= 1500 ? 'low' : 'medium';
                $r = $http->request('POST', 'https://text.pollinations.ai/openai', [
                    'headers' => ['Content-Type' => 'application/json'],
                    'json'    => [
                        'model'            => $model,
                        'temperature'      => $temperature,
                        'max_tokens'       => $maxTokens,
                        'reasoning_effort' => $reasoning,
                        'seed'             => random_int(1, 1_000_000),
                        'private'          => true,
                        'messages'         => [
                            ['role' => 'system', 'content' => $system],
                            ['role' => 'user',   'content' => $user],
                        ],
                    ],
                    // Timeout court pour fail-fast et passer au modèle suivant si l'API rame.
                    'timeout'      => 35,
                    'max_duration' => 50,
                ]);
                $status = $r->getStatusCode();
                if ($status < 200 || $status >= 300) continue;
                $body = $r->getContent(false);
                if ($body === '' || $body === null) continue;

                $text = $this->extractPollinationsContent($body);
                if ($text !== null && $text !== '' && !$this->looksLikeReasoningLeak($text)) {
                    return $text;
                }
            } catch (\Throwable $e) {
                // try next model
            }
        }
        return null;
    }

    /**
     * Extrait le texte final d'une réponse Pollinations, qui peut revenir sous plusieurs formes :
     *   - JSON chat-completion OpenAI : choices[0].message.content
     *   - Objet message simple        : {role, content}
     *   - Objet reasoning sans content: {role, reasoning_content, tool_calls} → on rejette
     *   - Texte brut.
     */
    private function extractPollinationsContent(string $body): ?string
    {
        $trim = ltrim($body);
        if ($trim === '') return null;

        if ($trim[0] === '{' || $trim[0] === '[') {
            $data = json_decode($body, true);
            if (is_array($data)) {
                $candidate = $data['choices'][0]['message']['content']
                    ?? $data['message']['content']
                    ?? $data['content']
                    ?? $data['response']
                    ?? $data['text']
                    ?? null;

                // Rejette les réponses "reasoning-only" qui n'ont pas de content propre.
                if (!is_string($candidate) || trim($candidate) === '') {
                    return null;
                }
                return $candidate;
            }
            // JSON invalide — on garde tel quel seulement si ça ne ressemble pas à un leak.
        }

        return $body;
    }

    /**
     * Détecte les réponses qui contiennent un raisonnement brut (chain-of-thought)
     * plutôt que la description finale. Si détecté, on saute ce provider.
     */
    private function looksLikeReasoningLeak(string $text): bool
    {
        $t = mb_strtolower($text);
        // Indices typiques d'un raisonnement brut : balises de rôle, patterns de pensée en anglais,
        // gros blocs de méta-analyse, ou JSON parasite renvoyé tel quel.
        $flags = [
            '"role":"assistant"',
            'reasoning_',
            '"tool_calls"',
            "let's draft",
            'we need to',
            'must avoid',
            'substring',
            'banned word',
            'chain-of-thought',
        ];
        $hits = 0;
        foreach ($flags as $f) if (str_contains($t, $f)) $hits++;
        return $hits >= 1;
    }

    private function callGroq(HttpClientInterface $http, string $system, string $user, int $maxTokens = 350, float $temperature = 0.9): ?string
    {
        $key = $_ENV['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY') ?: null;
        if (!$key) return null;
        $r = $http->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'headers' => ['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'],
            'json'    => [
                'model'       => $_ENV['GROQ_MODEL'] ?? 'llama-3.3-70b-versatile',
                'temperature' => $temperature,
                'max_tokens'  => $maxTokens,
                'messages'    => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user',   'content' => $user],
                ],
            ],
            'timeout' => 20,
        ]);
        $data = $r->toArray(false);
        return $data['choices'][0]['message']['content'] ?? null;
    }

    private function callOpenRouter(HttpClientInterface $http, string $system, string $user, int $maxTokens = 350, float $temperature = 0.9): ?string
    {
        $key = $_ENV['OPENROUTER_API_KEY'] ?? getenv('OPENROUTER_API_KEY') ?: null;
        if (!$key) return null;
        $r = $http->request('POST', 'https://openrouter.ai/api/v1/chat/completions', [
            'headers' => ['Authorization' => 'Bearer ' . $key, 'Content-Type' => 'application/json'],
            'json'    => [
                'model'       => $_ENV['OPENROUTER_MODEL'] ?? 'meta-llama/llama-3.3-70b-instruct:free',
                'temperature' => $temperature,
                'max_tokens'  => $maxTokens,
                'messages'    => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user',   'content' => $user],
                ],
            ],
            'timeout' => 25,
        ]);
        $data = $r->toArray(false);
        return $data['choices'][0]['message']['content'] ?? null;
    }

    private function callGemini(HttpClientInterface $http, string $system, string $user, int $maxTokens = 350, float $temperature = 0.9): ?string
    {
        $key = $_ENV['GEMINI_API_KEY'] ?? getenv('GEMINI_API_KEY') ?: null;
        if (!$key) return null;
        $model = $_ENV['GEMINI_MODEL'] ?? 'gemini-1.5-flash-latest';
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/' . $model . ':generateContent?key=' . $key;
        $r = $http->request('POST', $url, [
            'headers' => ['Content-Type' => 'application/json'],
            'json'    => [
                'systemInstruction' => ['parts' => [['text' => $system]]],
                'contents'          => [['role' => 'user', 'parts' => [['text' => $user]]]],
                'generationConfig'  => ['temperature' => $temperature, 'maxOutputTokens' => $maxTokens],
            ],
            'timeout' => 20,
        ]);
        $data = $r->toArray(false);
        return $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
    }

    // ============================================================
    // Cleaning & banned-words sanitisation
    // ============================================================

    private function cleanupAiText(string $text, string $type = 'description'): string
    {
        $text = trim($text);
        // Strip markdown code fences parfois ajoutés par les LLMs.
        $text = preg_replace('/^```(?:markdown|md)?\s*\n?/i', '', $text);
        $text = preg_replace('/\n?```\s*$/i', '', $text);
        $text = trim($text);

        if ($type === 'itinerary' || $type === 'destination') {
            // Préserver les retours à la ligne (structure Markdown).
            $text = preg_replace('/[ \t]+/u', ' ', $text);
            $text = preg_replace("/\r\n?|\r/u", "\n", $text);
            $text = preg_replace("/\n{3,}/u", "\n\n", $text);
            return trim($text);
        }

        $text = preg_replace('/^[`"\'“”«\s]+|[`"\'“”»\s]+$/u', '', $text);
        $text = preg_replace('/\s+/u', ' ', $text);
        return trim($text);
    }

    /**
     * Map tous les mots français courants dont la sous-chaîne entre en collision
     * avec BANNED_WORDS (validation substring, cf. NoBannedWordsValidator).
     * Exécuté sur TOUT texte renvoyé — IA comme local — pour garantir qu'il passe
     * le filtre client ET serveur.
     */
    private function avoidBanned(string $text): string
    {
        $map = [
            // "con" / "conne"  —  mots FR courants contenant cette sous-chaîne
            '/\brencontres?\b/iu'                       => 'échanges',
            '/\bse rencontrer\b/iu'                     => 'se retrouver',
            '/\brencontrer\b/iu'                        => 'découvrir',
            '/\breconnecter?\b/iu'                      => 'se ressourcer',
            '/\breconnecte\b/iu'                        => 'ressource',
            '/\bconfortables?\b/iu'                     => 'agréables',
            '/\bconfortablement\b/iu'                   => 'agréablement',
            '/\bconfort\b/iu'                           => 'bien-être',
            '/\bconvivial(es|s|e)?\b/iu'                => 'chaleureuses',
            '/\bconvivialité\b/iu'                      => 'chaleur',
            '/\bconstruit(es|s|e)?\b/iu'                => 'bâti',
            '/\bse construit\b/iu'                      => 'se bâtit',
            '/\bconstruire\b/iu'                        => 'bâtir',
            '/\bconstruction\b/iu'                      => 'bâtisse',
            '/\bconjuguent?\b/iu'                       => 's\'allient',
            '/\bse conjugue(nt)?\b/iu'                  => 's\'allient',
            '/\bconnaître\b/iu'                         => 'découvrir',
            '/\bconnait(re)?\b/iu'                      => 'découvre',
            '/\bconnaissance\b/iu'                      => 'savoir',
            '/\bcontemplati(f|ve|ves|fs)\b/iu'          => 'admiratif',
            '/\bcontemplation\b/iu'                     => 'admiration',
            '/\bcontempler\b/iu'                        => 'admirer',
            '/\bincontournables?\b/iu'                  => 'immanquables',
            '/\bcontact\b/iu'                           => 'échange',
            '/\bcontacts?\b/iu'                         => 'échanges',
            '/\bcontinu(e|es|ent|er|ant)?\b/iu'         => 'se prolonge',
            '/\bcontinuation\b/iu'                      => 'suite',
            '/\bconduis(e|es|ent|it|ons|ez)?\b/iu'      => 'mène',
            '/\bconduite\b/iu'                          => 'trajet',
            '/\bconserve(r|nt|s|z)?\b/iu'               => 'garde',
            '/\bconcentr(e|es|ent|é|ée|és|ées)\b/iu'    => 'rassemble',
            '/\bconcentration\b/iu'                     => 'intensité',
            '/\bcontes?\b/iu'                           => 'récits',
            '/\bcompagnons?\b/iu'                       => 'compagnons de route',
            '/\bseconde?s?\b/iu'                        => 'instants',
            '/\bsecond(e|es|s)?\b/iu'                   => 'autres',
            // "tue" / "tuer"
            '/\bponctu(e|ent|é|ée|és|ées)\b/iu'         => 'rythment',
            '/\bsitu(é|ée|és|ées|e|ent)\b/iu'           => 'posé',
            '/\baccentu(e|ent|é|ée|és|ées)\b/iu'        => 'appuie',
            '/\bstatue?s?\b/iu'                         => 'sculpture',
            // "bite"
            '/\bhabite(nt|z|s|r|rai|rez)?\b/iu'         => 'vit',
            '/\bhabitants?\b/iu'                        => 'locaux',
            '/\bhabitation\b/iu'                        => 'demeure',
            // "viol"
            '/\bviolets?\b/iu'                          => 'pourpre',
            '/\bviolon(s|istes)?\b/iu'                  => 'musique',
            // "foutre"
            '/\ben foutre\b/iu'                         => '',
            // "chier" / "chiant"
            '/\barchi(ver|vé|ves|vée)?\b/iu'            => 'garder',
            '/\bconversation(s)?\b/iu'                  => 'échanges',
            '/\bconversant(e|s|es)?\b/iu'               => 'discutant',
            '/\bconversati(f|ve|fs|ves)\b/iu'           => 'engageant',
            '/\bconseil(s|ler|lère|lers|lés|lée|lées|lant)?\b/iu' => 'astuce',
            '/\bconseille(r|s|nt|z|rai|rais|rait)?\b/iu' => 'recommande',
            '/\bconclusi(f|ve|fs|ves|on|ons)\b/iu'       => 'finale',
            '/\bconclure\b/iu'                           => 'clore',
            '/\bconclu(t|s|e|es|ent|ais|ait)?\b/iu'      => 'clos',
            '/\bconcert(s|o|os|oïste)?\b/iu'             => 'spectacle',
            '/\bconcertation\b/iu'                       => 'dialogue',
            '/\bcondition(s|nel|nelle|nés|nées)?\b/iu'   => 'modalités',
            '/\bconditionn(e|es|ent|é|ée|és|ées|er|ement)\b/iu' => 'module',
            '/\bconfiance\b/iu'                          => 'assurance',
            '/\bconfirm(e|es|ent|é|ée|és|ées|er|ation)\b/iu' => 'valide',
            '/\bcontin(u|ue|ues|uent|uer|uant|ué|uée|ués|uées|ue|ental|entale|entaux|entales|ent|ents)\b/iu' => 'poursuit',
            '/\bcontinent(s)?\b/iu'                      => 'territoire',
            '/\bconcentré(e|s|es)?\b/iu'                 => 'mélange',
            '/\bconcret(e|es|s)?\b/iu'                   => 'réel',
            '/\bconcept(s|uel|uels|uelle|uelles)?\b/iu'  => 'idée',
            '/\bcontre\b/iu'                             => 'face à',
            '/\bcontraire(s|ment)?\b/iu'                 => 'inverse',
            '/\bcontenu(s|e|es)?\b/iu'                   => 'programme',
            '/\bcontient\b/iu'                           => 'comprend',
            '/\bcontenir\b/iu'                           => 'renfermer',
            '/\bcontexte(s)?\b/iu'                       => 'cadre',
            '/\bdécontract(e|es|ent|er|ant|ions|iez)?\b/iu' => 'détend',
            '/\bdécontract[ée](e|s|es)?\b/iu'            => 'détendu',
            '/\braconte(r|s|nt|z|rai|rais|rait|ons|ez|ant)?\b/iu' => 'retrace',
            '/\bracont[ée](e|s|es)?\b/iu'                => 'retracé',
            '/\béconomiques?\b/iu'                       => 'petit budget',
            '/\béconomi(e|es|que|ques|ser|sez)\b/iu'     => 'budget',
            '/\bconsomm(e|es|ent|er|ation|ations|ateur|ateurs|atrice|atrices|é|ée|és|ées|ant|ez)\b/iu' => 'usage',
            '/\bconsid[èéê]r(e|es|ent|er|é|ée|és|ées|ant|ez|ablement|able|ables)\b/iu' => 'tient',
            '/\bcons[ée]quent(e|s|es)?\b/iu'             => 'important',
            '/\bcons[ée]quence(s)?\b/iu'                 => 'résultat',
            '/\bconform(e|es|ément|ité|és|ées)\b/iu'     => 'aligné',
            '/\bconsacr(e|es|ent|er|é|ée|és|ées|ant|ez)\b/iu' => 'dédie',
            '/\bcont[ée]\b/iu'                           => 'récit',
            '/\bcontrée(s)?\b/iu'                        => 'région',
            '/\biconique(s|ment)?\b/iu'                  => 'emblématique',
            '/\bicôn(e|es|iser)?\b/iu'                   => 'figure',
            '/\bicône(s)?\b/iu'                          => 'figure',
            '/\bcondens[ée](e|s|es)?\b/iu'               => 'resserré',
            '/\bconserv(e|es|er|é|ée|és|ées|ant|ation)\b/iu' => 'garde',
            '/\bconfectionn(e|es|er|é|ée|és|ées|ant|ation)\b/iu' => 'prépare',
            '/\bconfection(s)?\b/iu'                     => 'préparation',
            '/\bconfluence(s)?\b/iu'                     => 'jonction',
            '/\bconfluent(s)?\b/iu'                      => 'jonction',
        ];

        foreach ($map as $pattern => $replacement) {
            $text = preg_replace($pattern, $replacement, $text);
        }

        // Filet final : pour chaque mot banni qui reste en sous-chaîne, on remplace le
        // mot hôte complet par un synonyme contextuel neutre. Préférable à « … »
        // pour garder une phrase lisible, tout en franchissant le validateur serveur.
        $neutralFor = [
            'con'     => 'échanges',
            'conne'   => 'échanges',
            'tue'     => 'moment',
            'tuer'    => 'atteindre',
            'viol'    => 'couleur',
            'viole'   => 'couleur',
            'bite'    => 'demeure',
            'foutre'  => 'glisser',
            'chier'   => 'grincer',
            'chiant'  => 'grinçant',
            'chiante' => 'grinçante',
            'merde'   => 'souci',
            'pute'    => 'étoile',
            'putes'   => 'étoiles',
        ];
        $lower = mb_strtolower($text, 'UTF-8');
        foreach (NoBannedWords::BANNED_WORDS as $w) {
            if ($w === '' || mb_stripos($lower, $w, 0, 'UTF-8') === false) continue;
            $replacement = $neutralFor[$w] ?? 'instant';
            $text = preg_replace('/\p{L}*' . preg_quote($w, '/') . '\p{L}*/iu', $replacement, $text);
            $lower = mb_strtolower($text, 'UTF-8');
        }

        // Compacte les espaces mais conserve les retours à la ligne (structure Markdown).
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $text = preg_replace("/\n[ \t]+/u", "\n", $text);
        $text = preg_replace("/[ \t]+\n/u", "\n", $text);
        $text = preg_replace("/\n{3,}/u", "\n\n", $text);
        return trim($text);
    }

    private function containsBanned(string $text): bool
    {
        $lower = mb_strtolower($text, 'UTF-8');
        foreach (NoBannedWords::BANNED_WORDS as $w) {
            if ($w !== '' && mb_stripos($lower, $w, 0, 'UTF-8') !== false) return true;
        }
        return false;
    }

    // ============================================================
    // Smart local fallback (no API required)
    // ============================================================

    private function smartLocal(string $type, string $prompt): string
    {
        return match ($type) {
            'description' => $this->localDescription($prompt),
            'comment'     => $this->localComment($prompt),
            'destination' => $this->localDestinations($prompt),
            'itinerary'   => $this->localItinerary($prompt),
            default       => "Explorez le monde avec notre communauté de voyageurs passionnés.",
        };
    }

    // ============================================================
    // Smart local itinerary (used when all LLMs fail)
    // ============================================================

    private function localItinerary(string $prompt): string
    {
        $p = mb_strtolower($prompt, 'UTF-8');

        // --- Durée ---
        // Normalise les mots-nombres français ("une semaine" → "1 semaine") pour
        // que les regex ci-dessous capturent aussi les durées écrites en lettres.
        $wordNums = [
            'une'      => 1, 'un'        => 1,
            'deux'     => 2, 'trois'     => 3, 'quatre'    => 4, 'cinq'     => 5,
            'six'      => 6, 'sept'      => 7, 'huit'      => 8, 'neuf'     => 9, 'dix' => 10,
            'onze'     => 11, 'douze'    => 12, 'treize'   => 13, 'quatorze' => 14, 'quinze' => 15,
            'vingt'    => 20, 'trente'   => 30,
            'demi-'    => 0,
        ];
        $pNorm = $p;
        foreach ($wordNums as $w => $n) {
            $pNorm = preg_replace('/\b' . preg_quote($w, '/') . '\b/u', (string) $n, $pNorm);
        }

        $days = 3;
        $matched = false;
        if (preg_match('/(\d+)\s*semaines?/u', $pNorm, $m)) {
            $days = (int) $m[1] * 7;
            $matched = true;
        } elseif (preg_match('/\b(?:quinzaine)\b/u', $pNorm)) {
            $days = 15;
            $matched = true;
        } elseif (preg_match('/(\d+)\s*(?:jours?|j\b|journ[ée]es?)/u', $pNorm, $m)) {
            $days = (int) $m[1];
            $matched = true;
        } elseif (preg_match('/(\d+)\s*nuits?/u', $pNorm, $m)) {
            $days = (int) $m[1];
            $matched = true;
        } elseif (preg_match('/\blong\s*week[- ]?end\b/u', $pNorm)) {
            $days = 3;
            $matched = true;
        } elseif (preg_match('/\b(?:week[- ]?end|weekend)\b/u', $pNorm)) {
            $days = 2;
            $matched = true;
        } elseif (preg_match('/\b(?:mois)\b/u', $pNorm)) {
            $days = 21;
            $matched = true;
        }
        $days = max(1, min(21, $days));

        // --- Budget ---
        $budget = 'moyen';
        if ($this->hasAny($p, ['pas cher', 'petit budget', 'économique', 'economique', 'backpack', 'routard', 'auberge', 'hostel'])) {
            $budget = 'eco';
        } elseif ($this->hasAny($p, ['luxe', 'palace', 'prestige', 'haut de gamme', '5 étoiles', '5 etoiles', 'premium'])) {
            $budget = 'lux';
        }

        // --- Destination ---
        $db = $this->itineraryDatabase();
        $destKey = null;
        foreach ($db as $k => $_) {
            if ($k === 'default') continue;
            if (str_contains($p, $k)) { $destKey = $k; break; }
        }
        $dest = $db[$destKey ?? 'default'];
        if ($destKey === null) {
            // Essaie une capture générique « à XXX » / « pour XXX ». Limite à un ou
            // deux mots commençant par une majuscule (ex : « Rome », « New York »).
            if (preg_match('/\b(?:à|a|pour|vers|sur)\s+([A-ZÀ-ÿ][\p{L}\-]+(?:\s+[A-ZÀ-ÿ][\p{L}\-]+)?)/u', $prompt, $m)) {
                $dest['name'] = trim($m[1]);
            }
        }

        // --- Budgets journaliers ---
        $bHotel = ['eco' => [25, 45], 'moyen' => [70, 120], 'lux' => [220, 500]][$budget];
        $bFood  = ['eco' => [15, 25], 'moyen' => [30, 55],  'lux' => [90, 180]][$budget];
        $bAct   = ['eco' => [5, 15],  'moyen' => [20, 40],  'lux' => [80, 200]][$budget];
        $bTrans = ['eco' => [3, 8],   'moyen' => [8, 18],   'lux' => [30, 80]][$budget];

        $nights = max(1, $days - 1);
        $hotelTot = [$bHotel[0] * $nights, $bHotel[1] * $nights];
        $foodTot  = [$bFood[0] * $days,   $bFood[1] * $days];
        $actTot   = [$bAct[0] * $days,    $bAct[1] * $days];
        $transTot = [$bTrans[0] * $days,  $bTrans[1] * $days];
        $total    = [$hotelTot[0] + $foodTot[0] + $actTot[0] + $transTot[0],
                     $hotelTot[1] + $foodTot[1] + $actTot[1] + $transTot[1]];

        $budgetLabel = ['eco' => 'petit budget', 'moyen' => 'budget moyen', 'lux' => 'haut de gamme'][$budget];

        // --- Construction du Markdown ---
        $out  = "## 🗺️ " . $dest['name'] . " — " . $days . " jour" . ($days > 1 ? 's' : '') . " · " . $budgetLabel . "\n";
        $out .= $dest['intro'] . " Itinéraire pensé pour un séjour à " . $budgetLabel . " sur " . $days . " jour" . ($days > 1 ? 's' : '') . ".\n\n";

        $out .= "## 📅 Planning jour par jour\n";
        $themes = $dest['day_themes'] ?? ['Centre historique', 'Quartiers et vie locale', 'Gastronomie et musées', 'Excursion', 'Shopping et détente', 'Nature alentour', 'Découvertes insolites'];
        $slots = $dest['slots'] ?? [];
        $perDay = 4;
        for ($i = 0; $i < $days; $i++) {
            $theme = $themes[$i % count($themes)];
            $out .= "### Jour " . ($i + 1) . " — " . $theme . "\n";
            $matin  = $slots[$i * $perDay + 0] ?? ['Balade dans le quartier emblématique', 'explore à pied les ruelles et places centrales', '2 h', 'gratuit'];
            $midi   = $slots[$i * $perDay + 1] ?? ['Déjeuner local', 'un bistrot de quartier', $budget === 'eco' ? '8–15 €' : ($budget === 'moyen' ? '15–25 €' : '40–80 €')];
            $aprem  = $slots[$i * $perDay + 2] ?? ['Musée ou site marquant', 'réserve en ligne pour éviter la file', '2–3 h', $budget === 'eco' ? 'gratuit ou ~5 €' : '12–20 €'];
            $soir   = $slots[$i * $perDay + 3] ?? ['Dîner dans un quartier animé', 'cuisine locale avec ambiance', $budget === 'eco' ? '12–20 €' : ($budget === 'moyen' ? '25–45 €' : '60–150 €')];
            $out .= "- **Matin** : " . $matin[0] . " — " . $matin[1] . (isset($matin[2]) ? " · " . $matin[2] : "") . (isset($matin[3]) ? " · " . $matin[3] : "") . "\n";
            $out .= "- **Midi** : " . $midi[0] . " — " . $midi[1] . (isset($midi[2]) ? " · " . $midi[2] : "") . "\n";
            $out .= "- **Après-midi** : " . $aprem[0] . " — " . $aprem[1] . (isset($aprem[2]) ? " · " . $aprem[2] : "") . (isset($aprem[3]) ? " · " . $aprem[3] : "") . "\n";
            $out .= "- **Soir** : " . $soir[0] . " — " . $soir[1] . (isset($soir[2]) ? " · " . $soir[2] : "") . "\n\n";
        }

        $out .= "## ⭐ Lieux immanquables\n";
        foreach ($dest['highlights'] as $h) {
            $out .= "- **" . $h[0] . "** — " . $h[1] . "\n";
        }
        $out .= "\n";

        // --- Hébergements réels adaptés au budget ---
        $hotels = $dest['hotels'][$budget] ?? $dest['hotels']['moyen'] ?? null;
        if ($hotels) {
            $out .= "## 🏨 Où dormir (" . $budgetLabel . ")\n";
            foreach ($hotels as $h) {
                $out .= "- **" . $h[0] . "** — " . $h[1] . "\n";
            }
            $out .= "\n";
        }

        // --- Adresses gourmandes ---
        if (!empty($dest['food'])) {
            $out .= "## 🍽️ Où manger\n";
            foreach ($dest['food'] as $f) {
                $out .= "- **" . $f[0] . "** — " . $f[1] . "\n";
            }
            $out .= "\n";
        }

        $out .= "## 💰 Budget estimé (par personne)\n";
        $out .= "- Hébergement : " . $bHotel[0] . "–" . $bHotel[1] . " €/nuit × " . $nights . " ≈ " . $hotelTot[0] . "–" . $hotelTot[1] . " €\n";
        $out .= "- Repas : " . $bFood[0] . "–" . $bFood[1] . " €/jour × " . $days . " ≈ " . $foodTot[0] . "–" . $foodTot[1] . " €\n";
        $out .= "- Transports sur place : ≈ " . $transTot[0] . "–" . $transTot[1] . " €\n";
        $out .= "- Activités / entrées : ≈ " . $actTot[0] . "–" . $actTot[1] . " €\n";
        $out .= "- **Total estimé : ≈ " . $total[0] . " € – " . $total[1] . " €** (hors vol international)\n\n";

        $out .= "## 💡 Astuces pratiques\n";
        foreach ($dest['tips'] as $t) {
            $out .= "- " . $t . "\n";
        }

        return trim($out);
    }

    private function itineraryDatabase(): array
    {
        return [
            'rome' => [
                'name'   => 'Rome',
                'intro'  => "Rome mêle Antiquité, Renaissance et vie de quartier dans un mélange unique.",
                'day_themes' => ['Rome antique', 'Vatican et baroque', 'Trastevere et gastronomie', 'Villas, parcs et musées', 'Excursion : Ostia Antica', 'Quartiers modernes et street food'],
                'highlights' => [
                    ['Colisée & Forum Romain', 'le cœur antique, à visiter tôt le matin pour éviter la file'],
                    ['Vatican (Basilique + Musées)', 'la Chapelle Sixtine vaut chaque minute de file'],
                    ['Panthéon', 'chef-d\'œuvre d\'architecture romaine, entrée gratuite'],
                    ['Fontaine de Trevi', 'magique à l\'aube, bondée en journée'],
                    ['Trastevere', 'quartier aux ruelles pavées, idéal pour le dîner'],
                    ['Villa Borghèse', 'parc verdoyant avec la Galleria Borghese (réservation obligatoire)'],
                    ['Piazza Navona & Campo de\' Fiori', 'places baroques et marché du matin'],
                    ['Pizzarium (via della Meloria)', 'la pizza al taglio de référence à Rome'],
                ],
                'slots' => [
                    ['Colisée + Forum Romain', 'billet combiné 24h, réserve en ligne', '3 h', '18 €'],
                    ['Pizza al taglio', 'Pizzarium (via della Meloria) ou Forno Campo de\' Fiori', '8–12 €'],
                    ['Palatin & Circus Maximus', 'panorama sur les ruines, peu de foule l\'après-midi', '2 h', 'inclus dans le billet'],
                    ['Trattoria du Trastevere', 'cacio e pepe et vin de la maison', '20–30 €'],
                    ['Musées du Vatican + Chapelle Sixtine', 'entre à 8 h ou réserve un créneau soir', '3 h', '20 €'],
                    ['Panini chez Panella ou All\'Antico Vinaio', 'sandwich à emporter', '7–10 €'],
                    ['Basilique Saint-Pierre + coupole', 'la montée vaut la vue sur Rome', '2 h', '10 € la coupole'],
                    ['Dîner à Prati', 'quartier chic à côté du Vatican', '25–40 €'],
                    ['Marché de Campo de\' Fiori + Piazza Navona', 'flâne et photos, petit café', '2 h', 'gratuit'],
                    ['Supplì et pasta chez Roscioli', 'institution romaine', '15–25 €'],
                    ['Panthéon + quartier Juif (Ghetto)', 'architecture antique + carciofi alla giudia', '2 h', 'gratuit'],
                    ['Dîner dans le Trastevere', 'ambiance chaleureuse, musique live', '20–35 €'],
                ],
                'tips' => [
                    "Prends la Roma Pass 48h/72h si tu visites 2+ sites majeurs (transport inclus).",
                    "Les musées du Vatican sont gratuits le dernier dimanche du mois — arrive 1 h avant l\'ouverture.",
                    "Beaucoup de restaurants touristiques autour des monuments — marche 5 min pour trouver l\'authentique.",
                    "La Chapelle Sixtine : silence obligatoire, pas de photo — respecte-le pour en profiter.",
                    "Le métro est limité mais fiable ; les bus sont parfois chaotiques. Marcher reste la meilleure option.",
                ],
            ],
            'paris' => [
                'name'  => 'Paris',
                'intro' => "Paris se découvre à pied, quartier par quartier, entre grands monuments et cafés de trottoir.",
                'day_themes' => ['Paris classique', 'Rive gauche et musées', 'Montmartre et nord', 'Marais et street food', 'Versailles', 'Canal Saint-Martin et Belleville'],
                'highlights' => [
                    ['Tour Eiffel', 'magique de nuit, réserve pour monter au sommet'],
                    ['Louvre', 'incontournable — mais choisis 2-3 ailes plutôt que tout'],
                    ['Musée d\'Orsay', 'chef-d\'œuvres impressionnistes dans une ancienne gare'],
                    ['Montmartre & Sacré-Cœur', 'vue à 360°, ruelles d\'artistes'],
                    ['Le Marais', 'galeries, boutiques indé et falafel rue des Rosiers'],
                    ['Notre-Dame + Île de la Cité', 'parvis rouvert, Sainte-Chapelle à voir absolument'],
                    ['Canal Saint-Martin', 'apéro les pieds dans l\'eau au coucher du soleil'],
                    ['Versailles', 'château + jardins, prévois la journée'],
                ],
                'tips' => [
                    "Le Paris Museum Pass couvre 50+ musées et évite souvent les files — rentable à partir de 3 visites.",
                    "Achète un Navigo Easy pour les transports, plus pratique qu\'acheter des tickets à l\'unité.",
                    "La plupart des musées nationaux sont gratuits le 1er dimanche du mois (octobre-mars).",
                    "Évite les restaurants des abords immédiats des monuments — marche 10 min pour trouver mieux et moins cher.",
                    "Pour la Tour Eiffel : réserve en ligne 2 mois à l\'avance ou monte à pied jusqu\'au 2ème étage (moins cher, moins de file).",
                ],
            ],
            'tokyo' => [
                'name'  => 'Tokyo',
                'intro' => "Tokyo est une succession de quartiers, chacun avec son ambiance propre — du Shibuya frénétique au Yanaka qui a gardé l\'esprit d\'Edo.",
                'day_themes' => ['Shibuya + Shinjuku', 'Asakusa + Akihabara', 'Harajuku + Shimokitazawa', 'Excursion à Kamakura', 'Tsukiji + Odaiba', 'Yanaka + Ueno'],
                'highlights' => [
                    ['Shibuya Crossing', 'le plus célèbre passage piéton du monde, vue depuis Shibuya Sky'],
                    ['Senso-ji (Asakusa)', 'temple le plus ancien de Tokyo, allée Nakamise'],
                    ['Meiji-jingu + Harajuku', 'sanctuaire en forêt + rue commerçante kawaii de Harajuku'],
                    ['Marché de Tsukiji', 'petit-déjeuner sushi à 7 h, c\'est l\'expérience'],
                    ['Shinjuku Gyoen', 'parc magnifique, surtout en saison des cerisiers'],
                    ['Akihabara', 'paradis otaku, arcades rétro'],
                    ['Yanaka', 'vieille Tokyo authentique, rue Yanaka Ginza'],
                    ['teamLab Planets', 'art immersif, réserve bien à l\'avance'],
                ],
                'tips' => [
                    "Prends une Suica ou Pasmo dès l\'arrivée — toutes les lignes de métro et beaucoup de commerces l\'acceptent.",
                    "Le JR Pass vaut le coup si tu fais un aller-retour Tokyo-Kyoto ; sinon, l\'achat à l\'unité est plus économique.",
                    "Les ramen, gyozas et bento des supérettes (7-Eleven, FamilyMart, Lawson) sont excellents pour moins de 10 €.",
                    "Les sanctuaires se visitent tôt le matin pour éviter la foule et profiter du calme.",
                    "Google Maps fonctionne très bien pour le métro ; pas besoin de carte papier.",
                ],
            ],
            'barcelone' => [
                'name'  => 'Barcelone',
                'intro' => "Barcelone, c\'est Gaudí, la plage en ville et les tapas de quartier — le tout accessible à pied ou en métro.",
                'day_themes' => ['Gaudí et Eixample', 'Vieille ville et plage', 'Montjuïc et Poble Sec', 'Gràcia et street food', 'Excursion Montserrat'],
                'highlights' => [
                    ['Sagrada Família', 'à voir à la tombée du jour avec la lumière dans les vitraux'],
                    ['Parc Güell', 'réserve en ligne, évite le midi'],
                    ['Casa Batlló + La Pedrera', 'deux chefs-d\'œuvre de Gaudí sur le Passeig de Gràcia'],
                    ['Quartier gothique', 'ruelles médiévales, cathédrale, bars à tapas'],
                    ['Boqueria', 'marché légendaire, jus de fruits à 1,5 €'],
                    ['Montjuïc', 'châteaux, fontaines magiques, vue sur la ville'],
                    ['Barceloneta', 'plage + paella authentique à Can Solé'],
                    ['Gràcia', 'bohème, placettes pour l\'apéro'],
                ],
                'tips' => [
                    "Réserve Sagrada Família et Parc Güell en ligne — sinon tu risques de ne pas entrer le jour même.",
                    "La T-casual (10 trajets) est la carte de transport la plus rentable.",
                    "Méfie-toi des pickpockets sur La Rambla et dans le métro, surtout L3.",
                    "Le menu del día (midi, 12–15 €) : meilleur rapport qualité/prix pour manger local.",
                    "Évite les tapas sur La Rambla — va plutôt à Gràcia ou El Born.",
                ],
            ],
            'istanbul' => [
                'name'  => 'Istanbul',
                'intro' => "Istanbul enjambe deux rives, avec un patrimoine byzantin et ottoman parmi les plus riches au monde.",
                'day_themes' => ['Sultanahmet', 'Grand Bazar et Bosphore', 'Rive asiatique', 'Balat et Galata'],
                'highlights' => [
                    ['Sainte-Sophie', 'ancienne basilique devenue mosquée, symbole de la ville'],
                    ['Mosquée Bleue', 'chef-d\'œuvre ottoman, entrée gratuite hors prières'],
                    ['Topkapi', 'palais des sultans, réserve 3-4 h'],
                    ['Grand Bazar', 'plus de 4000 boutiques, négocie toujours'],
                    ['Basilique-citerne', 'mystérieuse et fraîche en été'],
                    ['Tour de Galata', 'vue à 360° sur la Corne d\'Or'],
                    ['Balat', 'ruelles colorées, cafés photogéniques'],
                    ['Croisière sur le Bosphore', '1,5 h, à faire absolument'],
                ],
                'tips' => [
                    "L\'Istanbulkart (carte de transport) couvre métro, tram, bus et ferry — indispensable.",
                    "Passe du côté asiatique en ferry depuis Eminönü : trajet sublime pour 30 cents.",
                    "Le Museum Pass Istanbul (5 jours) rentabilise Sainte-Sophie + Topkapi + Citerne.",
                    "Négocie dans les bazars : le premier prix est souvent 2-3 fois trop élevé.",
                    "Goûte le simit au petit-déj et le balık ekmek (sandwich au poisson) à Eminönü.",
                ],
            ],
            'marrakech' => [
                'name'  => 'Marrakech',
                'intro' => "Marrakech grise, rouge et safran — médina grouillante, jardins apaisants et cuisine berbère.",
                'day_themes' => ['Médina', 'Jardins et palais', 'Quartiers modernes et hammam', 'Excursion Atlas ou Essaouira'],
                'highlights' => [
                    ['Jemaa el-Fna', 'la place mythique, effervescente à la tombée du jour'],
                    ['Souks de la médina', 'plonge-toi dedans, perds-toi, négocie'],
                    ['Palais Bahia', 'mosaïques, patios et plafonds sculptés'],
                    ['Jardin Majorelle + Yves Saint Laurent', 'bleu Majorelle emblématique'],
                    ['Médersa Ben Youssef', 'école coranique du XVIème, splendide'],
                    ['Tombeaux Saadiens', 'mausolée richement décoré'],
                    ['Hammam traditionnel', 'essaie un hammam populaire, pas touristique'],
                    ['Excursion Ourika ou Essaouira', 'journée au vert ou sur la côte'],
                ],
                'tips' => [
                    "Marche à Jemaa el-Fna au coucher du soleil — c\'est à ce moment que la place s\'anime vraiment.",
                    "Dans les souks : accepte le thé, souris, négocie — ne refuse jamais brusquement.",
                    "Prends un riad plutôt qu\'un hôtel pour vivre la médina de l\'intérieur.",
                    "Les faux guides sont partout — un simple « non merci » ferme mais poli suffit.",
                    "Garde un peu de liquide : beaucoup de petits commerçants n\'acceptent pas la carte.",
                ],
            ],
            'lisbonne' => [
                'name'  => 'Lisbonne',
                'intro' => "Lisbonne se vit en côtoyant ses sept collines, entre azulejos, pastéis de nata et fado au coin d\'une ruelle.",
                'day_themes' => ['Alfama et Baixa', 'Belém', 'Bairro Alto et LX Factory', 'Excursion Sintra'],
                'highlights' => [
                    ['Alfama', 'quartier du fado, ruelles en pente, miradouros'],
                    ['Tour et Monastère de Belém', 'patrimoine UNESCO, ne rate pas les pastéis de Belém'],
                    ['Tram 28', 'traverse les vieux quartiers, prends-le tôt le matin'],
                    ['Bairro Alto', 'vie nocturne et bars à vinho verde'],
                    ['LX Factory', 'ancienne usine devenue quartier créatif'],
                    ['Miradouro da Senhora do Monte', 'la plus belle vue sur la ville'],
                    ['Sintra', 'châteaux féeriques, journée entière'],
                    ['Time Out Market', 'food hall avec les meilleurs chefs de la ville'],
                ],
                'tips' => [
                    "Le Viva Viagem (carte rechargeable) est la façon la moins chère de prendre tram, métro et ascenseurs.",
                    "Les fameux pastéis de Belém valent le détour — la file avance vite.",
                    "Les miradouros (points de vue) sont gratuits et parsemés dans la ville.",
                    "Sintra : préfère le train au bus, plus rapide et moins cher.",
                    "Le fado s\'écoute dans l\'Alfama — évite les spectacles touristiques avec menu imposé.",
                ],
            ],
            'bangkok' => [
                'name'  => 'Bangkok',
                'intro' => "Bangkok brasse temples dorés, street food phénoménale et rooftops vertigineux — une ville qui ne dort jamais.",
                'day_themes' => ['Temples et vieille ville', 'Chinatown et marchés', 'Rooftops et shopping', 'Excursion Ayutthaya'],
                'highlights' => [
                    ['Grand Palais + Wat Phra Kaew', 'tenue correcte exigée, arrive à l\'ouverture'],
                    ['Wat Pho', 'Bouddha couché de 46 m, spectaculaire'],
                    ['Wat Arun', 'magique au coucher du soleil depuis l\'autre rive'],
                    ['Chatuchak', 'le week-end, 15000 stands, prévois de l\'eau'],
                    ['Chinatown (Yaowarat)', 'paradis street food dès 18h'],
                    ['Rooftop Sky Bar (Lebua)', 'vue à couper le souffle, code vestimentaire'],
                    ['Marché flottant (région de Ratchaburi)', 'touristique mais photogénique'],
                    ['Ayutthaya', 'ancienne capitale, temples en ruines, journée'],
                ],
                'tips' => [
                    "Le BTS Skytrain et le MRT évitent les embouteillages monstrueux — bien plus rapide que le taxi.",
                    "Pour la street food : suis les queues de locaux, c\'est le meilleur signal qualité.",
                    "Le pourboire n\'est pas obligatoire mais toujours apprécié (20-50 baht).",
                    "Les tuk-tuks sont fun mais chers — négocie avant, ou prends Grab.",
                    "Respecte les tenues dans les temples : épaules et genoux couverts, chaussures enlevées.",
                ],
            ],
            'new york' => [
                'name'  => 'New York',
                'intro' => "New York est une addiction : gratte-ciel, musées de classe mondiale, quartiers qui changent tous les dix blocs.",
                'day_themes' => ['Manhattan Midtown', 'Downtown + Brooklyn', 'Central Park + musées', 'Harlem + Upper East', 'Excursion Coney Island / DUMBO'],
                'highlights' => [
                    ['Empire State / Top of the Rock', 'préfère Top of the Rock : vue ON l\'Empire State'],
                    ['Statue de la Liberté + Ellis Island', 'réserve le ferry bien à l\'avance'],
                    ['High Line + Chelsea Market', 'promenade aérienne puis food hall'],
                    ['Central Park', 'à pied ou à vélo, prévois 2-3 h'],
                    ['MET et MoMA', 'chacun mérite une demi-journée'],
                    ['Brooklyn Bridge + DUMBO', 'traverse à pied, photos mémorables'],
                    ['Times Square de nuit', 'kitsch mais à faire une fois'],
                    ['Williamsburg', 'brunch, boutiques indé, ambiance artsy'],
                ],
                'tips' => [
                    "Prends une MetroCard ou OMNY (sans contact) pour le métro — $2.90 le trajet, illimité après 12 trajets/semaine.",
                    "Le CityPASS vaut le coup si tu vises 3+ attractions majeures.",
                    "Les happy hours (16-19h) divisent les prix des cocktails par deux.",
                    "Beaucoup de musées fonctionnent en « suggested donation » — tu peux payer moins.",
                    "Évite Times Square pour manger — prix multipliés par 3 pour une qualité médiocre.",
                ],
            ],
            'londres' => [
                'name'  => 'Londres',
                'intro' => "Londres est une succession de villages avec leur caractère — du très britannique à l\'ultra-cosmopolite.",
                'day_themes' => ['Westminster et centre', 'Musées et Hyde Park', 'East End et marchés', 'Excursion Greenwich / Windsor'],
                'highlights' => [
                    ['Westminster + Big Ben + London Eye', 'photo-stop obligatoire, London Eye au coucher du soleil'],
                    ['Tower of London + Tower Bridge', 'joyaux de la couronne, visite 3 h'],
                    ['British Museum', 'gratuit, Pierre de Rosette, Parthénon'],
                    ['National Gallery', 'gratuit, Trafalgar Square'],
                    ['Camden Market', 'musique, street food, ambiance alternative'],
                    ['Borough Market', 'paradis des gourmets, samedi'],
                    ['Notting Hill + Portobello', 'samedi matin pour le marché'],
                    ['Shoreditch', 'street art et nightlife à l\'est'],
                ],
                'tips' => [
                    "Oyster Card ou carte sans contact : tarif capé quotidien, tu ne peux pas dépenser plus que le Travelcard.",
                    "La plupart des grands musées sont GRATUITS — British Museum, National Gallery, Tate, V&A.",
                    "Les pubs servent souvent un menu midi à 8-12 £, très correct.",
                    "Regarde les musicals en prévente (ex: TKTS Leicester Square) — jusqu\'à -50%.",
                    "Il pleut — prévois une veste imperméable plutôt qu\'un parapluie (trop venteux).",
                ],
            ],
            'tozeur' => [
                'name'  => 'Tozeur',
                'intro' => "Tozeur, porte du Sahara tunisien, est la capitale de l'oasis et du désert — palmeraie mythique, dunes dorées, oasis de montagne et décors de Star Wars à deux pas.",
                'day_themes' => [
                    'Palmeraie et médina de Tozeur',
                    'Chott el Jérid et Nefta',
                    'Oasis de montagne (Chebika, Tamerza, Mides)',
                    'Ong Jmal (décors Star Wars) et Ksar Ghilane',
                    'Douz et porte du désert',
                    'Matmata et Ksour du Sud',
                    'Détente à la palmeraie',
                ],
                'slots' => [
                    // Jour 1 : Tozeur
                    ['Médina Ouled el Hadef', 'ruelles en briques ocre, motifs géométriques uniques', '2 h', 'gratuit'],
                    ['Restaurant Le Petit Prince', 'dans la palmeraie, cuisine tunisienne et couscous aux légumes', '15–25 DT'],
                    ['Musée Dar Cheraït + Palmeraie', 'musée des traditions du Jérid + balade en calèche', '2 h', '10 DT'],
                    ['Dîner au Dar HI', 'cuisine raffinée dans un éco-lodge design', '40–80 DT'],
                    // Jour 2 : Chott el Jérid + Nefta
                    ['Chott el Jérid au lever du soleil', 'lac salé immense, mirages garantis sur la route P3', '3 h avec route', 'gratuit'],
                    ['Déjeuner à Nefta', 'Hôtel Caravansérail ou resto de la Corbeille', '15–30 DT'],
                    ['Corbeille de Nefta + zaouïa Sidi Bou Ali', 'oasis en cuvette, sanctuaire soufi', '2 h', 'gratuit'],
                    ['Coucher de soleil sur les dunes', 'depuis la route des oasis', '1 h', 'gratuit'],
                    // Jour 3 : Oasis de montagne
                    ['Chebika (oasis de montagne)', 'cascade et ruines du village berbère', '2 h', '3 DT'],
                    ['Déjeuner à Tamerza', 'relais Tamerza Palace ou gargote locale', '20–45 DT'],
                    ['Tamerza + Gorges de Mides', 'canyon spectaculaire, 40 km de piste', '3 h', 'gratuit'],
                    ['Retour à Tozeur, thé à la menthe en médina', '', 'gratuit'],
                    // Jour 4 : Star Wars + désert
                    ['Ong Jmal (décor de Mos Espa)', 'dune + décor de Star Wars Episode I, excursion 4x4 depuis Tozeur', '4 h', '80–150 DT l\'excursion'],
                    ['Pique-nique au pied des dunes', '', 'inclus dans excursion'],
                    ['Ksar Ghilane (dunes + source chaude)', 'oasis au milieu du Grand Erg Oriental', '3 h sur place', '10 DT bain'],
                    ['Dîner sous la tente berbère', 'musique bédouine au bivouac', '60–120 DT'],
                    // Jour 5 : Douz
                    ['Route vers Douz via Kébili', 'porte du désert, capitale du dromadaire', '3 h route', 'carburant'],
                    ['Déjeuner au restaurant El Mouradi Douz', 'cuisine locale, couscous au poisson de palmier', '25–45 DT'],
                    ['Balade à dos de dromadaire au coucher du soleil', 'Zaafrane ou Nouil, 1 h en chamelle', '', '30 DT'],
                    ['Nuit à Douz ou retour Tozeur', 'Hôtel Sahara Douz ou retour', '—'],
                    // Jour 6 : Matmata + Ksour
                    ['Matmata (habitations troglodytes)', 'maisons souterraines berbères — Hôtel Sidi Driss = décor Luke Skywalker', '3 h', '5 DT'],
                    ['Déjeuner à Matmata', 'Hôtel Sidi Driss ou Ksar Amazigh', '20–40 DT'],
                    ['Ksar Hadada + Ksar Ouled Soltane', 'greniers berbères fortifiés, près de Tataouine', '3 h', 'gratuit'],
                    ['Retour Tozeur, dîner à l\'hôtel', '', '—'],
                    // Jour 7 : Détente
                    ['Matinée à la palmeraie', 'balade, sources de Ras El Aïn, Eden Palm', '3 h', '8–15 DT'],
                    ['Déjeuner chez Chak Wak', 'parc thématique + cuisine locale', '20–30 DT'],
                    ['Après-midi hammam et souk', 'achète dattes Deglet Nour, tapis du Jérid', '2 h', 'variable'],
                    ['Dîner d\'adieu avec brick et couscous', 'restaurant de la médina', '20–40 DT'],
                ],
                'highlights' => [
                    ['Palmeraie de Tozeur', '200 000 palmiers dattiers, ambiance d\'oasis, sources de Ras El Aïn'],
                    ['Médina Ouled el Hadef', 'briques ocre à motifs géométriques, unique en Tunisie'],
                    ['Chott el Jérid', 'lac salé géant, traverse-le au lever du jour pour les mirages'],
                    ['Ong Jmal + Mos Espa', 'décors de Star Wars Episode I encore debout dans le désert'],
                    ['Chebika / Tamerza / Mides', 'les « 3 oasis de montagne », cascades et canyons'],
                    ['Ksar Ghilane', 'dunes rouges et bassin d\'eau chaude au cœur du Grand Erg'],
                    ['Musée Dar Cheraït', 'traditions du Jérid dans une demeure d\'époque'],
                    ['Nefta et sa Corbeille', 'oasis en cuvette et ville soufie, à 25 km de Tozeur'],
                    ['Douz (porte du désert)', 'dromadaires, Festival du Sahara en décembre'],
                ],
                'hotels' => [
                    'eco' => [
                        ['Résidence Essaada (centre Tozeur)', 'propre et central, ~50–80 DT / nuit'],
                        ['Hôtel Djerid (Tozeur)', 'classique, piscine, ~60–100 DT / nuit'],
                        ['Hôtel Khalifa El Tozeur', 'rapport qualité-prix, proche médina, ~70–110 DT / nuit'],
                    ],
                    'moyen' => [
                        ['Ras El Aïn (Tozeur)', 'ancien palais au cœur de la palmeraie, piscine, ~120–200 DT / nuit'],
                        ['Hôtel Palm Beach Palace Tozeur', 'grand classique 4*, piscine, jardins, ~150–250 DT / nuit'],
                        ['Hôtel Ksar Rouge', 'architecture saharienne, piscine, ~130–210 DT / nuit'],
                    ],
                    'lux' => [
                        ['Dar HI (Tozeur)', 'éco-lodge design signé Matali Crasset, spa, ~450–700 DT / nuit'],
                        ['Anantara Sahara Tozeur Resort', 'villas 5* avec piscine privée, SPA, ~800–1500 DT / nuit'],
                        ['Tamerza Palace Hotel', '5* au bord du canyon, à 1 h de Tozeur, ~300–500 DT / nuit'],
                    ],
                ],
                'food' => [
                    ['Restaurant Le Petit Prince (palmeraie)', 'cadre enchanteur, cuisine tunisienne, 15–35 DT'],
                    ['Restaurant Le République (médina)', 'brick à l\'œuf, couscous, 12–25 DT'],
                    ['Dar HI', 'bistronomie locale raffinée, 40–90 DT'],
                    ['Chak Wak', 'cuisine familiale dans un parc, 15–30 DT'],
                    ['Marché central de Tozeur', 'dattes Deglet Nour fraîches, miel, épices, 2–10 DT'],
                    ['Café Berbère (médina)', 'thé à la menthe aux pignons, 2–5 DT'],
                ],
                'tips' => [
                    "La meilleure saison est octobre à avril. Juin-août : trop chaud (45°C+).",
                    "Loue une voiture à Tozeur ou Tunis ; les oasis de montagne ne sont pas desservies par les bus.",
                    "Pour Ong Jmal et Ksar Ghilane, prends une excursion 4x4 (80-150 DT/pers) — pas accessible en voiture normale.",
                    "Rapporte des dattes Deglet Nour au marché central — c'est moins cher qu'à Tunis ou à l'aéroport.",
                    "Monnaie locale : dinar tunisien (DT). Les cartes passent dans les grands hôtels, prévois du liquide pour les oasis.",
                    "Les Tozeurois parlent français — tu peux t'exprimer sans problème.",
                    "Pour Matmata : l'Hôtel Sidi Driss = le décor de la ferme de Luke dans Star Wars, visitable même sans nuiter.",
                ],
            ],
            'djerba' => [
                'name'  => 'Djerba',
                'intro' => "Djerba est l'île des légendes — plages de sable fin, ruelles de Houmt Souk, villages berbères et la synagogue de la Ghriba, une des plus anciennes au monde.",
                'day_themes' => ['Houmt Souk et médina', 'Plage et flamants roses', 'Villages de l\'île', 'La Ghriba et artisanat', 'Sports nautiques'],
                'slots' => [
                    ['Médina de Houmt Souk', 'place Hédi Chaker, fondouks reconvertis, mosquée des Étrangers', '2 h', 'gratuit'],
                    ['Déjeuner au port de Houmt Souk', 'poulpe à la djerbienne chez Restaurant Haroun', '25–50 DT'],
                    ['Plage de Sidi Mahrès', 'farniente et baignade, une des plus belles plages de l\'île', '3 h', 'gratuit'],
                    ['Dîner à Midoun', 'restaurants de poisson, ambiance soirée', '30–60 DT'],
                    ['Synagogue de la Ghriba (Er Riadh)', 'lieu saint juif, pèlerinage annuel à Lag Ba\'Omer', '1 h', 'gratuit'],
                    ['Déjeuner à Guellala', 'poterie + mechouia au restaurant Essofra', '20–40 DT'],
                    ['Musée de Guellala + ateliers de poterie', 'traditions et faïence locale', '2 h', '8 DT'],
                    ['Apéro sur la plage, coucher de soleil à Ras R\'mel', 'langue de sable face aux flamants roses', '', 'gratuit'],
                    ['Plage de Sidi Jmour', 'village de pêcheurs, ambiance sauvage', '3 h', 'gratuit'],
                    ['Déjeuner grillade à Ajim', 'port d\'où part le ferry pour Zarzis, poulpes frais', '20–40 DT'],
                    ['Fort Borj el Kebir (Houmt Souk)', 'forteresse du XVème, vue sur le port', '1 h', '5 DT'],
                    ['Dîner au rooftop Radisson Blu Palace', 'vue sur la mer, cuisine méditerranéenne', '80–180 DT'],
                    ['Quad / kitesurf sur la plage', 'loueurs à Midoun, 1-2 h d\'activité', '80–200 DT'],
                    ['Déjeuner pieds dans le sable', 'Blue Pearl ou Hara Kebira', '30–60 DT'],
                    ['Musée Lalla Hadria', 'arts de l\'islam dans un palais reconstruit', '1,5 h', '12 DT'],
                    ['Hammam traditionnel puis dîner à Houmt Souk', 'restaurant A\'Salam ou Les Palmiers', '25–50 DT'],
                    ['Excursion à Zarzis ou Matmata', 'ferry Ajim-Jorf 15 min, puis route continentale', 'journée', '80–200 DT'],
                    ['Déjeuner en cours de route', '', '25–50 DT'],
                    ['Retour Djerba en fin de journée', 'coucher de soleil sur la chaussée romaine El Kantara', '', '—'],
                    ['Dîner final', '', '40–80 DT'],
                ],
                'highlights' => [
                    ['Houmt Souk', 'capitale de l\'île, souks, fondouks, place Hédi Chaker'],
                    ['Synagogue de la Ghriba (Er Riadh)', 'une des plus anciennes synagogues du monde, pèlerinage célèbre'],
                    ['Plage de Sidi Mahrès', '6 km de sable blanc, la plus longue de l\'île'],
                    ['Guellala', 'village de poterie, musée ethnographique'],
                    ['Borj el Kebir', 'forteresse hafside de Houmt Souk'],
                    ['Ras R\'mel (île aux flamants roses)', 'presqu\'île nord, flamants d\'octobre à mars'],
                    ['Musée Lalla Hadria / Djerba Explore', 'parc culturel et parc aux crocodiles'],
                    ['Chaussée romaine El Kantara', 'liaison antique de 7 km entre l\'île et le continent'],
                ],
                'hotels' => [
                    'eco' => [
                        ['Djerba Plaza Thalasso', '3*, piscine, à Midoun, ~90–150 DT / nuit'],
                        ['Hôtel Hari Club (Aghir)', 'tout-inclus pas cher, plage privée, ~110–180 DT / nuit'],
                        ['Hôtel Menzel Dija (Midoun)', 'petite adresse familiale, ~60–110 DT / nuit'],
                    ],
                    'moyen' => [
                        ['Mövenpick Resort & Marine Spa Djerba', '5* avec thalasso, plage Sidi Mahrès, ~250–400 DT / nuit'],
                        ['Iberostar Mehari Djerba', '4*, grande plage, jardins, ~200–320 DT / nuit'],
                        ['Djerba Aqua Resort', '5* familial, parc aquatique, ~220–350 DT / nuit'],
                    ],
                    'lux' => [
                        ['Radisson Blu Palace Resort & Thalasso Djerba', '5*, sur la plage, plusieurs piscines, ~400–700 DT / nuit'],
                        ['Hasdrubal Prestige Thalassa & Spa', '5* all-suite, thalasso de référence, ~500–900 DT / nuit'],
                        ['Dar Dhiafa (Erriadh)', 'boutique-hôtel de charme au village, ~350–600 DT / nuit'],
                    ],
                ],
                'food' => [
                    ['Restaurant Haroun (port de Houmt Souk)', 'poisson du jour, institution locale, 30–70 DT'],
                    ['Essofra (Guellala)', 'mechouia et grillades paysannes, 20–40 DT'],
                    ['Blue Pearl (plage)', 'poisson grillé les pieds dans le sable, 40–90 DT'],
                    ['A\'Salam (Houmt Souk)', 'cuisine tunisienne, prix locaux, 15–30 DT'],
                    ['La Fontaine (Midoun)', 'spécialités italiennes et tunisiennes, 25–55 DT'],
                    ['Marché aux poissons de Houmt Souk', 'poulpes, rougets, mérous frais, 10–40 DT'],
                ],
                'tips' => [
                    "Meilleures saisons : mai-juin et septembre-octobre. Juillet-août : plages bondées.",
                    "Aéroport Djerba-Zarzis (DJE) bien desservi depuis l\'Europe, sinon bac Ajim-Jorf depuis Zarzis.",
                    "Louer une voiture est quasi indispensable pour sortir des hôtels ; ou loue un scooter à Midoun.",
                    "Le poulpe à la djerbienne et la brik à l\'œuf sont les signatures locales.",
                    "La Ghriba : hors pèlerinage (mai), visite libre, couvre tes épaules.",
                    "Attention aux faux guides à Houmt Souk — refuse poliment, marche sans t\'arrêter.",
                ],
            ],
            'tunis' => [
                'name'  => 'Tunis',
                'intro' => "Tunis mêle la médina médiévale classée UNESCO, les ruines romaines de Carthage et les bleus-blancs de Sidi Bou Saïd à deux pas du centre.",
                'day_themes' => ['Médina et Ville nouvelle', 'Carthage et Sidi Bou Saïd', 'Bardo et banlieue nord', 'Excursion Dougga ou Kairouan'],
                'slots' => [
                    ['Médina de Tunis (Zitouna, souks, Dar Ben Abdallah)', 'cœur historique, Mosquée Zitouna (accès cour)', '3 h', 'gratuit'],
                    ['Déjeuner chez Dar El Jeld', 'palais XVIIIème transformé en restaurant gastronomique', '60–120 DT'],
                    ['Ville nouvelle (avenue Bourguiba, cathédrale)', 'architecture coloniale, café de Paris', '2 h', 'gratuit'],
                    ['Dîner au Fondouk El Attarine', 'cadre de palais, cuisine tunisienne élaborée', '50–90 DT'],
                    ['Ruines de Carthage (Byrsa, thermes d\'Antonin)', 'pass combiné 12 DT, prévois demi-journée', '4 h', '12 DT'],
                    ['Déjeuner à Sidi Bou Saïd', 'Le Pirate ou Dar Zarrouk, vue sur la mer', '40–80 DT'],
                    ['Sidi Bou Saïd', 'village bleu et blanc, Café des Nattes, villa Ennejma Ezzahra', '2 h', '5 DT villa'],
                    ['Dîner à La Goulette', 'poissons grillés en bord de mer', '40–80 DT'],
                    ['Musée national du Bardo', 'plus grande collection de mosaïques romaines au monde', '3 h', '13 DT'],
                    ['Déjeuner à la Marsa', 'plage urbaine, brasseries de bord de mer', '30–60 DT'],
                    ['Plage de Gammarth + shopping Gammarth', 'détente, cafés en bord de plage', '3 h', 'gratuit'],
                    ['Dîner au port de Sidi Bou Saïd', 'poisson au restaurant Le Grand Bleu', '50–100 DT'],
                    ['Excursion Dougga (Téboursouk)', '« la mieux préservée des villes romaines d\'Afrique du Nord », 120 km', 'journée', '10 DT + transport'],
                    ['Déjeuner à Dougga ou Testour', 'bistrot local', '20–40 DT'],
                    ['Retour Tunis, thé sur une terrasse de la médina', 'Café Panorama au Dar El Harka', '', '5–10 DT'],
                    ['Dîner final chez El Walima', 'cuisine tunisienne contemporaine', '50–100 DT'],
                ],
                'highlights' => [
                    ['Médina de Tunis', 'classée UNESCO, 700 monuments, souks des chechias et des parfums'],
                    ['Carthage', 'ruines puniques et romaines, thermes d\'Antonin face à la mer'],
                    ['Sidi Bou Saïd', 'village bleu et blanc perché sur la falaise, Café des Nattes'],
                    ['Musée du Bardo', 'mosaïques romaines, palais beylical'],
                    ['Avenue Habib Bourguiba', 'Champs-Élysées de Tunis, cathédrale et Théâtre municipal'],
                    ['La Goulette', 'port de Tunis, poissons grillés, plage populaire'],
                    ['Palais Ennejma Ezzahra (Sidi Bou Saïd)', 'résidence du Baron d\'Erlanger, musée des musiques arabes'],
                    ['Dougga (excursion)', 'site romain le mieux préservé d\'Afrique du Nord'],
                ],
                'hotels' => [
                    'eco' => [
                        ['Hôtel Maison Blanche (centre)', 'simple, central, ~80–140 DT / nuit'],
                        ['Dar El Jeld Tourism Residence (médina)', 'maison traditionnelle, ~100–170 DT / nuit'],
                        ['Hôtel Carlton (avenue Bourguiba)', 'classique, bien placé, ~90–160 DT / nuit'],
                    ],
                    'moyen' => [
                        ['The Russelior Hotel & Spa (La Marsa)', '5* en bord de mer, ~220–380 DT / nuit'],
                        ['Mövenpick du Lac Tunis', 'business moderne au lac, ~200–340 DT / nuit'],
                        ['La Villa Bleue (Sidi Bou Saïd)', 'boutique-hôtel, vue sur la mer, ~250–450 DT / nuit'],
                    ],
                    'lux' => [
                        ['The Residence Tunis (Gammarth)', '5* Leading Hotels, plage privée, spa, ~500–900 DT / nuit'],
                        ['Four Seasons Hotel Tunis (Gammarth)', '5* sur la plage, piscine à débordement, ~600–1200 DT / nuit'],
                        ['Dar Saïd (Sidi Bou Saïd)', 'palais restauré, piscine, ~400–700 DT / nuit'],
                    ],
                ],
                'food' => [
                    ['Dar El Jeld (médina)', 'gastronomie tunisienne dans un palais, 60–150 DT'],
                    ['Fondouk El Attarine (médina)', 'brik, couscous, tajine tunisien, 40–90 DT'],
                    ['Le Pirate (Sidi Bou Saïd)', 'poisson au port, 40–100 DT'],
                    ['Café des Nattes (Sidi Bou Saïd)', 'thé aux pignons, institution, 5–10 DT'],
                    ['Dar Slah (médina)', 'cuisine traditionnelle, 25–55 DT'],
                    ['Restaurant Le Golfe (La Goulette)', 'poisson frais, ambiance populaire, 40–80 DT'],
                ],
                'tips' => [
                    "Prends le TGM (train léger) pour Carthage / Sidi Bou Saïd / La Marsa depuis Tunis Marine : 1 DT, toutes les 15 min.",
                    "Le pass Carthage (12 DT) couvre 7 sites archéologiques sur l\'ensemble de la zone.",
                    "Médina : évite le vendredi midi (prières) pour les mosquées, sinon flâne librement.",
                    "La meilleure saison : mars-mai et septembre-novembre. Août = très chaud.",
                    "Attention aux faux guides à l\'entrée de la médina (Bab el Bhar). Dis fermement non, ne donne pas ton programme.",
                ],
            ],
            'hammamet' => [
                'name'  => 'Hammamet',
                'intro' => "Hammamet, station balnéaire la plus ancienne de Tunisie, offre plages de sable blanc, médina fortifiée et la somptueuse villa Sebastian rachetée par George Sebastian.",
                'day_themes' => ['Médina et plage de Hammamet', 'Hammamet Sud (Yasmine)', 'Nabeul et céramique', 'Excursion Zaghouan ou Kairouan'],
                'highlights' => [
                    ['Médina de Hammamet + Kasbah', 'petite médina fortifiée au bord de mer, vue depuis la Kasbah'],
                    ['Plage de Hammamet', 'sable blanc, eaux turquoise, cocotiers, palmiers'],
                    ['Villa Sebastian (Centre culturel international)', 'villa Art déco de George Sebastian, jardins, théâtre'],
                    ['Hammamet Yasmine (marina)', 'marina moderne, restaurants, boîtes de nuit'],
                    ['Nabeul (souk du vendredi + poteries)', 'à 15 km, capitale de la céramique'],
                    ['Médina Cartaghe (parc d\'attractions)', 'reproduction médiévale, spectacles équestres'],
                    ['Parc Friguia (zoo safari)', 'famille, animaux africains, entre Hammamet et Sousse'],
                    ['Thalassothérapie (Bio Azur, Hasdrubal)', 'Tunisie = 2ème destination thalasso au monde'],
                ],
                'hotels' => [
                    'eco' => [
                        ['Hôtel Lella Baya (médina)', '4* boutique, piscine, 110–190 DT'],
                        ['Hôtel Venezia Resort (Hammamet)', '3*, tout-inclus, 100–170 DT'],
                        ['Residence Romane (Yasmine)', 'apparthôtel familial, 80–140 DT'],
                    ],
                    'moyen' => [
                        ['La Badira (adults-only 5*)', 'design d\'exception, spa, plage privée, 300–550 DT'],
                        ['TUI Blue Scheherazade', '4*+, plage, tout-inclus, 200–360 DT'],
                        ['Royal Azur Thalassa', '5* thalasso, 250–420 DT'],
                    ],
                    'lux' => [
                        ['The Residence Hammamet', '5* de luxe, golf, plage privée, spa, 500–900 DT'],
                        ['Sentido Phenicia Premium', 'sélect 5*, 350–600 DT'],
                        ['Steigenberger Marhaba Thalasso', '5* thalasso haut de gamme, 400–700 DT'],
                    ],
                ],
                'food' => [
                    ['Chez Achour (médina)', 'restaurant historique, cuisine tunisienne, 40–80 DT'],
                    ['La Scala (Yasmine)', 'cuisine italienne réputée, 50–100 DT'],
                    ['Slovenia (plage)', 'poisson grillé en bord de mer, 50–120 DT'],
                    ['Le Barberousse (médina)', 'vue sur la Kasbah, poisson frais, 40–80 DT'],
                    ['Marché central de Hammamet', 'olives, épices, fruits secs, 2–15 DT'],
                ],
                'tips' => [
                    "Meilleure saison : mai-juin et septembre. Août : tout le Tunis vient en week-end.",
                    "Nabeul le vendredi : grand souk hebdomadaire, céramiques et olives.",
                    "Prends la louage (taxi collectif) Tunis-Hammamet : 10 DT, 1 h.",
                    "Marchande à la médina : commence à 30-50% du premier prix.",
                    "Les thalassos proposent des cures 6 jours : alternative intéressante aux all-inclusive classiques.",
                ],
            ],
            'sousse' => [
                'name'  => 'Sousse',
                'intro' => "Sousse, \"la perle du Sahel\", conjugue une médina UNESCO, 20 km de plages et la proximité de Port El Kantaoui, Monastir et El Jem.",
                'day_themes' => ['Médina UNESCO', 'Plage et Port El Kantaoui', 'Monastir et Mahdia', 'Excursion El Jem et Kairouan'],
                'highlights' => [
                    ['Médina de Sousse', 'remparts du IXème, Grande Mosquée, Ribat'],
                    ['Ribat de Sousse', 'forteresse militaire du VIIIème siècle, monte sur la tour'],
                    ['Musée archéologique (Kasbah)', 'mosaïques de la région, expo gratuite le vendredi'],
                    ['Port El Kantaoui', 'marina de luxe à 8 km, restos et boutiques'],
                    ['Monastir (mausolée Bourguiba, Ribat)', 'à 20 km, ribat filmé dans « La Vie de Brian »'],
                    ['El Jem (amphithéâtre romain)', '3ème plus grand au monde, à 60 km, excursion'],
                    ['Mahdia (ville fatimide)', 'à 60 km, ancienne capitale fatimide, plages sauvages'],
                    ['Kairouan (4ème ville sainte de l\'Islam)', 'à 60 km, Grande Mosquée IXème siècle'],
                ],
                'hotels' => [
                    'eco' => [
                        ['Hôtel Marabout (Sousse)', '3*, plage, tout-inclus, 80–150 DT'],
                        ['Hôtel Marhaba Club', '3*, bord de mer, 90–160 DT'],
                        ['Residence Boujaafar (centre)', 'apparthôtel, 70–130 DT'],
                    ],
                    'moyen' => [
                        ['Mövenpick Resort & Marine Spa Sousse', '5* sur la plage, spa, 220–380 DT'],
                        ['Marhaba Palace (Port El Kantaoui)', '5*, jardins, 180–300 DT'],
                        ['Hôtel El Mouradi Palace', '5* classique, 200–340 DT'],
                    ],
                    'lux' => [
                        ['Iberostar Selection Kantaoui Bay', '5* premium, 280–480 DT'],
                        ['Royal El Mansour Mahdia Thalasso', '5* à Mahdia, plage sauvage, 300–500 DT'],
                        ['Sentido Phenicia (Hammamet voisin)', 'alternative 5*, 350–600 DT'],
                    ],
                ],
                'food' => [
                    ['La Calèche (Port El Kantaoui)', 'poisson et fruits de mer, 50–100 DT'],
                    ['Le Lido (Sousse plage)', 'bord de mer, cuisine internationale, 40–80 DT'],
                    ['Restaurant de la Médina', 'cuisine tunisienne authentique, 20–40 DT'],
                    ['Marché central de Sousse', 'poisson frais, mareyeurs, 10–30 DT'],
                    ['Pâtisserie Masmoudi', 'makroud, baklava, institution tunisienne, 2–10 DT'],
                ],
                'tips' => [
                    "Train Sahel Metro relie Sousse-Monastir-Mahdia : pratique et pas cher (3-5 DT).",
                    "El Jem mérite le détour même sur 1 demi-journée : l\'amphithéâtre est spectaculaire.",
                    "Médina : les souks ferment le vendredi à 12h.",
                    "Meilleure saison : avril-juin et septembre-octobre.",
                    "Port El Kantaoui le soir : restaurants le long de la marina, ambiance familiale.",
                ],
            ],
            'athenes' => [
                'name'  => 'Athènes',
                'intro' => "Athènes mêle l\'Antiquité grecque à la vie de quartier actuelle — Acropole au-dessus, tavernes de Plaka en dessous, street art à Exarchia.",
                'day_themes' => ['Acropole et centre antique', 'Musées et Plaka', 'Quartiers vivants (Psyri, Exarchia)', 'Excursion au Cap Sounion ou Égine'],
                'highlights' => [
                    ['Acropole + Parthénon', 'monte à l\'ouverture, billet 20 € (ou pass 30 € combiné 7 sites)'],
                    ['Musée de l\'Acropole', 'chef-d\'œuvre architectural, frises du Parthénon, 10 €'],
                    ['Agora antique', 'cœur politique de l\'Athènes classique, temple d\'Héphaïstos'],
                    ['Plaka et Anafiotika', 'vieux quartier aux ruelles blanches, cafés sous l\'Acropole'],
                    ['Musée national archéologique', 'masque d\'Agamemnon, trésors mycéniens, 12 €'],
                    ['Colline de Lycabette', 'vue à 360° sur la ville, funiculaire ou à pied (30 min)'],
                    ['Marché central (Varvakios)', 'poissonnerie, bouchers, gargotes du matin'],
                    ['Cap Sounion (excursion)', 'temple de Poséidon, coucher de soleil mythique'],
                ],
                'hotels' => [
                    'eco' => [
                        ['Athens Hawks Hostel (Monastiraki)', 'dortoir propre, 20–35 €'],
                        ['City Circus Athens (Psyri)', 'hostel design, ambiance sociale, 30–55 €'],
                        ['Hotel Attalos (Monastiraki)', 'vue rooftop Acropole, 70–110 €'],
                    ],
                    'moyen' => [
                        ['A for Athens (Monastiraki)', 'boutique, rooftop bar célèbre, 130–220 €'],
                        ['The Modernist Athens (Kolonaki)', 'chic et tendance, 150–240 €'],
                        ['Electra Palace Athens (Plaka)', '5* au cœur de Plaka, 200–330 €'],
                    ],
                    'lux' => [
                        ['Hotel Grande Bretagne (Syntagma)', 'icône, rooftop, 400–800 €'],
                        ['King George, A Luxury Collection', '5* historique, 350–650 €'],
                        ['Four Seasons Astir Palace (Vouliagmeni)', 'sur la riviera, 600–1400 €'],
                    ],
                ],
                'food' => [
                    ['Ta Karamanlidika tou Fani (Psyri)', 'mezzés, charcuterie grecque, 20–35 €'],
                    ['Diporto (marché central)', 'institution, menu du jour, 12–20 €'],
                    ['Nolan (centre)', 'fusion gréco-asiatique, 30–50 €'],
                    ['Klimataria (Plaka)', 'taverne familiale, musique live, 20–40 €'],
                    ['O Thanasis (Monastiraki)', 'souvlaki légendaire, 10–15 €'],
                ],
                'tips' => [
                    "Le pass Acropole 30 € (valide 5 jours) inclut 7 sites : très rentable.",
                    "Métro propre et efficace : ligne 3 depuis l\'aéroport (9 €, 45 min).",
                    "Évite juillet-août (40°C, foule) ; mai-juin et septembre-octobre idéaux.",
                    "Le dimanche des mois d\'hiver, les sites antiques sont gratuits.",
                    "Attention aux pickpockets à Monastiraki et dans le métro.",
                ],
            ],
            'berlin' => [
                'name'  => 'Berlin',
                'intro' => "Berlin, ville de la réunification, est un laboratoire créatif ouvert 24/7 — histoire dense, street art, clubs mythiques et parcs immenses.",
                'day_themes' => ['Mitte et centre historique', 'Kreuzberg / Friedrichshain', 'Musées et Tiergarten', 'Potsdam (excursion)'],
                'highlights' => [
                    ['Brandenburger Tor + Reichstag', 'réserve la coupole du Reichstag : gratuit mais obligatoire'],
                    ['East Side Gallery', '1,3 km de Mur de Berlin repeint, gratuit'],
                    ['Museum Island (Pergamonmuseum, Neues)', 'cinq musées sur une île, pass 19 €'],
                    ['Checkpoint Charlie + Mémorial du Mur', 'ligne de démarcation historique, musée 17,50 €'],
                    ['Mémorial de l\'Holocauste', 'Peter Eisenman, 2711 stèles, poignant'],
                    ['Tiergarten + Victory Column', 'poumon vert, vue depuis la colonne 3,50 €'],
                    ['Kreuzberg (Landwehrkanal, Türkischer Markt)', 'Berlin multiculturel, marché du mardi/vendredi'],
                    ['Potsdam (excursion)', 'châteaux de Sanssouci, UNESCO, 30 min en train'],
                ],
                'hotels' => [
                    'eco' => [
                        ['Generator Berlin Mitte', 'hostel design, 25–50 €'],
                        ['MEININGER Hotel Hauptbahnhof', 'gare centrale, 60–100 €'],
                        ['Hotel Amano Grand Central', '3* tendance, 80–130 €'],
                    ],
                    'moyen' => [
                        ['Michelberger Hotel (Friedrichshain)', 'boutique créatif, 140–220 €'],
                        ['25hours Hotel Bikini Berlin', 'rooftop vue Tiergarten, 180–280 €'],
                        ['The Circus Hotel (Mitte)', '4*, design, 160–260 €'],
                    ],
                    'lux' => [
                        ['Hotel de Rome (Bebelplatz)', 'palace Rocco Forte, 400–800 €'],
                        ['Hotel Adlon Kempinski (Brandenburger Tor)', 'légende berlinoise, 500–1100 €'],
                        ['The Ritz-Carlton Berlin (Potsdamer Platz)', 'luxe central, 450–900 €'],
                    ],
                ],
                'food' => [
                    ['Mustafa\'s Gemüse Kebap (Kreuzberg)', 'LE meilleur kebab de Berlin, 5–8 €'],
                    ['Markthalle Neun (Kreuzberg)', 'street food food hall, 8–20 €'],
                    ['Zur letzten Instanz (Mitte)', 'taverne de 1621, cuisine allemande, 20–40 €'],
                    ['Burgermeister (Kreuzberg)', 'burger dans d\'anciens WC publics, 8–15 €'],
                    ['Curry 36 (Mehringdamm)', 'currywurst mythique, 3–6 €'],
                ],
                'tips' => [
                    "Berlin WelcomeCard : transports illimités + réductions musées, à partir de 24 €.",
                    "Le S-Bahn et le U-Bahn forment un réseau énorme — télécharge l\'app BVG.",
                    "Le Museum Pass Berlin 5 €/jour pour les 3 jours couvre 30+ musées.",
                    "Le dimanche : Mauerpark flea market et karaoké public, ambiance iconique.",
                    "La vie nocturne commence très tard (2 h du matin) et finit très tard (dimanche 15 h).",
                ],
            ],
            'dubai' => [
                'name'  => 'Dubaï',
                'intro' => "Dubaï pousse la démesure à l\'extrême — tours de verre, îles artificielles, désert à 30 min et souks traditionnels de Deira.",
                'day_themes' => ['Downtown et Burj Khalifa', 'Vieille ville (Deira et Bur Dubai)', 'Plages et Palm Jumeirah', 'Désert et safari', 'Abu Dhabi (excursion)'],
                'highlights' => [
                    ['Burj Khalifa (At the Top)', '828 m, niveau 124-125 à 169 AED, niveau 148 à 399 AED'],
                    ['Dubai Mall + aquarium + fontaine', 'réserve-toi une demi-journée, show des fontaines 30 min'],
                    ['Palm Jumeirah + Atlantis', 'île artificielle, The View at The Palm 100 AED'],
                    ['Souk de l\'or + Souk des épices (Deira)', 'traverse en abra (1 AED) depuis Bur Dubai'],
                    ['Dubai Frame', 'cadre géant de 150 m entre ancien et nouveau Dubaï, 50 AED'],
                    ['Safari désert + dîner bédouin', 'dune bashing + chameaux + spectacle, ~250-400 AED'],
                    ['Musée Al Shindagha (vieux quartier)', 'histoire de Dubaï dans des maisons de pêcheurs'],
                    ['Global Village (nov-avril)', 'pavillons culturels du monde entier, 27 AED'],
                    ['Abu Dhabi (excursion)', 'Mosquée Sheikh Zayed + Louvre Abu Dhabi, 1 h 30 de route'],
                ],
                'hotels' => [
                    'eco' => [
                        ['Rove Downtown', '4*, design, proche Burj Khalifa, 400–700 AED'],
                        ['Citymax Hotel Bur Dubai', '3*, centre vieille ville, 250–450 AED'],
                        ['ibis Deira City Centre', '3*, proche métro, 300–500 AED'],
                    ],
                    'moyen' => [
                        ['Jumeirah Beach Hotel', '5*, plage privée, 900–1600 AED'],
                        ['Address Dubai Mall', '5* au pied du Burj Khalifa, 1200–2000 AED'],
                        ['Sofitel The Palm', '5* Palm Jumeirah, 900–1700 AED'],
                    ],
                    'lux' => [
                        ['Burj Al Arab Jumeirah', '7*, icône mondiale, 4500–12000 AED'],
                        ['Atlantis The Royal (Palm)', '5* démesuré, 2500–8000 AED'],
                        ['Armani Hotel Dubai (Burj Khalifa)', 'dans la tour, signé Armani, 2000–5000 AED'],
                    ],
                ],
                'food' => [
                    ['Al Ustad Special Kebab (Bur Dubai)', 'institution depuis 1978, 40–80 AED'],
                    ['Ravi Restaurant (Satwa)', 'pakistanais populaire ouvert 24h, 30–60 AED'],
                    ['Pierchic (Al Qasr)', 'poisson de luxe sur pilotis, 400–900 AED'],
                    ['Zuma Dubai (DIFC)', 'japonais contemporain, 300–700 AED'],
                    ['Bu Qtair (Jumeirah)', 'fish curry, cabane de pêcheurs, 40–80 AED'],
                ],
                'tips' => [
                    "Meilleure saison : novembre à mars. Été : 45°C+, évite sauf budget / shopping.",
                    "Métro très pratique (rouge + verte), carte Nol rechargeable.",
                    "Tenue : couvre épaules et genoux dans les mosquées et souks.",
                    "Alcool uniquement dans les hôtels licenciés. Friday brunch : institution locale.",
                    "Ramadan : respecte le jeûne diurne en public (pas de nourriture/boissons dans la rue).",
                ],
            ],
            'amsterdam' => [
                'name'  => 'Amsterdam',
                'intro' => "Amsterdam, 165 canaux et 1500 ponts — musées mondiaux, vélo pour tous et quartiers créatifs comme Jordaan ou De Pijp.",
                'day_themes' => ['Centre et canaux', 'Musées (Rijks, Van Gogh, Anne Frank)', 'Jordaan et De Pijp', 'Excursion Zaanse Schans ou Keukenhof'],
                'highlights' => [
                    ['Rijksmuseum', 'la Ronde de Nuit de Rembrandt, 22,50 €, à réserver'],
                    ['Van Gogh Museum', 'la plus grande collection au monde, 22 €, à réserver'],
                    ['Maison d\'Anne Frank', 'chefs d\'accusation émouvants, 16 €, tickets 2 mois avant'],
                    ['Vondelpark', 'poumon vert, pique-nique, vélo'],
                    ['Croisière sur les canaux', '1 h, 18–25 €, classique mais unique'],
                    ['Jordaan', 'quartier bohème, Noordermarkt samedi matin'],
                    ['De Pijp + Albert Cuypmarkt', 'marché ethnique, ambiance multicolore'],
                    ['Zaanse Schans (excursion)', 'moulins à vent traditionnels, 20 min de train'],
                ],
                'hotels' => [
                    'eco' => [
                        ['ClinkNOORD Hostel', 'design, au nord, 40–80 €'],
                        ['Generator Amsterdam', 'hostel-hôtel, 60–110 €'],
                        ['The Student Hotel (West)', '3*, moderne, 90–150 €'],
                    ],
                    'moyen' => [
                        ['Hotel V Nesplein', 'boutique, cœur de ville, 180–300 €'],
                        ['The Hoxton Amsterdam', 'design, canal Herengracht, 220–380 €'],
                        ['Andaz Amsterdam Prinsengracht', '5* Hyatt, 250–450 €'],
                    ],
                    'lux' => [
                        ['Waldorf Astoria Amsterdam', '5* dans 6 maisons de canal, 500–1000 €'],
                        ['The Dylan Amsterdam', 'boutique 5*, 400–800 €'],
                        ['Conservatorium Hotel', '5* Design Hotels, 600–1200 €'],
                    ],
                ],
                'food' => [
                    ['Winkel 43 (Jordaan)', 'apple pie culte, 5–8 €'],
                    ['De Kas (Oud-Oost)', 'farm-to-table dans une serre, 60–100 €'],
                    ['Foodhallen', 'street food hall branché, 10–20 €'],
                    ['Haesje Claes', 'taverne hollandaise traditionnelle, 25–45 €'],
                    ['Febo', 'snacks à distributeurs, expérience locale, 3–5 €'],
                ],
                'tips' => [
                    "Loue un vélo dès l\'arrivée (15 €/j) : c\'est la façon locale.",
                    "I Amsterdam City Card : transports + 40 musées, à partir de 60 €/24h.",
                    "Réserve Anne Frank et Van Gogh 6-8 semaines à l\'avance en haute saison.",
                    "Red Light District : marche mais pas de photos, c\'est interdit et très mal vu.",
                    "Prononce « Van Gogh » à la hollandaise (« fan gokhh ») pour briller.",
                ],
            ],
            'prague' => [
                'name'  => 'Prague',
                'intro' => "Prague garde intact son centre gothique, baroque et Art nouveau — la Vltava serpente entre le Pont Charles et le Château, avec la Vieille Ville en écrin.",
                'day_themes' => ['Vieille Ville et Pont Charles', 'Château et Malá Strana', 'Josefov et Nové Město', 'Excursion Český Krumlov ou Kutná Hora'],
                'highlights' => [
                    ['Pont Charles (Karlův most)', 'traverse à l\'aube ou à la nuit, 30 statues baroques'],
                    ['Place de la Vieille-Ville + Horloge astronomique', 'show chaque heure, mosaïque 600 ans'],
                    ['Château de Prague + cathédrale Saint-Guy', 'plus grand château complexe d\'Europe, 250 CZK'],
                    ['Quartier juif (Josefov)', 'vieux cimetière, 6 synagogues, billet combiné 500 CZK'],
                    ['Malá Strana + Église Saint-Nicolas', 'baroque sublime, vue depuis la galerie 100 CZK'],
                    ['Maison Dansante + Petřín', 'architecture Gehry, colline avec mini tour Eiffel'],
                    ['Bar à bière (pivnice) U Fleků', 'brasserie depuis 1499, 70 CZK la pinte'],
                    ['Český Krumlov (excursion)', 'ville médiévale UNESCO, 3 h de bus'],
                ],
                'hotels' => [
                    'eco' => [
                        ['Mosaic House Prague', 'hostel-hôtel design, 50–90 €'],
                        ['Hostel ONE Home', 'très sociable, 25–40 €'],
                        ['Hotel Golden Well Old Town', '3*, central, 80–140 €'],
                    ],
                    'moyen' => [
                        ['Hotel U Prince (Vieille Ville)', 'vue sur l\'horloge, 150–260 €'],
                        ['Mandarin Oriental Prague (Malá Strana)', '5*, ancien couvent, 300–550 €'],
                        ['NYX Hotel Prague', 'design 4*, 130–220 €'],
                    ],
                    'lux' => [
                        ['Four Seasons Hotel Prague', '5* face au Pont Charles, 400–900 €'],
                        ['Augustine, a Luxury Collection Hotel', '5*, ancien monastère, 350–700 €'],
                        ['Aria Hotel (Malá Strana)', 'thématique musique, 300–550 €'],
                    ],
                ],
                'food' => [
                    ['U Medvídků', 'brasserie 1466, svíčková et goulache, 200–400 CZK'],
                    ['Lokál Dlouhááá', 'cuisine tchèque moderne, bière fraîche, 250–450 CZK'],
                    ['Café Savoy', 'Art nouveau 1893, petit-déj légendaire, 200–400 CZK'],
                    ['Sansho', 'asiatique moderne, 500–800 CZK'],
                    ['Trdelník (tout le centre)', 'pâtisserie spirale, 80–120 CZK'],
                ],
                'tips' => [
                    "Monnaie : couronne tchèque (CZK), 1 € ≈ 25 CZK. Évite de changer dans les bureaux du centre (arnaques).",
                    "Prague Card : transports + 50 musées, 1550 CZK pour 2 jours.",
                    "Métro + tram excellents, billet 30 min = 30 CZK.",
                    "L\'Horloge astronomique : le défilé des apôtres est court, reste près du cadran.",
                    "Les menus traduits en 5 langues sont à fuir — vise les pivnice de quartier.",
                ],
            ],
            'budapest' => [
                'name'  => 'Budapest',
                'intro' => "Budapest, la \"perle du Danube\", associe Buda la colline (château, bastion) et Pest la vivante (Parlement, boulevards Art nouveau) avec des bains thermaux uniques.",
                'day_themes' => ['Pest (Parlement, Basilique)', 'Buda (château, bastion)', 'Bains thermaux', 'Excursion Szentendre'],
                'highlights' => [
                    ['Parlement hongrois', '2ème plus grand parlement d\'Europe, visite 8000 HUF (réserve)'],
                    ['Bastion des Pêcheurs + Église Matthias', 'vue mythique sur Pest, 1300 HUF'],
                    ['Pont des Chaînes', 'pont historique, traverse à pied de nuit'],
                    ['Bains Széchenyi', 'piscines en plein air néobaroques, 7500 HUF'],
                    ['Marché central (Vásárcsarnok)', 'produits hongrois, paprika, foie gras, 2ème étage gastronomique'],
                    ['Quartier juif + ruin bars', 'Szimpla Kert = THE ruin bar originel'],
                    ['Île Marguerite', 'parc flottant sur le Danube, chevalets ou vélo'],
                    ['Szentendre (excursion)', 'ville d\'artistes à 20 km, HÉV depuis Batthyány tér'],
                ],
                'hotels' => [
                    'eco' => [
                        ['Maverick Urban Lodge', 'hostel central, 25–50 €'],
                        ['Danubius Hotel Astoria', 'classique historique, 70–130 €'],
                        ['Bo18 Hotel Superior', '3* central, 80–150 €'],
                    ],
                    'moyen' => [
                        ['Aria Hotel Budapest', '5* musical, rooftop, 200–400 €'],
                        ['Prestige Hotel Budapest', 'boutique 4*, 130–240 €'],
                        ['Corinthia Budapest', '5* historique 1896, 180–320 €'],
                    ],
                    'lux' => [
                        ['Four Seasons Hotel Gresham Palace', '5* Art nouveau, 400–900 €'],
                        ['Matild Palace, A Luxury Collection', '5* rouvert 2021, 350–700 €'],
                        ['Párisi Udvar Hotel', '5* dans passage néo-gothique, 300–600 €'],
                    ],
                ],
                'food' => [
                    ['Kispiac Bisztró', 'cuisine hongroise moderne, 8000–15000 HUF'],
                    ['Menza (Liszt Ferenc tér)', 'ambiance rétro, goulash, 3500–7000 HUF'],
                    ['Gerbeaud (place Vörösmarty)', 'pâtisserie depuis 1858, 2000–4000 HUF'],
                    ['Bors Gastrobár', 'baguettes garnies, 2000–3500 HUF'],
                    ['Halászbástya Étterem', 'bastion, vue Parlement, 15000–30000 HUF'],
                ],
                'tips' => [
                    "Budapest Card : transports + bains Lukács + 25 musées, à partir de 33 €/24h.",
                    "Bains Gellért ou Rudas (coupole turque) pour une alternative aux Széchenyi.",
                    "Monnaie : forint (HUF), change dans un bureau officiel, PAS dans la rue.",
                    "Le métro M1 (ligne jaune) est patrimoine UNESCO — la 2ème plus ancienne du continent.",
                    "Ruin bars du quartier juif : Szimpla Kert, Instant, Mazel Tov, ouverture tard.",
                ],
            ],
            'seville' => [
                'name'  => 'Séville',
                'intro' => "Séville, capitale de l\'Andalousie, distille patios fleuris, flamenco, tapas et trois monuments UNESCO (Cathédrale, Alcazar, Archives des Indes).",
                'day_themes' => ['Centre et monuments UNESCO', 'Triana et flamenco', 'Plaza de España et Parc de María Luisa', 'Excursion Cordoue ou Cadix'],
                'highlights' => [
                    ['Cathédrale + Giralda', 'plus grande cathédrale gothique du monde, tombeau de Colomb, 11 €'],
                    ['Real Alcázar', 'palais mudéjar, jardins, décor de Game of Thrones (Dorne), 14,50 €'],
                    ['Plaza de España', 'ensemble néo-mudéjar du parc María Luisa, ballades en barque'],
                    ['Metropol Parasol (Las Setas)', 'plus grande structure bois au monde, rooftop 15 €'],
                    ['Triana', 'quartier flamenco, Azulejos, tapas au Bar Santa Ana'],
                    ['Museo del Baile Flamenco', 'spectacle quotidien, 27 €'],
                    ['Torre del Oro + Guadalquivir', 'croisière 20 € ou simple balade au bord du fleuve'],
                    ['Cordoue (excursion)', 'Mosquée-Cathédrale, patios UNESCO, 45 min en AVE'],
                ],
                'hotels' => [
                    'eco' => [
                        ['La Banda Rooftop Hostel', 'hostel avec rooftop, 25–45 €'],
                        ['Hotel Amadeus Sevilla', 'boutique musical, 80–130 €'],
                        ['Hotel Simón', 'palais du XVIIIème simple, 90–160 €'],
                    ],
                    'moyen' => [
                        ['Mercer Sevilla', '5* palais du XIXème, piscine sur le toit, 300–500 €'],
                        ['EME Catedral Mercer', '5* face à la cathédrale, 250–450 €'],
                        ['Hotel Alfonso XIII', 'icône néomudéjar 5*, 350–650 €'],
                    ],
                    'lux' => [
                        ['Palacio de Villapanés', 'palais XVIIIème, piscine patio, 400–700 €'],
                        ['Hotel Alfonso XIII (A Luxury Collection)', 'le plus iconique, 400–900 €'],
                        ['Gran Meliá Colón', '5* face à l\'arène, 250–450 €'],
                    ],
                ],
                'food' => [
                    ['El Rinconcillo', 'plus vieux bar à tapas d\'Europe (1670), 3–12 €/tapa'],
                    ['Casa Morales', 'taberna 1850, solera au tonneau, 10–25 €'],
                    ['Eslava (San Lorenzo)', 'tapas modernes, huevo sobre bizcocho de boletus, 4–8 €/tapa'],
                    ['Bodeguita Casablanca', 'tapas derrière la Giralda, 3–10 €/tapa'],
                    ['Abades Triana', 'cuisine andalouse revisitée, vue sur le fleuve, 50–90 €'],
                ],
                'tips' => [
                    "Meilleure saison : mars-mai et octobre. Juillet-août : 42°C, siesta obligatoire.",
                    "La Feria de Avril et la Semaine sainte sont spectaculaires mais bondées, réserve 6 mois avant.",
                    "Pass Sevilla Card inclut Alcázar + Cathédrale + transports.",
                    "Pour le flamenco : La Casa del Flamenco ou Los Gallos, pas les spectacles à touristes du centre.",
                    "Dîne tard : 21 h est l\'heure normale en Andalousie.",
                ],
            ],
            'reykjavik' => [
                'name'  => 'Reykjavik',
                'intro' => "Reykjavik, plus petite capitale d\'Europe, est la porte d\'entrée de l\'Islande — volcans, aurores boréales, geysers et Blue Lagoon à moins de 1 h.",
                'day_themes' => ['Centre de Reykjavik', 'Cercle d\'or (Geysir, Gullfoss, Thingvellir)', 'Côte Sud (cascades + plages noires)', 'Blue Lagoon + Sky Lagoon'],
                'highlights' => [
                    ['Hallgrímskirkja', 'église emblématique, tour panoramique 1400 ISK'],
                    ['Sun Voyager (Sólfar)', 'sculpture navire viking en bord de mer'],
                    ['Harpa (salle de concert)', 'architecture primée, visites guidées'],
                    ['Cercle d\'or (Geysir + Gullfoss + Thingvellir)', 'excursion journée, loueur à partir de 70 €'],
                    ['Blue Lagoon (Grindavík)', 'bain géothermique légendaire, 82–105 € selon période'],
                    ['Sky Lagoon (Kópavogur)', 'alternative plus locale, rituel 7 étapes 70 €'],
                    ['Seljalandsfoss + Skógafoss', 'cascades majestueuses sur la côte sud'],
                    ['Reynisfjara', 'plage de sable noir avec orgues basaltiques'],
                ],
                'hotels' => [
                    'eco' => [
                        ['Loft HI Hostel', 'hostel central, 70–130 €'],
                        ['Kex Hostel', 'design dans une ancienne biscuiterie, 80–150 €'],
                        ['Fosshotel Reykjavik', '4* moderne, 160–280 €'],
                    ],
                    'moyen' => [
                        ['Hotel Borg by Keahotels', 'icône Art déco 1930, 280–450 €'],
                        ['Sand Hotel by Keahotels', '4* sur Laugavegur, 250–400 €'],
                        ['Alda Hotel Reykjavik', '4*, central, 220–380 €'],
                    ],
                    'lux' => [
                        ['The Reykjavik EDITION', '5* récent, luxe discret, 450–800 €'],
                        ['Tower Suites Reykjavik', 'penthouses vue panoramique, 500–900 €'],
                        ['Ion Adventure Hotel (Nesjavellir)', 'design au milieu des volcans, 400–700 €'],
                    ],
                ],
                'food' => [
                    ['Bæjarins Beztu Pylsur', 'hot-dog culte (Bill Clinton fan), 6 €'],
                    ['Matur og Drykkur', 'cuisine islandaise revisitée, 80–120 €'],
                    ['Dill Restaurant', '1 étoile Michelin, menu dégustation 200 €+'],
                    ['Sægreifinn', 'soupe de langouste dans un hangar, 25–40 €'],
                    ['Fiskmarkadurinn', 'poisson contemporain, 70–110 €'],
                ],
                'tips' => [
                    "Aurores boréales : septembre-mars, il faut des nuits claires — application Aurora Forecast.",
                    "Loue une voiture dès l\'aéroport (Keflavík) pour les excursions — bus très limités.",
                    "L\'Islande est chère : prévois un budget 100-200 €/jour minimum hors hébergement.",
                    "Remplis ta gourde au robinet : l\'eau est la meilleure au monde et gratuite partout.",
                    "Respecte la nature : pas de hors-piste, les mousses mettent 100 ans à repousser.",
                ],
            ],
            'default' => [
                'name'  => 'ta destination',
                'intro' => "Voici un itinéraire adapté à ta demande.",
                'highlights' => [
                    ['Cœur historique', 'pour sentir l\'âme de l\'endroit dès le premier jour'],
                    ['Un musée ou monument majeur', 'le site qui raconte le mieux l\'histoire locale'],
                    ['Quartier vivant', 'pour l\'ambiance de soirée et la cuisine locale'],
                    ['Point de vue / panorama', 'un miradouro, une colline ou un rooftop'],
                    ['Marché local', 'pour comprendre la culture par les produits'],
                    ['Excursion d\'une journée', 'nature ou village voisin accessible en train/bus'],
                ],
                'hotels' => [
                    'eco'   => [
                        ['Auberge de jeunesse centrale', 'dortoir propre, wifi, généralement 15–40 €/nuit'],
                        ['Hôtel 2-3* central', 'chambre simple bien placée, 50–90 €/nuit'],
                        ['Location meublée (Airbnb / Booking Apart)', 'studio cœur de ville, 40–80 €/nuit'],
                    ],
                    'moyen' => [
                        ['Hôtel 4* de quartier', 'bon standing, petit-déj inclus, 100–180 €/nuit'],
                        ['Boutique-hôtel de charme', 'décor local, 120–200 €/nuit'],
                        ['Hôtel de chaîne milieu de gamme', 'confort prévisible, 90–160 €/nuit'],
                    ],
                    'lux'   => [
                        ['Palace ou 5* historique', 'adresse de prestige, 350–700 €/nuit'],
                        ['Suite avec vue', 'vue panoramique ou rooftop, 300–600 €/nuit'],
                        ['Design Hotel / Leading Hotel of the World', 'service sur-mesure, 400–900 €/nuit'],
                    ],
                ],
                'food' => [
                    ['Marché central', 'immersion locale et prix bas (5-15 €)'],
                    ['Rue gastronomique du centre', 'identifie une rue où les restaurants n\'affichent PAS de menu en 8 langues'],
                    ['Food hall moderne', 'choix varié pour 10-25 €'],
                    ['Bistro local de quartier', 'menu du jour à prix doux, 15-30 €'],
                ],
                'tips' => [
                    "Prends la carte de transport locale dès l\'arrivée, souvent rentable dès 3 trajets.",
                    "Réserve les sites les plus populaires en ligne à l\'avance pour éviter les files.",
                    "Mange où mangent les locaux : suis les files de midi, évite les menus traduits en 8 langues.",
                    "Télécharge Google Maps hors-ligne de la zone avant de partir.",
                    "Prévois 1 journée « respiration » sans programme fixé.",
                ],
            ],
        ];
    }

    private function localComment(string $prompt): string
    {
        $pool = [
            "Magnifique partage, cela donne vraiment envie de préparer les valises.",
            "Quelle belle découverte, merci pour ce récit inspirant qui fait voyager.",
            "Superbe, l'ambiance que tu décris donne immédiatement envie d'y être.",
            "Merci pour ce partage, cette destination vient de rejoindre ma liste de rêves.",
            "Incroyable voyage — aurais-tu des astuces pratiques pour organiser un séjour similaire ?",
            "Tes mots captent à merveille l'atmosphère du lieu, j'ai l'impression d'y être.",
        ];
        return $pool[array_rand($pool)];
    }

    private function localDestinations(string $p): string
    {
        $p = mb_strtolower($p);
        if ($this->hasAny($p, ['plage', 'mer', 'soleil', 'balnéaire', 'balneaire', 'tropical'])) {
            return "1. **Maldives** - Paradis tropical avec bungalows sur pilotis et récifs coralliens.\n"
                 . "2. **Santorin, Grèce** - Îles aux couchers de soleil inoubliables et ruelles blanches.\n"
                 . "3. **Djerba, Tunisie** - Plages de sable fin et patrimoine méditerranéen.";
        }
        if ($this->hasAny($p, ['culture', 'histoire', 'musée', 'musee', 'patrimoine'])) {
            return "1. **Rome, Italie** - Colisée, Vatican et gastronomie exceptionnelle.\n"
                 . "2. **Kyoto, Japon** - Temples anciens, jardins zen et traditions millénaires.\n"
                 . "3. **Fès, Maroc** - Médina classée UNESCO et artisanat traditionnel.";
        }
        if ($this->hasAny($p, ['aventure', 'trek', 'randonnée', 'randonnee', 'montagne'])) {
            return "1. **Patagonie, Argentine** - Glaciers majestueux et nature sauvage préservée.\n"
                 . "2. **Népal** - Sentiers himalayens et villages accrochés aux sommets.\n"
                 . "3. **Islande** - Volcans, cascades et aurores boréales.";
        }
        return "1. **Bali, Indonésie** - Temples mystiques, rizières en terrasses et plages paradisiaques.\n"
             . "2. **Islande** - Aurores boréales, geysers et paysages lunaires à couper le souffle.\n"
             . "3. **Patagonie, Argentine** - Glaciers majestueux et nature sauvage préservée.";
    }

    private function localDescription(string $prompt): string
    {
        $p = mb_strtolower($prompt);

        // ---- Mode intégration : si l'utilisateur demande d'insérer une expression
        // dans son texte de base, on respecte ça côté fallback (sinon on perdrait
        // l'intention quand les providers IA sont injoignables). ----
        // Texte de base capturé entre toutes formes de guillemets (ASCII, courbe, français).
        $quoted = '(?:"([^"]{2,500})"|«\s*([^»]{2,500})\s*»|“([^”]{2,500})”|\'([^\']{2,500})\')';
        $hasBase = preg_match('/Texte\s+d[eé]j[aà]\s+[eé]crit\s*:\s*' . $quoted . '/iu', $prompt, $tm);

        // Phrase à insérer : 1) entre guillemets, 2) sinon après le verbe d'intégration.
        $insert = '';
        $quoted2 = '(?:"([^"]{2,120})"|«\s*([^»]{2,120})\s*»|“([^”]{2,120})”|\'([^\']{2,120})\')';
        if (preg_match('/(?:int[eè]gre|inclus|ajoute|ins[eè]re|place|utilise(?:\s+l[\'’]expression|\s+le\s+mot)?|met[s]?(?:\s+l[\'’]expression|\s+le\s+mot)?|avec\s+(?:la\s+phrase|le\s+mot|l[\'’]expression))\s*(?:l[\'’]expression\s*|le\s+mot\s*)?[:\-]?\s*' . $quoted2 . '/iu', $prompt, $em)) {
            $insert = trim($em[1] ?: $em[2] ?? '') ?: trim($em[3] ?? '') ?: trim($em[4] ?? '');
        } elseif (preg_match('/(?:int[eè]gre|inclus|ajoute|ins[eè]re|place|utilise(?:\s+l[\'’]expression|\s+le\s+mot)?|met[s]?(?:\s+l[\'’]expression|\s+le\s+mot)?)\s+(?:l[\'’]expression\s+|le\s+mot\s+|la\s+phrase\s+)?([^.!?\n,;]{2,120})/iu', $prompt, $em)) {
            $candidate = trim($em[1]);
            $candidate = preg_replace(
                '/\s+(?:et|puis|ensuite|stp|s\'?il\s+te\s+plait|s\'?il\s+vous\s+plait|au\s+d[eé]but|a\s+la\s+fin|au\s+milieu|dans\s+la\s+phrase|dans\s+le\s+texte|reste\s+\w+|en\s+gardant|en\s+restant)\b.*$/iu',
                '',
                $candidate
            );
            $candidate = trim($candidate, " \t\n\r\0\x0B.,;:");
            if (mb_strlen($candidate) >= 2 && !preg_match('/^(?:ca|cela|ce|cette|cet|le|la|les|du|de|des|un|une)$/iu', $candidate)) {
                $insert = $candidate;
            }
        }

        $base = $hasBase ? trim($tm[1] ?? $tm[2] ?? $tm[3] ?? $tm[4] ?? '') : '';

        if ($base !== '' && $insert !== '') {
            // Si la phrase à insérer est déjà dedans, on ne touche pas.
            if (mb_stripos($base, $insert) !== false) {
                return rtrim($base, '.!?…') . '.';
            }
            // Sinon on l'insère naturellement (en début si le texte est court, en milieu sinon).
            $base = rtrim($base, ' .!?…');
            $merged = mb_strlen($base) < 60
                ? ucfirst($insert) . ' : ' . lcfirst($base) . '.'
                : $base . ' — ' . $insert . '.';
            return $merged;
        }

        // ---- Thèmes ----
        $themeMap = [
            'plage'     => ['plage', 'mer', 'océan', 'ocean', 'sable', 'balnéaire', 'balneaire', 'tropical', 'lagon', 'côte', 'cote'],
            'montagne'  => ['montagne', 'alpe', 'alpes', 'sommet', 'randonnée', 'randonnee', 'trek', 'altitude', 'neige', 'ski'],
            'ville'     => ['ville', 'métropole', 'metropole', 'urbain', 'citytrip', 'capitale'],
            'desert'    => ['désert', 'desert', 'dune', 'sahara', 'oasis'],
            'foret'     => ['forêt', 'foret', 'jungle', 'amazonie'],
            'culture'   => ['culture', 'histoire', 'musée', 'musee', 'patrimoine', 'monument', 'temple', 'ruines'],
            'gastro'    => ['gastronomie', 'cuisine', 'culinaire', 'street food', 'restaurant'],
            'aventure'  => ['aventure', 'extrême', 'extreme', 'adrénaline', 'adrenaline', 'rafting', 'plongée', 'plongee'],
            'nature'    => ['nature', 'paysage', 'faune', 'flore', 'safari', 'parc national', 'réserve', 'reserve'],
            'spirituel' => ['spirituel', 'retraite', 'yoga', 'méditation', 'meditation', 'zen'],
            'romance'   => ['romantique', 'couple', 'lune de miel', 'amoureux'],
            'famille'   => ['famille', 'enfants', 'familial'],
            'luxe'      => ['luxe', 'palace', 'resort', 'prestige'],
            'budget'    => ['pas cher', 'budget', 'économique', 'economique', 'backpack', 'routard'],
            'festif'    => ['fête', 'fete', 'festival', 'nightlife', 'nuit'],
            'roadtrip'  => ['road trip', 'roadtrip', 'route', 'van', 'camping-car'],
            'hiver'     => ['neige', 'ski', 'hiver', 'glace', 'igloo'],
            'ete'       => ['été', 'chaleur', 'soleil', 'canicule'],
        ];
        $themes = [];
        foreach ($themeMap as $tag => $ws) if ($this->hasAny($p, $ws)) $themes[] = $tag;

        // ---- Lieux ----
        $places = [
            'paris' => 'Paris', 'tunisie' => 'la Tunisie', 'tunis' => 'Tunis', 'djerba' => 'Djerba',
            'maroc' => 'le Maroc', 'marrakech' => 'Marrakech', 'japon' => 'le Japon', 'tokyo' => 'Tokyo',
            'kyoto' => 'Kyoto', 'italie' => "l'Italie", 'rome' => 'Rome', 'venise' => 'Venise',
            'grèce' => 'la Grèce', 'grece' => 'la Grèce', 'santorin' => 'Santorin',
            'espagne' => "l'Espagne", 'barcelone' => 'Barcelone', 'madrid' => 'Madrid',
            'portugal' => 'le Portugal', 'lisbonne' => 'Lisbonne',
            'bali' => 'Bali', 'thailande' => 'la Thaïlande', 'thaïlande' => 'la Thaïlande', 'bangkok' => 'Bangkok',
            'vietnam' => 'le Vietnam', 'cambodge' => 'le Cambodge',
            'inde' => "l'Inde", 'népal' => 'le Népal', 'nepal' => 'le Népal',
            'islande' => "l'Islande", 'norvège' => 'la Norvège', 'norvege' => 'la Norvège',
            'suède' => 'la Suède', 'suede' => 'la Suède', 'finlande' => 'la Finlande',
            'egypte' => "l'Égypte", 'égypte' => "l'Égypte",
            'turquie' => 'la Turquie', 'istanbul' => 'Istanbul',
            'new york' => 'New York', 'usa' => 'les États-Unis', 'états-unis' => 'les États-Unis',
            'canada' => 'le Canada', 'québec' => 'le Québec', 'quebec' => 'le Québec',
            'brésil' => 'le Brésil', 'bresil' => 'le Brésil', 'argentine' => "l'Argentine",
            'pérou' => 'le Pérou', 'perou' => 'le Pérou', 'mexique' => 'le Mexique',
            'maldives' => 'les Maldives', 'seychelles' => 'les Seychelles', 'dubai' => 'Dubaï', 'dubaï' => 'Dubaï',
            'corse' => 'la Corse', 'sicile' => 'la Sicile', 'sardaigne' => 'la Sardaigne',
            'ibiza' => 'Ibiza', 'mykonos' => 'Mykonos', 'crete' => 'la Crète', 'crète' => 'la Crète',
            'hammamet' => 'Hammamet', 'sousse' => 'Sousse', 'monastir' => 'Monastir', 'tozeur' => 'Tozeur', 'tabarka' => 'Tabarka',
        ];
        $placeHit = null;
        foreach ($places as $k => $v) if (str_contains($p, $k)) { $placeHit = $v; break; }

        // ---- Saison ----
        $season = null;
        if ($this->hasAny($p, ['été', 'ete', 'juillet', 'août', 'aout', 'canicule'])) $season = 'ete';
        elseif ($this->hasAny($p, ['hiver', 'janvier', 'février', 'fevrier', 'décembre', 'decembre', 'neige'])) $season = 'hiver';
        elseif ($this->hasAny($p, ['printemps', 'mars', 'avril', 'mai'])) $season = 'printemps';
        elseif ($this->hasAny($p, ['automne', 'septembre', 'octobre', 'novembre'])) $season = 'automne';

        // ---- Fragments (évitent "con", "tue", "viol"…) ----
        $openings = [];
        $middles  = [];
        $closings = [];

        $add = function (string $t) use (&$openings, &$middles, &$closings): void {
            switch ($t) {
                case 'plage':
                    $openings[] = "Imaginez des eaux cristallines qui caressent un sable doré sous un soleil généreux.";
                    $openings[] = "Rien ne vaut le chant des vagues et l'odeur iodée d'une mer infinie.";
                    $openings[] = "Un horizon turquoise, des cocotiers qui ploient sous la brise et le silence du lagon.";
                    $middles[]  = "Entre baignades paresseuses, snorkeling et couchers de soleil flamboyants, chaque journée devient une parenthèse enchantée.";
                    $middles[]  = "Farniente au bord de l'eau, balades pieds nus et fruits de mer au coucher du soleil rythment les journées.";
                    $closings[] = "Une escapade balnéaire idéale pour se ressourcer et repartir la tête pleine de lumière.";
                    $closings[] = "De quoi oublier le tumulte du quotidien et ne garder que le bruit des vagues.";
                    break;
                case 'montagne':
                    $openings[] = "Au creux des sommets, entre ciel et roche, le silence prend une dimension nouvelle.";
                    $openings[] = "Les sentiers grimpent, l'air se fait plus pur et chaque pas révèle un panorama à couper le souffle.";
                    $openings[] = "Là-haut, les nuages rasent la crête et la lumière dessine la roche minute après minute.";
                    $middles[]  = "Refuges chaleureux, lacs d'altitude et points de vue vertigineux rythment l'ascension.";
                    $middles[]  = "Bivouacs étoilés, fromages d'alpage et sentiers serpentant entre les pins dessinent un itinéraire inoubliable.";
                    $closings[] = "Une aventure en montagne qui ramène à l'essentiel.";
                    $closings[] = "Un grand bol d'air pour recharger le corps et éclaircir l'esprit.";
                    break;
                case 'ville':
                    $openings[] = "Plongez au cœur d'une métropole vibrante où l'histoire se mêle à l'effervescence moderne.";
                    $openings[] = "Ruelles bouillonnantes, façades qui racontent des siècles, terrasses où la vie locale se joue en public.";
                    $middles[]  = "Des cafés animés aux ruelles cachées, chaque quartier raconte une histoire et dévoile ses trésors.";
                    $middles[]  = "Musées incontournables, boutiques d'artisans et adresses secrètes composent un itinéraire riche.";
                    $closings[] = "Une immersion urbaine parfaite pour qui aime vibrer au rythme de la ville.";
                    $closings[] = "Le genre de séjour qui laisse la tête pleine d'images et les papilles encore en éveil.";
                    break;
                case 'desert':
                    $openings[] = "À perte de vue, les dunes dorées ondulent comme une mer figée sous un ciel immense.";
                    $openings[] = "Le sable chante sous le pas, l'horizon se dérobe et la chaleur devient un paysage à part entière.";
                    $middles[]  = "Entre nuits étoilées autour du feu, thé à la menthe et caravanes lointaines, le temps semble suspendu.";
                    $middles[]  = "Dromadaires au crépuscule, oasis improbables et bivouacs sous la voûte céleste rythment l'aventure.";
                    $closings[] = "Une expérience rare, aussi silencieuse que grandiose.";
                    $closings[] = "Un voyage qui réapprend la valeur du silence et de l'immensité.";
                    break;
                case 'foret':
                    $openings[] = "Sous une canopée vivante, la lumière filtre en rayons et la forêt murmure ses secrets.";
                    $middles[]  = "Chants d'oiseaux, cascades cachées et sentiers mousseux invitent à une exploration sensorielle.";
                    $closings[] = "Une immersion verdoyante pour respirer profondément et ralentir.";
                    break;
                case 'culture':
                    $openings[] = "Entre monuments emblématiques et ruelles chargées d'histoire, chaque pierre raconte une histoire.";
                    $openings[] = "Un voyage où l'art, la mémoire et les traditions forment la trame quotidienne.";
                    $middles[]  = "Musées, palais et échanges avec les locaux font voyager à travers les siècles.";
                    $middles[]  = "Des fresques millénaires aux festivals traditionnels, la culture se vit pleinement.";
                    $closings[] = "Un périple riche en découvertes et en émotions.";
                    $closings[] = "Une leçon d'histoire grandeur nature, à savourer sans se presser.";
                    break;
                case 'gastro':
                    $openings[] = "Carnet gourmand en main, partez à la découverte de saveurs authentiques et de parfums envoûtants.";
                    $openings[] = "Le voyage passe ici par l'assiette : épices, produits frais et savoir-faire ancestral.";
                    $middles[]  = "Marchés colorés, tables familiales et adresses secrètes racontent une culture par l'assiette.";
                    $middles[]  = "Des petits producteurs aux chefs étoilés, chaque dégustation est une découverte.";
                    $closings[] = "Un voyage culinaire qui se savoure autant qu'il se vit.";
                    $closings[] = "De quoi repartir avec des souvenirs gustatifs et quelques recettes dans la valise.";
                    break;
                case 'aventure':
                    $openings[] = "L'adrénaline monte : des sensations fortes attendent les voyageurs en quête d'intensité.";
                    $openings[] = "Hors des sentiers battus, l'aventure se vit en mode brut et sincère.";
                    $middles[]  = "Entre activités sportives, itinéraires sauvages et échanges impromptus, la routine s'efface.";
                    $middles[]  = "Canyoning, via ferrata, kayak et bivouacs au bord du vide forgent des souvenirs indélébiles.";
                    $closings[] = "Une aventure qui réveille l'explorateur qui sommeille en vous.";
                    $closings[] = "Pour qui aime quand le voyage laisse des traces — dans les jambes et dans la mémoire.";
                    break;
                case 'nature':
                    $openings[] = "Paysages grandioses, faune préservée et horizons sans limites : la nature est ici reine.";
                    $middles[]  = "Chaque levé de jour dévoile un tableau différent, chaque sentier un émerveillement.";
                    $closings[] = "Une échappée nature pour se reconnecter… pour se relier au vivant.";
                    break;
                case 'spirituel':
                    $openings[] = "Loin du bruit, ce voyage invite à l'écoute de soi et à la lenteur retrouvée.";
                    $middles[]  = "Yoga matinal, méditations face au paysage et silence habité composent des journées apaisantes.";
                    $closings[] = "Une parenthèse régénérante pour le corps et l'esprit.";
                    break;
                case 'romance':
                    $openings[] = "Une destination pensée pour le cœur, où chaque instant se partage à deux.";
                    $middles[]  = "Dîners aux chandelles, balades main dans la main et couchers de soleil complices rythment le séjour.";
                    $closings[] = "Le cadre rêvé pour une escapade romantique inoubliable.";
                    break;
                case 'famille':
                    $openings[] = "Pensée pour petits et grands, cette destination réconcilie toutes les envies.";
                    $middles[]  = "Activités ludiques, hébergements agréables et découvertes accessibles font le bonheur de la tribu.";
                    $closings[] = "Des souvenirs à partager qui resteront longtemps dans les albums de famille.";
                    break;
                case 'luxe':
                    $openings[] = "Du raffinement jusque dans les moindres détails, ce voyage est une ode à l'élégance.";
                    $middles[]  = "Palaces d'exception, spa panoramiques et services sur-mesure subliment l'expérience.";
                    $closings[] = "Une parenthèse de prestige à savourer sans compter.";
                    break;
                case 'budget':
                    $openings[] = "Voyager autrement, c'est possible : cette destination prouve que le dépaysement ne coûte pas cher.";
                    $middles[]  = "Auberges chaleureuses, transports locaux et street food savoureuse dessinent un itinéraire accessible.";
                    $closings[] = "La preuve qu'une aventure authentique tient aussi dans un petit budget.";
                    break;
                case 'festif':
                    $openings[] = "La nuit s'embrase : musique, lumières et danses donnent le ton d'un séjour inoubliable.";
                    $middles[]  = "Festivals vibrants, rooftops animés et soirées spontanées composent une ambiance électrique.";
                    $closings[] = "Pour qui veut un voyage qui ne s'éteint jamais vraiment.";
                    break;
                case 'roadtrip':
                    $openings[] = "Volant en main, fenêtre ouverte, l'aventure commence dès le premier kilomètre.";
                    $middles[]  = "Villages de caractère, panoramas changeants et bivouacs improvisés rythment la route.";
                    $closings[] = "Un road trip à la carte pour qui aime la liberté du mouvement.";
                    break;
            }
        };
        foreach ($themes as $t) $add($t);

        // Accroche avec lieu si détecté.
        $placed = null;
        if ($placeHit !== null) {
            $variants = [
                "Cap sur {$placeHit}, une destination qui ne laisse personne indifférent.",
                "Direction {$placeHit} : un cadre qui a tout pour marquer les esprits.",
                "Envie d'ailleurs ? {$placeHit} répond présent, avec son caractère unique.",
                "{$placeHit}, c'est cette destination dont on parle encore longtemps après le retour.",
            ];
            $placed = $variants[array_rand($variants)];
        }

        $seasonLine = null;
        if ($season !== null) {
            $seasonLine = match ($season) {
                'ete'       => "En été, la lumière longue et les soirées douces subliment chaque moment.",
                'hiver'     => "L'hiver y dépose une atmosphère feutrée, propice aux découvertes plus intimes.",
                'printemps' => "Au printemps, les couleurs renaissent et les températures se prêtent aux longues balades.",
                'automne'   => "À l'automne, les tons chauds et la douceur de l'air invitent à la flânerie.",
                default     => null,
            };
        }

        if (empty($openings)) {
            $openings[] = "Il y a des destinations qui marquent, des voyages qui changent — celui-ci en fait partie.";
            $openings[] = "Certains endroits se vivent autant qu'ils se racontent, et celui-ci en est l'exemple parfait.";
            $openings[] = "Et si votre prochain voyage était celui qui change tout ? Cette escapade en a tout le potentiel.";
            $openings[] = "Prendre le temps, s'émerveiller, changer d'air : voilà ce que promet cette destination.";
        }
        if (empty($middles)) {
            $middles[] = "Entre paysages marquants, échanges spontanés et instants suspendus, chaque journée apporte sa dose d'émerveillement.";
            $middles[] = "Des rues animées aux panoramas secrets, l'expérience se dévoile au fil des pas et des détails qui ravissent.";
            $middles[] = "Les saveurs locales, les sourires croisés et les lumières changeantes dessinent un voyage à nul autre pareil.";
        }
        if (empty($closings)) {
            $closings[] = "Un voyage qui restera gravé dans les souvenirs et donnera envie de repartir aussitôt.";
            $closings[] = "Une invitation à explorer, ressentir et revenir transformé.";
            $closings[] = "Le genre d'aventure qui redéfinit ce que voyager veut dire.";
        }

        $parts = [];
        if ($placed) $parts[] = $placed;
        $parts[] = $openings[array_rand($openings)];
        $parts[] = $middles[array_rand($middles)];
        if ($seasonLine) $parts[] = $seasonLine;
        $parts[] = $closings[array_rand($closings)];
        $parts = array_slice($parts, 0, 4);

        // Injecte un mot-clé saillant du prompt si on l'a raté, pour renforcer la pertinence.
        // preg_split avec /u peut renvoyer false si le prompt contient des séquences
        // UTF-8 mal formées — on retombe sur un split classique pour ne pas planter.
        $keywords = preg_split('/\s+/u', trim($prompt));
        if (!is_array($keywords)) {
            $keywords = preg_split('/\s+/', trim($prompt)) ?: [];
        }
        $salient  = null;
        foreach ($keywords as $k) {
            $kk = trim(mb_strtolower($k), " \t\n\r\0\x0B.,!?;:\"'«»");
            if (mb_strlen($kk) >= 4 && !in_array($kk, ['avec', 'dans', 'pour', 'vers', 'sans', 'cette', 'cette', 'mais', 'leur'], true)) {
                $salient = $k;
                break;
            }
        }
        if ($salient && $placeHit === null && empty($themes)) {
            array_splice($parts, 1, 0, "Autour de « " . trim($salient, " .,!?;:\"'«»") . " », l'expérience prend une couleur singulière.");
            $parts = array_slice($parts, 0, 4);
        }

        return implode(' ', $parts);
    }

    private function hasAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $n) if ($n !== '' && str_contains($haystack, $n)) return true;
        return false;
    }
}
