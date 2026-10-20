<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/challenges.php';

$pageTitle = 'Workbench';
require __DIR__ . '/includes/header.php';
$user = current_user();
?>
<section class="page-heading">
    <h1>HTTP Security Workbench</h1>
    <p>Inspect request parameters, study server security controls, and solve isolated local challenges.</p>
</section>
<div class="workbench-layout">
<section class="training-index" aria-labelledby="challenges-heading">
    <div class="section-heading">
        <h2 id="challenges-heading">Challenges</h2>
    </div>
    <div class="challenge-list">
        <?php $num = 1; foreach (challenge_definitions() as $slug => $definition): ?>
            <a class="challenge-row" href="/challenge.php?slug=<?= e($slug) ?>">
                <span class="challenge-number"><?= sprintf('%02d', $num++) ?></span>
                <div class="challenge-main">
                    <h3><?= e($definition['title']) ?></h3>
                    <p><?= e($definition['summary']) ?></p>
                </div>
                <div class="challenge-meta">
                    <span class="method"><?= e($definition['method']) ?></span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
    <div class="baseline-links">
        <h2 class="tools-heading">Utilities</h2>
        <ul class="inline-links">
            <li><a href="/search.php">User search</a></li>
            <li><a href="/notes.php">Private notes</a></li>
            <li><a href="<?= $user === null ? '/login.php' : '/profile.php' ?>"><?= $user === null ? 'Log in' : 'Your profile' ?></a></li>
        </ul>
    </div>
</section>

<section class="api-demo" aria-labelledby="inspector-heading">
    <div class="section-heading">
        <h2 id="inspector-heading">HTTP Inspector</h2>
    </div>
    <div class="inspector-console">
        <form id="api-demo-form" novalidate>
            <div class="request-line">
                <span class="method">GET</span> 
                <code>/api/users.php?id=<input id="api-user-id" class="inline-input" name="id" type="number" min="1" placeholder="1" required></code>
                <button type="submit">Send</button>
            </div>
        </form>
        <div class="response-panel">
            <dl id="api-metadata" class="response-metadata">
                <dt>Status</dt><dd id="api-status">—</dd>
                <dt>Content-Type</dt><dd>—</dd>
                <dt>Cache-Control</dt><dd>—</dd>
            </dl>
            <pre id="api-result" tabindex="0" aria-label="JSON response"></pre>
        </div>
    </div>
    <noscript><p class="hint">The inspector needs JavaScript. You can <a href="/api/users.php?id=1">open the endpoint directly</a>.</p></noscript>
</section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
