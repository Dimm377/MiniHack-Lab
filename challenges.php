<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/challenges.php';
require_once __DIR__ . '/config/database.php';

$user = require_auth();
$definitions = challenge_definitions();
header('Cache-Control: no-store');

$statement = db()->prepare('SELECT challenge_slug, solved_at FROM solves WHERE user_id = :user_id');
$statement->execute(['user_id' => $user['id']]);
$solvedRows = $statement->fetchAll();
$solves = [];
foreach ($solvedRows as $row) {
    if (is_string($row['challenge_slug']) && isset($definitions[$row['challenge_slug']])) {
        $solves[$row['challenge_slug']] = (string) $row['solved_at'];
    }
}

$pageTitle = 'Challenges';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading catalog-heading">
    <div>
        <h1>Challenges</h1>
        <p>Progressive hands-on HTTP and security inspection exercises.</p>
    </div>
    <div class="progress-badge">
        <span class="progress-count"><?= count($solves) ?> / <?= count($definitions) ?></span>
        <span class="progress-label">Solved</span>
    </div>
</section>
<section aria-label="HTTP exercises">
    <div class="section-heading"><h2>HTTP Fundamentals</h2></div>
    <div class="challenge-list">
        <?php $num = 1; foreach ($definitions as $slug => $challenge): ?>
            <?php $isSolved = isset($solves[$slug]); ?>
            <a class="challenge-row" href="/challenge.php?slug=<?= e(rawurlencode($slug)) ?>">
                <span class="challenge-number"><?= sprintf('%02d', $num++) ?></span>
                <div class="challenge-main">
                    <h3><?= e($challenge['title']) ?></h3>
                    <p><?= e($challenge['summary']) ?></p>
                </div>
                <div class="challenge-meta">
                    <span class="method"><?= e($challenge['method']) ?></span>
                    <span class="challenge-status <?= $isSolved ? 'is-solved' : 'is-unsolved' ?>">
                        <?= $isSolved ? '<span class="status-dot"></span> Solved' : 'Open' ?>
                    </span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <p class="hint">Each flag belongs to your account. Repeating a solve keeps the same result.</p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
