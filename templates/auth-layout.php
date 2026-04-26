<?php

$title = $title ?? 'Authentification';
$h = static fn (mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $h($title) ?></title>
    <link rel="icon" type="image/png" href="/assets/java/trans_bg.png">
    <link rel="stylesheet" href="/app.css">
</head>
<body class="auth-body">
    <div class="auth-screen">
        <?php require $contentTemplate; ?>
    </div>
</body>
</html>
