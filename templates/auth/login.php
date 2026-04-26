<?php
$fieldErrors = $fieldErrors ?? [];
$rememberMeAvailable = $rememberMeAvailable ?? true;
$emailClass = in_array('email', $fieldErrors, true) ? 'auth-input-invalid' : '';
$passwordClass = in_array('password', $fieldErrors, true) ? 'auth-input-invalid' : '';
?>
<section class="auth-card">
    <div class="auth-visual">
        <img src="/assets/java/trans_bg.png" alt="EasyTravel">
    </div>

    <div class="auth-panel">
        <div class="auth-panel__header">
            <h1>Connexion</h1>
            <p>Connectez-vous a votre compte</p>
        </div>

        <form class="auth-form" method="post" action="/login">
            <label>
                <span>Email</span>
                <input class="<?= $emailClass ?>" type="email" name="email" value="<?= $h($form['email'] ?? '') ?>" placeholder="exemple@email.com" required>
            </label>

            <label>
                <span>Mot de passe</span>
                <input class="<?= $passwordClass ?>" type="password" name="password" value="<?= $h($form['password'] ?? '') ?>" placeholder="........" required>
            </label>

            <div class="auth-row">
                <label class="auth-checkbox <?= !$rememberMeAvailable ? 'auth-checkbox-disabled' : '' ?>">
                    <input
                        type="checkbox"
                        name="remember_me"
                        value="1"
                        <?= !empty($form['remember_me']) ? 'checked' : '' ?>
                        <?= !$rememberMeAvailable ? 'disabled' : '' ?>
                    >
                    <span><?= $rememberMeAvailable ? 'Se souvenir de moi apres deconnexion' : 'Se souvenir de moi (base requise)' ?></span>
                </label>

                <a class="auth-link" href="/forgot-password">Mot de passe oublie ?</a>
            </div>

            <button class="button auth-submit" type="submit">Se connecter</button>

            <?php if (!empty($errorMessage)): ?>
                <p class="auth-feedback auth-feedback-error"><?= $h($errorMessage) ?></p>
            <?php elseif (!empty($statusMessage)): ?>
                <p class="auth-feedback auth-feedback-success"><?= $h($statusMessage) ?></p>
            <?php endif; ?>
        </form>

        <div class="auth-separator">
            <span></span>
            <small>OU</small>
            <span></span>
        </div>

        <p class="auth-switch">
            Vous n'avez pas de compte ?
            <a href="/sign-up">S'inscrire</a>
        </p>
    </div>
</section>
