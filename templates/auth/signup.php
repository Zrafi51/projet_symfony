<?php
$fieldErrors = $fieldErrors ?? [];
$prenomClass = in_array('prenom', $fieldErrors, true) ? 'auth-input-invalid' : '';
$nomClass = in_array('nom', $fieldErrors, true) ? 'auth-input-invalid' : '';
$emailClass = in_array('email', $fieldErrors, true) ? 'auth-input-invalid' : '';
$passwordClass = in_array('password', $fieldErrors, true) ? 'auth-input-invalid' : '';
$confirmPasswordClass = in_array('confirm_password', $fieldErrors, true) ? 'auth-input-invalid' : '';
?>
<section class="auth-card">
    <div class="auth-visual">
        <img src="/assets/java/trans_bg.png" alt="EasyTravel">
    </div>

    <div class="auth-panel">
        <div class="auth-panel__header">
            <h1>Inscription</h1>
            <p>Creez votre compte gratuitement</p>
        </div>

        <form class="auth-form" method="post" action="/sign-up">
            <div class="auth-grid">
                <label>
                    <span>Prenom</span>
                    <input class="<?= $prenomClass ?>" type="text" name="prenom" value="<?= $h($form['prenom'] ?? '') ?>" placeholder="Votre prenom" required>
                </label>

                <label>
                    <span>Nom</span>
                    <input class="<?= $nomClass ?>" type="text" name="nom" value="<?= $h($form['nom'] ?? '') ?>" placeholder="Votre nom" required>
                </label>
            </div>

            <label>
                <span>Email</span>
                <input class="<?= $emailClass ?>" type="email" name="email" value="<?= $h($form['email'] ?? '') ?>" placeholder="exemple@email.com" required>
            </label>

            <label>
                <span>Mot de passe</span>
                <input class="<?= $passwordClass ?>" type="password" name="password" value="<?= $h($form['password'] ?? '') ?>" placeholder="........" required>
            </label>

            <label>
                <span>Confirmer le mot de passe</span>
                <input class="<?= $confirmPasswordClass ?>" type="password" name="confirm_password" value="<?= $h($form['confirm_password'] ?? '') ?>" placeholder="........" required>
            </label>

            <button class="button auth-submit" type="submit">S'inscrire</button>

            <?php if (!empty($errorMessage)): ?>
                <p class="auth-feedback auth-feedback-error"><?= $h($errorMessage) ?></p>
            <?php endif; ?>
        </form>

        <p class="auth-switch">
            Vous avez deja un compte ?
            <a href="/login">Se connecter</a>
        </p>
    </div>
</section>
