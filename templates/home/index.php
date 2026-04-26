<?php
$heroCards = $previewDestinations;

if ($heroCards === []) {
    $heroCards = [
        ['nom' => 'Paris', 'pays' => 'France', 'continent' => 'Europe', 'prix_base' => 1890, 'description' => 'Escapade premium et art de vivre.'],
        ['nom' => 'Tokyo', 'pays' => 'Japon', 'continent' => 'Asie', 'prix_base' => 2490, 'description' => 'Energie urbaine et design immersif.'],
        ['nom' => 'Safari', 'pays' => 'Kenya', 'continent' => 'Afrique', 'prix_base' => 4990, 'description' => 'Nature, grands espaces et experience signature.'],
    ];
}

$resolveImage = static function (array $destination): string {
    $text = strtolower(trim(($destination['nom'] ?? '').' '.($destination['pays'] ?? '').' '.($destination['continent'] ?? '')));

    return match (true) {
        str_contains($text, 'paris'), str_contains($text, 'france'), str_contains($text, 'europe') => '/assets/java/paris.jpg',
        str_contains($text, 'tokyo'), str_contains($text, 'japon'), str_contains($text, 'asie') => '/assets/java/tokyo.jpg',
        str_contains($text, 'kenya'), str_contains($text, 'tanzanie'), str_contains($text, 'afrique'), str_contains($text, 'safari') => '/assets/java/safari.jpg',
        default => '/assets/java/asia.jpg',
    };
};
?>

<section class="hero-banner">
    <div class="hero-banner__media" style="background-image: linear-gradient(to top, rgba(6, 6, 8, 0.86), rgba(6, 6, 8, 0.28)), url('/assets/java/homepage-hero-video-poster.png');"></div>
    <div class="hero-banner__content">
        <p class="hero-kicker">TripVerse Java -> Symfony</p>
        <h2>On garde le meme esprit visuel que le projet Java et on relie le tout aux CRUD Symfony.</h2>
        <p>
            Palette sombre premium, accents dores, cartes immersives et navigation style dashboard.
            La connexion cible reste la base `voyage`, donc le rendu Symfony peut reutiliser les memes donnees que Java.
        </p>

        <div class="hero-stats">
            <div class="hero-stat">
                <strong><?= $h($stats['destinations'] ?? 0) ?></strong>
                <span>destinations</span>
            </div>
            <div class="hero-stat">
                <strong><?= $h($stats['activites'] ?? 0) ?></strong>
                <span>activites</span>
            </div>
            <div class="hero-stat hero-stat--accent">
                <strong>2</strong>
                <span>CRUD migres</span>
            </div>
        </div>

        <div class="hero-actions">
            <a class="button button-gold" href="/destinations">Explorer les destinations</a>
            <a class="button button-outline-light" href="/activites">Ouvrir les activites</a>
        </div>
    </div>
</section>

<section class="module-strip">
    <article class="module-card">
        <p class="label">CRUD 1</p>
        <h2>Destinations</h2>
        <p>Le module Symfony reprend `DestinationService` et s'affiche maintenant dans un style proche des cards Java.</p>
        <a class="button button-gold" href="/destinations">Aller au CRUD</a>
    </article>

    <article class="module-card">
        <p class="label">CRUD 2</p>
        <h2>Activites</h2>
        <p>Le module Symfony reprend `ActiviteService` avec liaison vers `destination_id` comme dans le projet Java.</p>
        <a class="button button-outline" href="/activites">Voir les activites</a>
    </article>
    <article class="module-card module-card--dark">
        <p class="label">Base de donnees</p>
        <h2>Connexion cible</h2>
        <p>`utils.DataSource` pointe vers `voyage`, donc Symfony est prepare pour la meme base MySQL.</p>
        <span class="tag-pill">Host 127.0.0.1:3306</span>
    </article>
</section>

<section class="showcase-panel">
    <div class="section-head section-head--light">
        <div>
            <p class="label">Apercu design Java</p>
            <h2>Cartes destinations inspirees de `destinations.css`</h2>
            <p>Ces cartes utilisent deja le style visuel Java, et on peut ensuite les brancher sur d'autres modules CRUD.</p>
        </div>
    </div>

    <div class="destination-grid">
        <?php foreach ($heroCards as $destination): ?>
            <article class="destination-card">
                <div class="destination-card__image" style="background-image: linear-gradient(to top, rgba(0,0,0,0.74), rgba(0,0,0,0.18)), url('<?= $h($resolveImage($destination)) ?>');">
                    <span class="destination-badge"><?= $h($destination['continent'] ?? 'Monde') ?></span>
                    <div class="destination-card__overlay">
                        <h3><?= $h($destination['nom'] ?? 'Destination') ?></h3>
                        <p><?= $h($destination['pays'] ?? '') ?></p>
                    </div>
                </div>
                <div class="destination-card__body">
                    <div class="destination-meta">
                        <div>
                            <span>Prix base</span>
                            <strong><?= $h(number_format((float) ($destination['prix_base'] ?? 0), 0, '.', ' ')) ?> TND</strong>
                        </div>
                        <div>
                            <span>Etat</span>
                            <strong>CRUD lie</strong>
                        </div>
                    </div>
                    <p><?= $h($destination['description'] ?? 'Description disponible dans la base Java.') ?></p>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="migration-roadmap">
    <div class="roadmap-step">
        <span>01</span>
        <div>
            <h3>Base visuelle</h3>
            <p>Navbar, hero, cartes et boutons aligns sur le design Java.</p>
        </div>
    </div>
    <div class="roadmap-step">
        <span>02</span>
        <div>
            <h3>CRUD deja relies</h3>
            <p>`destinations` et `activites` sont deja affiches dans cette nouvelle habillage.</p>
        </div>
    </div>
    <div class="roadmap-step">
        <span>03</span>
        <div>
            <h3>Suite logique</h3>
            <p>`user` puis `reclamation/reponse`, ensuite `packages`, `paiements` et `factures`.</p>
        </div>
    </div>
</section>
