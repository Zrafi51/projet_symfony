<?php
$dashboardType = $dashboardType ?? 'user';
$currentUser = $currentUser ?? [];
$supportSnapshot = $supportSnapshot ?? ['counts' => [], 'reclamations' => [], 'responses_by_reclamation' => []];
$supportCounts = $supportSnapshot['counts'] ?? [];
$reclamations = $supportSnapshot['reclamations'] ?? [];
$responsesByReclamation = $supportSnapshot['responses_by_reclamation'] ?? [];
$isAdminDashboard = $dashboardType === 'admin';

$formatDateTime = static function (?string $value): string {
    if ($value === null || trim($value) === '') {
        return 'Date inconnue';
    }

    try {
        return (new DateTimeImmutable($value))->format('d/m/Y H:i');
    } catch (Throwable) {
        return (string) $value;
    }
};

$resolveStatusClass = static function (string $status): string {
    return match (strtoupper(trim($status))) {
        'RESOLUE' => 'client-status-pill-green',
        'REJETEE' => 'client-status-pill-red',
        'EN_COURS' => 'client-status-pill-blue',
        default => 'client-status-pill-orange',
    };
};
?>

<section class="crud-hero crud-hero--editor client-dashboard-hero">
    <div>
        <p class="hero-kicker"><?= $isAdminDashboard ? 'Admin dashboard Java' : 'Dashboard client Java' ?></p>
        <h2>Bienvenue <?= $h($currentUser['display_name'] ?? 'Voyageur') ?></h2>
        <p>
            Vous etes connecte avec le role `<?= $h($currentUser['role'] ?? 'USER') ?>`.
            Cette page reprend maintenant la logique support du projet Java: vos reclamations et les reponses envoyees par l administration.
        </p>
    </div>
    <div class="crud-hero__aside">
        <a class="button button-gold" href="/contact">Nouvelle reclamation</a>
        <a class="button button-outline-light" href="/logout">Se deconnecter</a>
    </div>
</section>

<section class="module-strip client-dashboard-strip">
    <article class="module-card">
        <p class="label">Support</p>
        <h2><?= $h((string) ($supportCounts['total'] ?? 0)) ?> reclamations</h2>
        <p>Toutes vos demandes ouvertes depuis la page `Contact` arrivent ici.</p>
    </article>
    <article class="module-card">
        <p class="label">A traiter</p>
        <h2><?= $h((string) (($supportCounts['pending'] ?? 0) + ($supportCounts['in_progress'] ?? 0))) ?> en suivi</h2>
        <p>Reclamations en attente ou deja prises en charge par l administration.</p>
    </article>
    <article class="module-card module-card--dark">
        <p class="label">Reponses admin</p>
        <h2><?= $h((string) ($supportCounts['answered'] ?? 0)) ?> fils actifs</h2>
        <p>Chaque reponse ajoutee dans le dashboard admin apparait directement dans cet espace.</p>
    </article>
</section>

<section class="editor-panel client-dashboard-panel">
    <div class="section-head section-head--light">
        <div>
            <p class="label">Mes reclamations</p>
            <h2>Suivi support</h2>
            <p>Vue client reliee a `reclamation` et `reponse` de la base Java.</p>
        </div>
        <a class="button button-outline-light" href="/contact">Ouvrir la page contact</a>
    </div>

    <?php if ($reclamations === []): ?>
        <div class="client-empty-state">
            <h3>Aucune reclamation envoyee pour le moment</h3>
            <p>Vous pouvez utiliser la page Contact pour ouvrir une demande au support et suivre ensuite les reponses de l administration ici.</p>
            <a class="button button-gold" href="/contact">Creer une reclamation</a>
        </div>
    <?php else: ?>
        <div class="client-complaint-stack">
            <?php foreach ($reclamations as $reclamation): ?>
                <?php $responses = $responsesByReclamation[$reclamation['id']] ?? []; ?>
                <article class="client-complaint-card">
                    <div class="client-complaint-card__header">
                        <div>
                            <h3><?= $h((string) ($reclamation['sujet'] ?? 'Reclamation')) ?></h3>
                            <p><?= $h($formatDateTime((string) ($reclamation['created_at'] ?? ''))) ?> | <?= $h((string) count($responses)) ?> reponse(s)</p>
                        </div>
                        <span class="client-status-pill <?= $resolveStatusClass((string) ($reclamation['statut'] ?? 'EN_ATTENTE')) ?>">
                            <?= $h((string) ($reclamation['status_label'] ?? ($reclamation['statut'] ?? 'EN ATTENTE'))) ?>
                        </span>
                    </div>

                    <p class="client-complaint-copy"><?= $h((string) ($reclamation['description'] ?? '')) ?></p>

                    <?php if ($responses !== []): ?>
                        <div class="client-response-thread">
                            <?php foreach ($responses as $response): ?>
                                <div class="client-response-bubble">
                                    <div class="client-response-bubble__meta">
                                        <strong><?= $h((string) ($response['admin_name'] ?? 'Administration')) ?></strong>
                                        <span><?= $h($formatDateTime((string) ($response['created_at'] ?? ''))) ?></span>
                                    </div>
                                    <p><?= $h((string) ($response['contenu'] ?? '')) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="card-actions">
                        <a class="button button-outline-light" href="/contact">Nouvelle reclamation</a>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
