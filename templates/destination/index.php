<?php
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

<section class="crud-hero crud-hero--destinations">
    <div>
        <p class="hero-kicker">Legacy CRUD</p>
        <h2>Destinations</h2>
        <p>Le CRUD Symfony garde les colonnes Java mais adopte les cards et les accents visuels du projet JavaFX.</p>
    </div>
    <div class="crud-hero__aside">
        <div class="hero-stat hero-stat--light">
            <strong><?= $h(count($destinations)) ?></strong>
            <span>enregistrements</span>
        </div>
        <a class="button button-gold" href="/destinations/new">Ajouter une destination</a>
    </div>
</section>

<?php if ($destinations === []): ?>
    <section class="empty-panel">
        <h3>Aucune destination disponible</h3>
        <p>Demarre MySQL et verifie la table `destinations` de la base `voyage` pour afficher les cartes Java dans Symfony.</p>
    </section>
<?php else: ?>
    <section class="destination-grid">
        <?php foreach ($destinations as $destination): ?>
            <article class="destination-card">
                <div class="destination-card__image" style="background-image: linear-gradient(to top, rgba(0,0,0,0.76), rgba(0,0,0,0.18)), url('<?= $h($resolveImage($destination)) ?>');">
                    <span class="destination-badge">#<?= $h($destination['id'] ?? '') ?></span>
                    <div class="destination-card__overlay">
                        <h3><?= $h($destination['nom'] ?? '') ?></h3>
                        <p><?= $h(($destination['pays'] ?? '').' - '.($destination['continent'] ?? '')) ?></p>
                    </div>
                </div>

                <div class="destination-card__body">
                    <div class="destination-meta">
                        <div>
                            <span>Prix base</span>
                            <strong><?= $h(number_format((float) ($destination['prix_base'] ?? 0), 2, '.', ' ')) ?> TND</strong>
                        </div>
                        <div>
                            <span>Source</span>
                            <strong>Table Java</strong>
                        </div>
                    </div>
                    <p><?= $h($destination['description'] ?? '') ?></p>
                    <div class="card-actions">
                        <a class="button button-outline" href="/destinations/<?= $h($destination['id']) ?>/edit">Modifier</a>
                        <form method="post" action="/destinations/<?= $h($destination['id']) ?>/delete">
                            <button class="button button-danger" type="submit">Supprimer</button>
                        </form>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
