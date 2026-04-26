<section class="crud-hero crud-hero--activities">
    <div>
        <p class="hero-kicker">Legacy CRUD</p>
        <h2>Activites</h2>
        <p>Les activites sont maintenant presentes dans un rendu type dashboard tout en gardant le schema Java.</p>
    </div>
    <div class="crud-hero__aside">
        <div class="hero-stat hero-stat--light">
            <strong><?= $h(count($activites)) ?></strong>
            <span>activites</span>
        </div>
        <a class="button button-gold" href="/activites/new">Ajouter une activite</a>
    </div>
</section>

<?php if ($activites === []): ?>
    <section class="empty-panel">
        <h3>Aucune activite disponible</h3>
        <p>Il faut des destinations existantes et une connexion MySQL active pour retrouver le meme flux que dans Java.</p>
    </section>
<?php else: ?>
    <section class="activity-board">
        <?php foreach ($activites as $activite): ?>
            <article class="activity-card">
                <div class="activity-card__head">
                    <div>
                        <p class="activity-card__kicker">Activite #<?= $h($activite['id'] ?? '') ?></p>
                        <h3><?= $h($activite['nom'] ?? '') ?></h3>
                        <span class="tag-pill"><?= $h($activite['destination_nom'] ?? ('Destination #'.($activite['destination_id'] ?? ''))) ?></span>
                    </div>
                    <span class="activity-price"><?= $h(number_format((float) ($activite['prix'] ?? 0), 2, '.', ' ')) ?> TND</span>
                </div>

                <div class="activity-metrics">
                    <div>
                        <span>Categorie</span>
                        <strong><?= $h($activite['categorie'] ?? '') ?></strong>
                    </div>
                    <div>
                        <span>Duree</span>
                        <strong><?= $h((string) ($activite['duree_heures'] ?? 0)) ?> h</strong>
                    </div>
                    <div>
                        <span>Origine</span>
                        <strong>JDBC -> Symfony</strong>
                    </div>
                </div>

                <p class="activity-description"><?= $h($activite['description'] ?? '') ?></p>

                <div class="card-actions">
                    <a class="button button-outline" href="/activites/<?= $h($activite['id']) ?>/edit">Modifier</a>
                    <form method="post" action="/activites/<?= $h($activite['id']) ?>/delete">
                        <button class="button button-danger" type="submit">Supprimer</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
