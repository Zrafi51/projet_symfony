<section class="crud-hero crud-hero--editor">
    <div>
        <p class="hero-kicker">Edition</p>
        <h2><?= $h($formTitle ?? 'Destination') ?></h2>
        <p>Le formulaire Symfony reste branche directement sur la table Java `destinations`.</p>
    </div>
    <div class="crud-hero__aside">
        <a class="button button-outline-light" href="/destinations">Retour a la liste</a>
    </div>
</section>

<section class="editor-panel">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $error): ?>
                <p><?= $h($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form class="stack" method="post" action="<?= $h($action ?? '') ?>">
        <div class="form-grid">
            <label>
                <span>Nom</span>
                <input type="text" name="nom" value="<?= $h($destination['nom'] ?? '') ?>" required>
            </label>

            <label>
                <span>Pays</span>
                <input type="text" name="pays" value="<?= $h($destination['pays'] ?? '') ?>" required>
            </label>
        </div>

        <div class="form-grid">
            <label>
                <span>Continent</span>
                <input type="text" name="continent" value="<?= $h($destination['continent'] ?? '') ?>" required>
            </label>

            <label>
                <span>Prix de base</span>
                <input type="number" step="0.01" min="0" name="prix_base" value="<?= $h($destination['prix_base'] ?? 0) ?>" required>
            </label>
        </div>

        <label>
            <span>Description</span>
            <textarea name="description" rows="5"><?= $h($destination['description'] ?? '') ?></textarea>
        </label>

        <button class="button" type="submit"><?= $h($submitLabel ?? 'Enregistrer') ?></button>
    </form>
</section>
