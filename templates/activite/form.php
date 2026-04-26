<section class="crud-hero crud-hero--editor">
    <div>
        <p class="hero-kicker">Edition</p>
        <h2><?= $h($formTitle ?? 'Activite') ?></h2>
        <p>Le select Destination relit la meme table que le projet Java, mais dans un template Symfony harmonise.</p>
    </div>
    <div class="crud-hero__aside">
        <a class="button button-outline-light" href="/activites">Retour a la liste</a>
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
                <input type="text" name="nom" value="<?= $h($activite['nom'] ?? '') ?>" required>
            </label>

            <label>
                <span>Destination</span>
                <select name="destination_id" required>
                    <option value="">Choisir</option>
                    <?php foreach ($destinations as $destination): ?>
                        <option
                            value="<?= $h($destination['id']) ?>"
                            <?= (string) ($activite['destination_id'] ?? '') === (string) ($destination['id'] ?? '') ? 'selected' : '' ?>
                        >
                            <?= $h(($destination['nom'] ?? '').' - '.($destination['pays'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <div class="form-grid">
            <label>
                <span>Categorie</span>
                <input type="text" name="categorie" value="<?= $h($activite['categorie'] ?? '') ?>" required>
            </label>

            <label>
                <span>Prix</span>
                <input type="number" step="0.01" min="0" name="prix" value="<?= $h($activite['prix'] ?? 0) ?>" required>
            </label>
        </div>

        <label>
            <span>Duree (heures)</span>
            <input type="number" min="1" name="duree_heures" value="<?= $h($activite['duree_heures'] ?? 1) ?>" required>
        </label>

        <label>
            <span>Description</span>
            <textarea name="description" rows="5"><?= $h($activite['description'] ?? '') ?></textarea>
        </label>

        <button class="button" type="submit"><?= $h($submitLabel ?? 'Enregistrer') ?></button>
    </form>
</section>
