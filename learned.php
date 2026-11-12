<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/challenges.php';
require_once __DIR__ . '/config/database.php';

$user = require_auth();
header('Cache-Control: no-store');
$definitions = challenge_definitions();
// Only solved challenges are listed: takeaways are post-solve content.
$solves = challenge_solves($user['id']);

$pageTitle = 'What you\'ve learned';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading catalog-heading">
    <div>
        <h1>What you've learned</h1>
        <p>The takeaways from each challenge you have solved, in one place.</p>
    </div>
    <div class="progress-badge">
        <span class="progress-count"><?= count($solves) ?> / <?= count($definitions) ?></span>
        <span class="progress-label">Solved</span>
    </div>
</section>
<?php if ($solves === []): ?>
    <p class="empty-state">Solve a challenge to collect its takeaways here. <a href="/challenges.php">Browse challenges</a></p>
<?php else: ?>
    <div class="learning-content recap-list">
        <?php foreach ($definitions as $slug => $challenge): ?>
            <?php if (!isset($solves[$slug])) { continue; } ?>
            <section aria-labelledby="recap-<?= e($slug) ?>">
                <h2 id="recap-<?= e($slug) ?>"><a href="/challenge.php?slug=<?= e(rawurlencode($slug)) ?>"><?= e($challenge['title']) ?></a></h2>
                <ul>
                    <?php foreach ($challenge['learning']['takeaways'] as $takeaway): ?>
                        <li><?= e($takeaway) ?></li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
