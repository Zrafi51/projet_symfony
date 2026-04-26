<?php
$currentUser = $currentUser ?? null;
$formData = $formData ?? [];
$supportSnapshot = $supportSnapshot ?? ['counts' => []];
$supportCounts = $supportSnapshot['counts'] ?? [];
$isAuthenticated = is_array($currentUser) && trim((string) ($currentUser['email'] ?? '')) !== '';
$dashboardHref = $isAuthenticated
    ? (in_array(strtoupper((string) ($currentUser['role'] ?? 'USER')), ['ADMIN', 'SUPER_ADMIN'], true) ? '/admin/dashboard' : '/dashboard')
    : '/login';
?>

<section class="contact-hero">
    <div class="contact-hero__copy">
        <p class="hero-kicker">Contact</p>
        <h2>Parlons de votre voyage</h2>
        <p class="lead">Notre equipe vous repond en moins de 24h. La page suit la meme logique que `Contact.fxml` du projet Java.</p>
    </div>

    <div class="contact-hero__actions">
        <a class="button button-gold" href="<?= $h($dashboardHref) ?>"><?= $isAuthenticated ? 'Voir mon dashboard' : 'Se connecter' ?></a>
        <a class="button button-outline-light" href="/destinations">Explorer les destinations</a>
    </div>
</section>

<section class="contact-grid">
    <article class="contact-form-card">
        <div class="contact-card-head">
            <div>
                <p class="label"><?= $isAuthenticated ? 'Mode reclamation' : 'Mode visiteur' ?></p>
                <h3><?= $isAuthenticated ? 'Envoyer une reclamation au support' : 'Envoyer un message a notre equipe' ?></h3>
                <p>
                    <?= $isAuthenticated
                        ? 'Votre demande sera ecrite dans `reclamation` et la reponse admin apparaitra ensuite dans votre dashboard.'
                        : 'Votre message sera sauvegarde dans `contacts`, comme dans le module Java pour les visiteurs.' ?>
                </p>
            </div>
            <span class="contact-mode-pill"><?= $isAuthenticated ? 'Client connecte' : 'Visiteur' ?></span>
        </div>

        <form class="contact-form" method="post" action="/contact">
            <div class="contact-form-grid">
                <label class="contact-field">
                    <span>Nom complet</span>
                    <input
                        class="contact-input"
                        type="text"
                        name="name"
                        value="<?= $h((string) ($formData['name'] ?? '')) ?>"
                        <?= $isAuthenticated ? 'readonly' : '' ?>
                        placeholder="Votre nom complet"
                    >
                </label>

                <label class="contact-field">
                    <span>Email</span>
                    <input
                        class="contact-input"
                        type="email"
                        name="email"
                        value="<?= $h((string) ($formData['email'] ?? '')) ?>"
                        <?= $isAuthenticated ? 'readonly' : '' ?>
                        placeholder="vous@example.com"
                    >
                </label>
            </div>

            <div class="contact-form-grid">
                <label class="contact-field">
                    <span>Sujet</span>
                    <input
                        class="contact-input"
                        type="text"
                        name="subject"
                        value="<?= $h((string) ($formData['subject'] ?? '')) ?>"
                        placeholder="<?= $isAuthenticated ? 'Sujet de votre reclamation' : 'Sujet de votre demande' ?>"
                    >
                </label>

                <label class="contact-field">
                    <span>Telephone</span>
                    <input
                        class="contact-input"
                        type="text"
                        name="phone"
                        value="<?= $h((string) ($formData['phone'] ?? '')) ?>"
                        placeholder="+216 00 000 000"
                    >
                </label>
            </div>

            <label class="contact-field">
                <span>Message</span>
                <textarea
                    class="contact-input contact-textarea"
                    name="message"
                    rows="8"
                    placeholder="<?= $isAuthenticated ? 'Expliquez clairement votre reclamation ou le probleme rencontre...' : 'Decrivez votre projet de voyage ou votre question...' ?>"
                ><?= $h((string) ($formData['message'] ?? '')) ?></textarea>
            </label>

            <div class="contact-form-actions">
                <button class="button button-gold" type="submit"><?= $isAuthenticated ? 'Envoyer la reclamation ->' : 'Envoyer le message ->' ?></button>
                <p class="contact-form-note">Support `EasyTravel`, meme base `voyage`, meme logique Java.</p>
            </div>
        </form>
    </article>

    <aside class="contact-side-stack">
        <article class="contact-info-card">
            <span class="contact-info-kicker">Call us</span>
            <h3>+33 1 23 45 67 89</h3>
            <p>Assistance voyage, support client et accompagnement premium.</p>
        </article>

        <article class="contact-info-card">
            <span class="contact-info-kicker">Email</span>
            <h3>contact@easytravel.com</h3>
            <p>Reponse rapide pour vos questions, reservations et demandes de suivi.</p>
        </article>

        <article class="contact-info-card">
            <span class="contact-info-kicker">Address</span>
            <h3>Paris, France</h3>
            <p>Notre equipe concoit vos voyages sur mesure depuis le studio EasyTravel.</p>
        </article>

        <?php if ($isAuthenticated): ?>
            <article class="contact-info-card contact-info-card-accent">
                <span class="contact-info-kicker">Suivi support</span>
                <div class="contact-stats">
                    <div>
                        <span>Total</span>
                        <strong><?= $h((string) ($supportCounts['total'] ?? 0)) ?></strong>
                    </div>
                    <div>
                        <span>En attente</span>
                        <strong><?= $h((string) ($supportCounts['pending'] ?? 0)) ?></strong>
                    </div>
                    <div>
                        <span>Repondues</span>
                        <strong><?= $h((string) ($supportCounts['answered'] ?? 0)) ?></strong>
                    </div>
                </div>
                <a class="contact-inline-link" href="/dashboard">Consulter mes reclamations</a>
            </article>
        <?php endif; ?>
    </aside>
</section>

<section class="contact-cta">
    <div>
        <p class="label">Pret pour l aventure</p>
        <h3>Contactez-nous pour creer votre voyage sur mesure</h3>
        <p>Le footer reprend le meme esprit que l ecran Java: grand appel a l action, support, newsletter et branding EasyTravel.</p>
    </div>
    <div class="contact-cta__actions">
        <a class="button button-gold" href="/">Retour a l accueil</a>
        <a class="button button-outline-light" href="/activites">Voir les activites</a>
    </div>
</section>

<footer class="contact-footer">
    <div class="contact-footer__brand">
        <strong>EasyTravel</strong>
        <p>Createur d experiences de voyage uniques avec une base partagee entre Java et Symfony.</p>
    </div>
    <div class="contact-footer__links">
        <a href="/destinations">Destinations</a>
        <a href="/contact">Contact</a>
        <a href="/login">Connexion</a>
        <a href="/dashboard">Dashboard</a>
    </div>
</footer>
