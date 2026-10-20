<?php

declare(strict_types=1);

$pageTitle = isset($pageTitle) ? (string) $pageTitle : 'MiniHack Web Lab';
$navUser = current_user();
$flash = take_flash();
$activePath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$activePath = $activePath === '/index.php' ? '/' : $activePath;
$navItems = ['/' => 'Workbench', '/challenges.php' => 'Challenges', '/search.php' => 'Search', '/notes.php' => 'Notes'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | MiniHack Web Lab</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="/assets/js/app.js" defer></script>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header">
    <div class="header-top">
        <a class="brand" href="/">MiniHack <span>Web Lab</span></a>
        <span class="version">v0.2</span>
        <div class="account-nav">
        <?php if ($navUser !== null): ?>
            <a class="account-name" href="/profile.php"<?= $activePath === '/profile.php' ? ' aria-current="page"' : '' ?>><?= e($navUser['username']) ?></a>
            <form class="inline" method="post" action="/logout.php" novalidate>
                <?= csrf_input() ?>
                <button class="link-button" type="submit">Log out</button>
            </form>
        <?php else: ?>
            <a href="/register.php"<?= $activePath === '/register.php' ? ' aria-current="page"' : '' ?>>Register</a>
            <a href="/login.php"<?= $activePath === '/login.php' ? ' aria-current="page"' : '' ?>>Log in</a>
        <?php endif; ?>
        </div>
    </div>
    <nav aria-label="Main navigation">
        <?php foreach ($navItems as $path => $label): ?>
            <a href="<?= e($path) ?>"<?= ($activePath === $path || ($path === '/challenges.php' && $activePath === '/challenge.php')) ? ' aria-current="page"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>
</header>
<main id="main" class="container" tabindex="-1">
    <?php if ($flash !== null): ?>
        <p class="alert alert-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></p>
    <?php endif; ?>
