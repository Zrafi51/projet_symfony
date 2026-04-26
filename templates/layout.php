<?php

$title = $title ?? 'Projet Symfony';
$showPageHeading = $showPageHeading ?? true;
$stylesheets = $stylesheets ?? [];
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$currentUser = is_array($currentUser ?? null) ? $currentUser : [];
$photoUrl = trim((string) ($currentUser['photo_display_url'] ?? $currentUser['photo_url'] ?? ''));
$fullName = trim(((string) ($currentUser['prenom'] ?? '')).' '.((string) ($currentUser['nom'] ?? '')));
$displayName = trim((string) ($currentUser['display_name'] ?? $fullName));
if ($displayName === '') {
    $displayName = (string) ($currentUser['email'] ?? 'Voyageur');
}
$userInitial = strtoupper(substr((string) ($currentUser['prenom'] ?? $displayName), 0, 1));
$userRole = strtoupper((string) ($currentUser['role'] ?? 'USER'));
$isAdminUser = $userRole === 'ADMIN';
$accountDashboardPath = $isAdminUser ? '/admin/dashboard' : '/dashboard';
$accountProfilePath = $isAdminUser ? '/admin/settings' : '/profile';
$accountProfileTitle = $isAdminUser ? 'Parametres admin' : 'Gerer profil';
$accountProfileSubtitle = $isAdminUser ? 'Securite et informations du compte' : 'Vos informations personnelles';
$accountDashboardTitle = $isAdminUser ? 'Dashboard admin' : 'Dashboard';
$accountDashboardSubtitle = $isAdminUser ? 'Piloter la plateforme' : 'Votre espace client';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $h($title) ?></title>
    <link rel="icon" type="image/png" href="/assets/java/trans_bg.png">
    <link rel="stylesheet" href="/app.css">
    <?php foreach ($stylesheets as $stylesheet): ?>
        <link rel="stylesheet" href="<?= $h((string) $stylesheet) ?>">
    <?php endforeach; ?>
</head>
<body>
    <div class="page-backdrop"></div>
    <div class="shell">
        <header class="site-header">
            <a class="brand" href="/">
                <span class="brand-logo-shell">
                    <img class="brand-logo brand-logo-animated" src="/assets/java/logo-animated.svg" alt="Logo anime EasyTravel du projet Java">
                </span>
                <span class="brand-copy">
                    <small>Logo anime de l'accueil Java</small>
                    <strong>Migre tel quel dans Symfony</strong>
                </span>
            </a>

            <nav class="site-nav">
                <a class="<?= $path === '/' ? 'is-active' : '' ?>" href="/">Accueil</a>
                <a class="<?= str_starts_with($path, '/destinations') ? 'is-active' : '' ?>" href="/destinations">Destinations</a>
                <a class="<?= str_starts_with($path, '/activites') ? 'is-active' : '' ?>" href="/activites">Activites</a>
                <a class="<?= str_starts_with($path, '/about') ? 'is-active' : '' ?>" href="/about">À propos</a>
                <a class="<?= str_starts_with($path, '/contact') ? 'is-active' : '' ?>" href="/contact">Contact</a>
            </nav>

            <div class="site-header-actions">
                <div class="header-pill">
                    <span class="status-dot"></span>
                    <span><?= $isAdminUser ? 'Session admin connectee' : 'Meme base MySQL que Java' ?></span>
                </div>

                <?php if ($currentUser !== []): ?>
                    <div class="account-menu-wrapper" data-account-menu-wrapper>
                        <button
                            class="connexion-button site-login-button account-button account-button-logged-in"
                            type="button"
                            data-account-menu-button
                            aria-controls="siteAccountMenu"
                            aria-expanded="false"
                        >
                            <div class="account-button-content">
                                <div class="account-button-avatar">
                                    <?php if ($photoUrl !== ''): ?>
                                        <img src="<?= $h($photoUrl) ?>" alt="<?= $h($displayName) ?>" class="account-avatar-image">
                                    <?php else: ?>
                                        <span class="account-button-avatar-label"><?= $h($userInitial) ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="account-button-name"><?= $h((string) ($currentUser['prenom'] ?? 'Mon compte')) ?></span>
                                <span class="account-button-arrow">&#9662;</span>
                            </div>
                        </button>

                        <div class="navbar-account-menu" id="siteAccountMenu" data-account-menu hidden>
                            <div class="account-menu-header">
                                <div class="account-menu-header-avatar">
                                    <?php if ($photoUrl !== ''): ?>
                                        <img src="<?= $h($photoUrl) ?>" alt="<?= $h($displayName) ?>" class="account-menu-avatar-image">
                                    <?php else: ?>
                                        <span class="account-menu-header-avatar-label"><?= $h($userInitial) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="account-menu-header-text">
                                    <div class="account-menu-header-name"><?= $h($displayName) ?></div>
                                    <div class="account-menu-header-email"><?= $h((string) ($currentUser['email'] ?? 'Compte utilisateur')) ?></div>
                                </div>
                            </div>
                            <div class="account-menu-separator"></div>
                            <a href="<?= $h($accountProfilePath) ?>" class="account-menu-item">
                                <div class="account-menu-icon-shell">
                                    <div class="account-menu-icon-shape account-menu-icon-profile"></div>
                                </div>
                                <div class="account-menu-item-labels">
                                    <div class="account-menu-title"><?= $h($accountProfileTitle) ?></div>
                                    <div class="account-menu-subtitle"><?= $h($accountProfileSubtitle) ?></div>
                                </div>
                                <span class="account-menu-item-chevron">&#8250;</span>
                            </a>
                            <a href="/forum" class="account-menu-item">
                                <div class="account-menu-icon-shell">
                                    <div class="account-menu-icon-shape account-menu-icon-forum"></div>
                                </div>
                                <div class="account-menu-item-labels">
                                    <div class="account-menu-title">Forum</div>
                                    <div class="account-menu-subtitle">Discussions et echanges voyageurs</div>
                                </div>
                                <span class="account-menu-item-chevron">&#8250;</span>
                            </a>
                            <a href="<?= $h($accountDashboardPath) ?>" class="account-menu-item">
                                <div class="account-menu-icon-shell">
                                    <div class="account-menu-icon-shape account-menu-icon-dashboard"></div>
                                </div>
                                <div class="account-menu-item-labels">
                                    <div class="account-menu-title"><?= $h($accountDashboardTitle) ?></div>
                                    <div class="account-menu-subtitle"><?= $h($accountDashboardSubtitle) ?></div>
                                </div>
                                <span class="account-menu-item-chevron">&#8250;</span>
                            </a>
                            <div class="account-menu-separator"></div>
                            <a href="/logout" class="account-menu-item account-menu-item-danger">
                                <div class="account-menu-icon-shell account-menu-icon-shell-danger">
                                    <div class="account-menu-icon-shape account-menu-icon-shape-danger account-menu-icon-logout"></div>
                                </div>
                                <div class="account-menu-item-labels">
                                    <div class="account-menu-title account-menu-title-danger">Deconnexion</div>
                                    <div class="account-menu-subtitle account-menu-subtitle-danger">Fermer la session active et garder le compte memorise</div>
                                </div>
                                <span class="account-menu-item-chevron">&#8250;</span>
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <a href="/login" class="connexion-button site-login-button">Connexion</a>
                <?php endif; ?>
            </div>
        </header>

        <?php if ($showPageHeading): ?>
            <section class="page-heading">
                <p class="eyebrow">Design Java applique aux templates Symfony</p>
                <h1><?= $h($title) ?></h1>
                <p class="lead">Navigation, hero, cartes et ecrans CRUD rebranches sur la meme logique de donnees que l'application Java.</p>
            </section>
        <?php endif; ?>

        <?php if (!empty($databaseError)): ?>
            <div class="alert alert-error"><?= $h($databaseError) ?></div>
        <?php endif; ?>

        <?php if (!empty($statusMessage)): ?>
            <div class="alert alert-success"><?= $h($statusMessage) ?></div>
        <?php endif; ?>

        <main class="page">
            <?php require $contentTemplate; ?>
        </main>
    </div>
    <script src="/app.js"></script>
</body>
</html>
