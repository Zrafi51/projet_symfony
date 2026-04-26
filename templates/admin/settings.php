<?php
$currentUser = $currentUser ?? [];
$profileForm = $profileForm ?? [];
$notificationForm = $notificationForm ?? [];
$displayName = $displayName ?? 'Admin User';
$roleChipLabel = $roleChipLabel ?? strtoupper((string) ($profileForm['role'] ?? 'ADMIN'));
$roleSummaryLabel = $roleSummaryLabel ?? $roleChipLabel;
$summaryModeLabel = $summaryModeLabel ?? 'Base synchronisee';
$rememberedSession = (bool) ($rememberedSession ?? false);
$statusMessage = $statusMessage ?? null;
$errorMessage = $errorMessage ?? null;
$databaseError = $databaseError ?? null;

$photoSourceUrl = trim((string) ($profileForm['photo_url'] ?? ($currentUser['photo_url'] ?? '')));
$photoUrl = trim((string) ($photoDisplayUrl ?? $photoSourceUrl));
$initial = strtoupper(substr((string) $displayName, 0, 1));
$notificationOptions = [
    [
        'name' => 'notify_security',
        'title' => 'Alertes de securite',
        'description' => 'Connexions, mot de passe et changements sensibles du compte.',
        'kicker' => 'Priorite haute',
    ],
    [
        'name' => 'notify_booking',
        'title' => 'Reservations et paiements',
        'description' => 'Suivi des reservations critiques, paiements et actions urgentes.',
        'kicker' => 'Operations',
    ],
    [
        'name' => 'notify_forum',
        'title' => 'Forum et support',
        'description' => 'Reclamations clients, messages forum et tickets support.',
        'kicker' => 'Support',
    ],
    [
        'name' => 'notify_offers',
        'title' => 'Offres et marketing',
        'description' => 'Contenus premium, campagnes et suggestions commerciales.',
        'kicker' => 'Optionnel',
    ],
];
$selectedNotificationsCount = 0;
foreach ($notificationOptions as $notificationOption) {
    if (!empty($notificationForm[$notificationOption['name']])) {
        ++$selectedNotificationsCount;
    }
}
$formatDate = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return 'Non renseignee';
    }

    try {
        return (new DateTimeImmutable($value))->format('d/m/Y');
    } catch (Throwable) {
        return (string) $value;
    }
};
?>
<div class="admin-settings-shell">
    <header class="admin-settings-topbar">
        <div class="admin-settings-topbar-row">
            <a class="admin-settings-back-button" href="/admin/dashboard">Retour dashboard</a>
            <div>
                <h1 class="admin-page-title">Parametres Admin</h1>
                <p class="admin-settings-topbar-copy">Profil, avatar, mot de passe et notifications de la console.</p>
            </div>
            <div class="admin-settings-topbar-actions">
                <button class="admin-settings-primary-button" type="submit" form="admin-profile-form">Sauvegarder le profil</button>
            </div>
        </div>
    </header>

    <main class="admin-settings-content">
        <section class="admin-settings-hero">
            <div class="admin-settings-hero-copy">
                <span class="admin-settings-badge">ADMIN SETTINGS</span>
                <h2>Pilotez votre profil, <?= $h($displayName) ?></h2>
                <p>Mettez a jour votre identite admin, votre avatar et vos preferences EasyTravel.</p>
                <div class="admin-settings-chip-row">
                    <span class="admin-settings-chip"><?= $h((string) ($profileForm['email'] ?? 'admin@easytravel.local')) ?></span>
                    <span class="admin-settings-chip admin-settings-chip-accent"><?= $h($roleChipLabel) ?></span>
                </div>
            </div>

            <aside class="admin-settings-avatar-panel">
                <div class="admin-settings-avatar-shell">
                    <img
                        class="admin-settings-avatar-image"
                        src="<?= $photoUrl !== '' ? $h($photoUrl) : '' ?>"
                        alt="<?= $h($displayName) ?>"
                        style="<?= $photoUrl !== '' ? '' : 'display:none;' ?>"
                        onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';"
                    >
                    <span class="admin-settings-avatar-initial" style="<?= $photoUrl !== '' ? 'display:none;' : '' ?>"><?= $h($initial) ?></span>
                </div>
                <div class="admin-settings-avatar-meta">
                    <strong class="admin-settings-panel-title">Avatar admin</strong>
                    <small class="admin-settings-panel-copy" data-admin-avatar-file-label><?= $photoSourceUrl !== '' ? $h(basename($photoSourceUrl)) : 'PNG, JPG ou WEBP - 5 Mo max' ?></small>
                </div>
                <form class="admin-settings-upload-form" method="post" action="/admin/settings/avatar" enctype="multipart/form-data" data-admin-avatar-form>
                    <input class="admin-settings-file-input" id="admin-avatar-input" type="file" name="avatar" accept="image/*" required>
                    <div class="admin-settings-upload-row">
                        <button class="admin-settings-secondary-button" type="button" data-admin-avatar-trigger>Choisir une image</button>
                        <span class="admin-settings-upload-name" data-admin-avatar-name>Aucun fichier selectionne</span>
                    </div>
                    <button class="admin-settings-primary-button admin-full-button" type="submit" data-admin-avatar-submit disabled>Uploader une image</button>
                </form>
                <form method="post" action="/admin/settings/avatar/remove">
                    <button class="admin-settings-danger-button admin-full-button" type="submit">Supprimer avatar</button>
                </form>
            </aside>
        </section>

        <?php if ($databaseError !== null): ?>
            <div class="admin-alert admin-alert-error"><?= $h($databaseError) ?></div>
        <?php endif; ?>
        <?php if ($errorMessage !== null && $errorMessage !== ''): ?>
            <p class="admin-settings-status admin-settings-status-error"><?= $h($errorMessage) ?></p>
        <?php endif; ?>
        <?php if ($statusMessage !== null && $statusMessage !== ''): ?>
            <p class="admin-settings-status admin-settings-status-success"><?= $h($statusMessage) ?></p>
        <?php endif; ?>

        <div class="admin-settings-grid">
            <aside class="admin-settings-sidebar">
                <section class="admin-settings-card admin-settings-side-card">
                    <div>
                        <h3 class="admin-settings-card-title">Resume du compte</h3>
                        <p class="admin-settings-card-copy">Un apercu rapide de votre espace admin et de votre synchronisation base de donnees.</p>
                    </div>

                    <div class="admin-settings-summary-shell">
                        <div class="admin-settings-summary-avatar-shell">
                            <img
                                class="admin-settings-summary-avatar-image"
                                src="<?= $photoUrl !== '' ? $h($photoUrl) : '' ?>"
                                alt="<?= $h($displayName) ?>"
                                style="<?= $photoUrl !== '' ? '' : 'display:none;' ?>"
                                onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';"
                            >
                            <span class="admin-settings-summary-avatar-initial" style="<?= $photoUrl !== '' ? 'display:none;' : '' ?>"><?= $h($initial) ?></span>
                        </div>
                        <strong class="admin-settings-summary-name"><?= $h($displayName) ?></strong>
                        <small class="admin-settings-summary-email"><?= $h((string) ($profileForm['email'] ?? 'admin@easytravel.local')) ?></small>
                    </div>

                    <div class="admin-settings-meta-list">
                        <div><span class="admin-settings-meta-label">Role</span><strong class="admin-settings-meta-value"><?= $h($roleSummaryLabel) ?></strong></div>
                        <div><span class="admin-settings-meta-label">Mode</span><strong class="admin-settings-meta-value"><?= $h($summaryModeLabel) ?></strong></div>
                        <div><span class="admin-settings-meta-label">Memoire appareil</span><strong class="admin-settings-meta-value"><?= $rememberedSession ? 'Active apres logout' : 'Inactive' ?></strong></div>
                        <div><span class="admin-settings-meta-label">Date de naissance</span><strong class="admin-settings-meta-value"><?= $h($formatDate((string) ($profileForm['date_naissance'] ?? ''))) ?></strong></div>
                    </div>
                </section>

                <section class="admin-settings-card admin-settings-side-card">
                    <h3 class="admin-settings-card-title">Conseil admin</h3>
                    <p class="admin-settings-card-copy">Cette page est faite pour gerer votre profil, vos notifications et votre avatar comme un vrai espace settings.</p>
                </section>
            </aside>

            <div class="admin-settings-main">
                <section class="admin-settings-card admin-settings-form-card">
                    <div class="admin-settings-card-head">
                        <div>
                            <h3 class="admin-settings-card-title">Informations personnelles</h3>
                            <p class="admin-settings-card-copy">Mettez a jour votre identite admin, votre poste et vos coordonnees.</p>
                        </div>
                    </div>

                    <form id="admin-profile-form" class="admin-settings-form" method="post" action="/admin/settings/profile">
                        <div class="admin-settings-form-grid">
                            <label>
                                <span class="admin-settings-field-label">Prenom</span>
                                <input class="admin-settings-input" type="text" name="prenom" value="<?= $h((string) ($profileForm['prenom'] ?? '')) ?>" placeholder="Votre prenom">
                            </label>
                            <label>
                                <span class="admin-settings-field-label">Nom</span>
                                <input class="admin-settings-input" type="text" name="nom" value="<?= $h((string) ($profileForm['nom'] ?? '')) ?>" placeholder="Votre nom">
                            </label>
                            <label>
                                <span class="admin-settings-field-label">Email</span>
                                <input class="admin-settings-input" type="email" name="email" value="<?= $h((string) ($profileForm['email'] ?? '')) ?>" placeholder="admin@easytravel.local" required>
                            </label>
                            <label>
                                <span class="admin-settings-field-label">Telephone</span>
                                <input class="admin-settings-input" type="text" name="telephone" value="<?= $h((string) ($profileForm['telephone'] ?? '')) ?>" placeholder="+216 00 000 000">
                            </label>
                            <label>
                                <span class="admin-settings-field-label">Poste</span>
                                <input class="admin-settings-input" type="text" name="job_title" value="<?= $h((string) ($profileForm['job_title'] ?? '')) ?>" placeholder="Super Admin">
                            </label>
                            <label>
                                <span class="admin-settings-field-label">Societe</span>
                                <input class="admin-settings-input" type="text" name="company" value="<?= $h((string) ($profileForm['company'] ?? '')) ?>" placeholder="EasyTravel">
                            </label>
                            <label>
                                <span class="admin-settings-field-label">Date de naissance</span>
                                <input class="admin-settings-input" type="date" name="date_naissance" value="<?= $h((string) ($profileForm['date_naissance'] ?? '')) ?>">
                            </label>
                            <label>
                                <span class="admin-settings-field-label">Role</span>
                                <input class="admin-settings-input admin-settings-readonly-input" type="text" value="<?= $h((string) ($profileForm['role'] ?? $roleChipLabel)) ?>" readonly>
                            </label>
                            <label class="admin-settings-span-2">
                                <span class="admin-settings-field-label">Adresse</span>
                                <textarea class="admin-settings-textarea" name="adresse" rows="3" placeholder="Adresse admin"><?= $h((string) ($profileForm['adresse'] ?? '')) ?></textarea>
                            </label>
                            <label class="admin-settings-span-2">
                                <span class="admin-settings-field-label">Bio / Notes admin</span>
                                <textarea class="admin-settings-textarea" name="bio" rows="4" placeholder="Notes admin"><?= $h((string) ($profileForm['bio'] ?? '')) ?></textarea>
                            </label>
                        </div>

                        <div class="admin-settings-actions">
                            <a class="admin-settings-secondary-button" href="/admin/settings">Annuler les changements</a>
                            <button class="admin-settings-primary-button" type="submit">Sauvegarder le profil</button>
                        </div>
                    </form>
                </section>

                <div class="admin-settings-feature-grid">
                    <section class="admin-settings-card admin-settings-feature-card">
                        <div class="admin-settings-card-head">
                            <div>
                                <h3 class="admin-settings-card-title">Securite du compte</h3>
                                <p class="admin-settings-card-copy">Changez votre mot de passe admin et gardez une session plus securisee.</p>
                            </div>
                        </div>

                        <form class="admin-settings-form" method="post" action="/admin/settings/password">
                            <label>
                                <span class="admin-settings-field-label">Mot de passe actuel</span>
                                <input class="admin-settings-input" type="password" name="current_password" placeholder="Mot de passe actuel" required>
                            </label>
                            <label>
                                <span class="admin-settings-field-label">Nouveau mot de passe</span>
                                <input class="admin-settings-input" type="password" name="new_password" placeholder="Nouveau mot de passe" required>
                            </label>
                            <label>
                                <span class="admin-settings-field-label">Confirmer le mot de passe</span>
                                <input class="admin-settings-input" type="password" name="confirm_password" placeholder="Confirmer le nouveau mot de passe" required>
                            </label>
                            <div class="admin-settings-actions">
                                <button class="admin-settings-primary-button" type="submit">Changer le mot de passe</button>
                            </div>
                        </form>
                    </section>

                    <section class="admin-settings-card admin-settings-feature-card">
                        <div class="admin-settings-card-head">
                            <div>
                                <h3 class="admin-settings-card-title">Notifications admin</h3>
                                <p class="admin-settings-card-copy">Choisissez les alertes prioritaires que vous voulez recevoir sur la console.</p>
                            </div>
                            <span class="admin-settings-selection-badge" data-notification-count><?= $h((string) $selectedNotificationsCount) ?> active(s)</span>
                        </div>

                        <form class="admin-settings-form" method="post" action="/admin/settings/notifications" data-notification-form>
                            <div class="admin-settings-selection-actions">
                                <button class="admin-settings-secondary-button" type="button" data-notification-action="all">Tout activer</button>
                                <button class="admin-settings-secondary-button" type="button" data-notification-action="essential">Essentiel</button>
                                <button class="admin-settings-secondary-button" type="button" data-notification-action="none">Tout couper</button>
                            </div>

                            <div class="admin-settings-check-list admin-settings-notification-grid">
                                <?php foreach ($notificationOptions as $notificationOption): ?>
                                    <?php $isChecked = !empty($notificationForm[$notificationOption['name']]); ?>
                                    <label class="admin-settings-notification-card <?= $isChecked ? 'admin-settings-notification-card-active' : '' ?>">
                                        <input
                                            class="admin-settings-notification-input"
                                            type="checkbox"
                                            name="<?= $h($notificationOption['name']) ?>"
                                            value="1"
                                            <?= $isChecked ? 'checked' : '' ?>
                                        >
                                        <span class="admin-settings-notification-visual">
                                            <span class="admin-settings-notification-check"></span>
                                        </span>
                                        <span class="admin-settings-notification-copy">
                                            <small><?= $h($notificationOption['kicker']) ?></small>
                                            <strong><?= $h($notificationOption['title']) ?></strong>
                                            <span><?= $h($notificationOption['description']) ?></span>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>

                            <div class="admin-settings-actions">
                                <button class="admin-settings-primary-button" type="submit">Enregistrer les notifications</button>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </div>
    </main>
</div>
<script>
(() => {
    const avatarForm = document.querySelector('[data-admin-avatar-form]');
    const avatarInput = document.getElementById('admin-avatar-input');
    const avatarTrigger = document.querySelector('[data-admin-avatar-trigger]');
    const avatarSubmit = document.querySelector('[data-admin-avatar-submit]');
    const avatarName = document.querySelector('[data-admin-avatar-name]');
    const avatarMetaLabel = document.querySelector('[data-admin-avatar-file-label]');
    const heroAvatarImage = document.querySelector('.admin-settings-avatar-image');
    const heroAvatarInitial = document.querySelector('.admin-settings-avatar-shell .admin-settings-avatar-initial');
    const summaryAvatarImage = document.querySelector('.admin-settings-summary-avatar-image');
    const summaryAvatarInitial = document.querySelector('.admin-settings-summary-avatar-shell .admin-settings-summary-avatar-initial');

    if (avatarForm && avatarInput && avatarTrigger) {
        avatarTrigger.addEventListener('click', () => avatarInput.click());
        avatarInput.addEventListener('change', () => {
            if (!avatarInput.files || avatarInput.files.length === 0) {
                if (avatarName) {
                    avatarName.textContent = 'Aucun fichier selectionne';
                }
                if (avatarSubmit) {
                    avatarSubmit.disabled = true;
                }
                return;
            }

            const file = avatarInput.files[0];
            if (avatarName) {
                avatarName.textContent = file.name;
            }
            if (avatarMetaLabel) {
                avatarMetaLabel.textContent = file.name;
            }
            if (avatarSubmit) {
                avatarSubmit.disabled = false;
            }

            const reader = new FileReader();
            reader.addEventListener('load', () => {
                if (heroAvatarImage) {
                    heroAvatarImage.src = String(reader.result);
                    heroAvatarImage.style.display = 'block';
                }
                if (summaryAvatarImage) {
                    summaryAvatarImage.src = String(reader.result);
                    summaryAvatarImage.style.display = 'block';
                }
                if (heroAvatarInitial) {
                    heroAvatarInitial.style.display = 'none';
                }
                if (summaryAvatarInitial) {
                    summaryAvatarInitial.style.display = 'none';
                }
            });
            reader.readAsDataURL(file);
        });
    }

    const notificationForm = document.querySelector('[data-notification-form]');
    const notificationCount = document.querySelector('[data-notification-count]');
    if (notificationForm) {
        const inputs = Array.from(notificationForm.querySelectorAll('.admin-settings-notification-input'));
        const cards = Array.from(notificationForm.querySelectorAll('.admin-settings-notification-card'));
        const actionButtons = Array.from(notificationForm.querySelectorAll('[data-notification-action]'));

        const syncCards = () => {
            let activeCount = 0;
            cards.forEach((card) => {
                const input = card.querySelector('.admin-settings-notification-input');
                const isChecked = Boolean(input && input.checked);
                card.classList.toggle('admin-settings-notification-card-active', isChecked);
                if (isChecked) {
                    activeCount += 1;
                }
            });

            if (notificationCount) {
                notificationCount.textContent = activeCount + ' active(s)';
            }
        };

        inputs.forEach((input) => {
            input.addEventListener('change', syncCards);
        });

        actionButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const action = button.getAttribute('data-notification-action');
                inputs.forEach((input) => {
                    if (action === 'all') {
                        input.checked = true;
                    } else if (action === 'none') {
                        input.checked = false;
                    } else if (action === 'essential') {
                        input.checked = input.name !== 'notify_offers';
                    }
                });

                syncCards();
            });
        });

        syncCards();
    }
})();
</script>
