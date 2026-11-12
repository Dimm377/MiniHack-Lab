<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/challenges.php';

$pageTitle = 'Workbench';
require __DIR__ . '/includes/header.php';
$user = current_user();
$definitions = challenge_definitions();
$solves = $user === null ? [] : challenge_solves($user['id']);
$nextSlug = null;
foreach ($definitions as $slug => $definition) {
    if (!isset($solves[$slug])) {
        $nextSlug = $slug;
        break;
    }
}
?>
<section class="page-heading">
    <h1>HTTP Security Workbench</h1>
    <p>Learn how requests, responses and server controls work by observing them, then recover training flags hidden in ordinary HTTP behavior.</p>
</section>
<div class="workbench-layout">
<section class="training-index" aria-labelledby="start-heading">
    <div class="section-heading">
        <h2 id="start-heading">How this lab works</h2>
    </div>
    <ol class="onboarding-steps">
        <li>Each challenge hides a training flag such as <code>MHL{…}</code> somewhere in a normal HTTP exchange.</li>
        <li>You investigate with your browser's developer tools (usually F12): the Network panel, page source and cookie storage.</li>
        <li>Flags belong to your local account. Optional hints point the way without giving the flag away.</li>
        <li>Submitting a flag unlocks an explanation of why it worked and where it matters in real applications.</li>
    </ol>
    <?php if ($user === null): ?>
        <p>Challenges need a local account so each flag and solve belongs to you.</p>
        <ul class="inline-links">
            <li><a href="/register.php">Create a local account</a></li>
            <li><a href="/login.php">Log in</a></li>
        </ul>
    <?php else: ?>
        <div class="section-heading progress-row">
            <h3>Your progress</h3>
            <div class="progress-badge">
                <span class="progress-count"><?= count($solves) ?> / <?= count($definitions) ?></span>
                <span class="progress-label">Solved</span>
            </div>
        </div>
        <ul class="inline-links">
            <?php if ($nextSlug !== null): ?>
                <li><a href="/challenge.php?slug=<?= e(rawurlencode($nextSlug)) ?>"><?= $solves === [] ? 'Start' : 'Continue' ?>: <?= e($definitions[$nextSlug]['title']) ?> &rarr;</a></li>
            <?php else: ?>
                <li><a href="/learned.php">Review what you've learned &rarr;</a></li>
            <?php endif; ?>
            <li><a href="/challenges.php">All challenges</a></li>
        </ul>
    <?php endif; ?>

    <h2 class="tools-heading" id="baseline-heading">Secure baseline</h2>
    <p>These features are the secure reference implementation, not challenges. Study how each one protects itself. A working bypass is a defect to report, not a flag.</p>
    <dl class="details" aria-labelledby="baseline-heading">
        <dt><a href="/search.php">User search</a></dt>
        <dd>Literal matching: search for %, _, quotes and backslashes and compare the results.</dd>
        <dt><a href="/notes.php">Private notes</a></dt>
        <dd>Ownership comes from your session, never from IDs you send; stored text is encoded on output.</dd>
        <dt>User API</dt>
        <dd>Explicit JSON status codes, and only public fields leave the server. Try it in the inspector.</dd>
        <dt><a href="<?= $user === null ? '/login.php' : '/profile.php' ?>">Accounts and sessions</a></dt>
        <dd>Generic login errors, password hashing, a new session ID after login and CSRF tokens on every form.</dd>
    </dl>
</section>

<section class="api-demo" aria-labelledby="inspector-heading">
    <div class="section-heading">
        <h2 id="inspector-heading">HTTP Inspector</h2>
    </div>
    <div class="inspector-console">
        <div class="console-header">
            <span class="console-title">Public user API</span>
        </div>
        <form id="api-demo-form" novalidate>
            <div class="request-line">
                <span class="method">GET</span> 
                <code>/api/users.php?id=<input id="api-user-id" class="inline-input" name="id" type="number" min="1" placeholder="1" aria-label="User ID" required></code>
                <button type="submit">Send</button>
            </div>
        </form>
        <div class="response-panel">
            <dl class="response-metadata">
                <dt>Status</dt><dd id="api-status" aria-live="polite">—</dd>
            </dl>
            <dl id="api-headers" class="response-metadata" aria-label="Response headers">
                <dt>Headers</dt><dd>—</dd>
            </dl>
            <pre id="api-result" tabindex="0" aria-label="JSON response"></pre>
        </div>
    </div>
    <p class="hint">Scripts can read most response headers, but browsers hide Set-Cookie from them. Compare this view with the same request in your Network panel.</p>
    <noscript><p class="hint">The inspector needs JavaScript. You can <a href="/api/users.php?id=1">open the endpoint directly</a>.</p></noscript>
</section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
