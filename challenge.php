<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/challenges.php';
require_once __DIR__ . '/includes/trace.php';
require_once __DIR__ . '/config/database.php';

$user = require_auth();
$slug = request_string($_GET, 'slug');
$challenge = challenge_definition($slug);
header('Cache-Control: no-store');

if ($challenge === null) {
    http_response_code(404);
    $pageTitle = 'Challenge not found';
    require __DIR__ . '/includes/header.php';
    echo '<section><h1>Challenge not found</h1><p>The requested challenge does not exist.</p></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$expectedFlag = challenge_flag($user['id'], $slug);
challenge_dispatch_delivery($slug, $expectedFlag);

$error = null;
if (is_post() && isset($_POST['flag'])) {
    verify_csrf_or_abort();
    $submittedFlag = trim(request_string($_POST, 'flag'));

    if ($submittedFlag === '' || strlen($submittedFlag) > 80 || !hash_equals($expectedFlag, $submittedFlag)) {
        $error = 'The submitted flag is not correct.';
    } else {
        $statement = db()->prepare(
            'INSERT INTO solves (user_id, challenge_slug) VALUES (:user_id, :challenge_slug) '
            . 'ON CONFLICT(user_id, challenge_slug) DO NOTHING'
        );
        $statement->execute(['user_id' => $user['id'], 'challenge_slug' => $slug]);

        set_flash(
            'success',
            $statement->rowCount() === 1 ? 'Challenge solved.' : 'Challenge was already solved.'
        );
        redirect('/challenge.php?slug=' . rawurlencode($slug));
    }
}

$statement = db()->prepare(
    'SELECT solved_at FROM solves WHERE user_id = :user_id AND challenge_slug = :challenge_slug'
);
$statement->execute(['user_id' => $user['id'], 'challenge_slug' => $slug]);
$solvedAt = $statement->fetchColumn();

$isUnlocked = challenge_is_unlocked($slug);
$inspection = $challenge['inspection'] ?? null;
$requestMethod = request_string($_SERVER, 'REQUEST_METHOD');
$requestUri = request_string($_SERVER, 'REQUEST_URI');
$bodyFields = is_post() ? $_POST : [];
$attempted = $inspection !== null && (array_key_exists('inspect', $_GET) || array_key_exists('inspect', $bodyFields));
// Echo what arrived without revealing the expected value; e() encodes it at output.
$describeField = static fn (array $source): string => !array_key_exists('inspect', $source)
    ? 'not sent'
    : (is_string($source['inspect']) ? '"' . $source['inspect'] . '"' : 'not a single value');
$nextSlug = challenge_next_slug($slug);

$pageTitle = $challenge['title'];
require __DIR__ . '/includes/header.php';
?>
<?= challenge_source_comment($slug, $expectedFlag) ?>
<section class="page-heading">
    <p class="back-link"><a href="/challenges.php">&larr; All challenges</a></p>
    <h1><?= e($challenge['title']) ?></h1>
    <div class="section-heading">
        <p><?= e($challenge['summary']) ?></p>
        <span class="challenge-status <?= $solvedAt !== false ? 'is-solved' : '' ?>">
            <?= $solvedAt !== false ? 'solved ' . e($solvedAt) . ' UTC' : 'unsolved' ?>
        </span>
    </div>
</section>
<p class="request-line"><span class="method"><?= e($requestMethod) ?></span> <code><?= e($requestUri) ?></code></p>
<?php if ($solvedAt !== false): ?>
<div class="exercise-layout solved-layout">
<section class="learning-content">
    <h2>Why It Worked</h2>
    <p><?= e($challenge['learning']['why_it_worked']) ?></p>
    <h2>Real-World Relevance</h2>
    <p><?= e($challenge['learning']['real_world_relevance']) ?></p>
    <h2>What To Remember</h2>
    <ul>
        <?php foreach ($challenge['learning']['takeaways'] as $takeaway): ?>
            <li><?= e($takeaway) ?></li>
        <?php endforeach; ?>
    </ul>
</section>
<aside class="solved-answer" aria-labelledby="solved-heading">
    <h2 id="solved-heading">Solved</h2>
    <code><?= e($expectedFlag) ?></code>
    <ul class="inline-links solved-next">
        <?php if ($nextSlug !== null): ?>
            <li><a class="primary-link" href="/challenge.php?slug=<?= e(rawurlencode($nextSlug)) ?>">Next challenge: <?= e(challenge_definition($nextSlug)['title']) ?> &rarr;</a></li>
        <?php endif; ?>
        <li><a<?= $nextSlug === null ? ' class="primary-link"' : '' ?> href="/learned.php">Review what you've learned<?= $nextSlug === null ? ' &rarr;' : '' ?></a></li>
    </ul>
</aside>
</div>
<?php endif; ?>
<div class="exercise-layout<?= $solvedAt !== false ? ' is-solved' : '' ?>">
<section class="challenge-task">
    <h2>Objective</h2>
    <p><?= e($challenge['objective']) ?></p>
    <h2>Investigation</h2>
    <ol>
        <?php foreach ($challenge['instructions'] as $instruction): ?>
            <li><?= e($instruction) ?></li>
        <?php endforeach; ?>
    </ol>

    <?php if (isset($challenge['trigger'])): ?>
        <ul class="inline-links">
            <li><a href="<?= e('/challenge.php?slug=' . rawurlencode($slug) . '&' . $challenge['trigger']['query']) ?>"><?= e($challenge['trigger']['label']) ?></a></li>
        </ul>
        <?php if (isset($challenge['trigger']['arrival']) && request_string($_GET, 'arrived') === '1'): ?>
            <div class="challenge-output">
                <span>Page response</span>
                <p><?= e($challenge['trigger']['arrival']) ?></p>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (isset($challenge['records']) && array_key_exists('record', $_GET)): ?>
        <?php
        // Intentional flaw over synthetic data only: the lookup uses the client ID and never checks the owner.
        $recordId = positive_int($_GET['record']);
        $record = $recordId === null ? null : ($challenge['records'][$recordId] ?? null);
        ?>
        <?= http_trace('Record lookup', [
            ['direction' => 'request', 'summary' => [$requestMethod, $requestUri], 'note' => null, 'sections' => [], 'open' => false],
            ['direction' => 'response', 'summary' => [200, 'OK'],
                'note' => $record === null ? 'Record not found' : 'Record #' . $recordId,
                'sections' => $record === null ? [] : ['Record' => ['Owner' => $record['owner'], 'Content' => $record['content']]],
                'open' => $record !== null],
        ]) ?>
        <?php if ($record !== null && $record['holds_flag']): ?><p class="trace-flag"><code><?= e($expectedFlag) ?></code></p><?php endif; ?>
    <?php endif; ?>

    <?php if ($inspection !== null): ?>
    <form method="<?= e(strtolower($inspection['method'])) ?>" action="/challenge.php?slug=<?= e(rawurlencode($slug)) ?>" novalidate>
        <input type="hidden" name="slug" value="<?= e($slug) ?>">
        <label for="inspect">Inspection target</label>
        <p id="inspect-help" class="hint">Available targets: page, request, body. Compare their responses.</p>
        <input id="inspect" name="inspect" type="text" value="<?= e(request_string($inspection['method'] === 'GET' ? $_GET : $bodyFields, 'inspect')) ?>" aria-describedby="inspect-help" autocomplete="off" spellcheck="false">
        <button type="submit">Send inspection</button>
    </form>
    <?php endif; ?>

    <?php if ($attempted): ?>
        <?= http_trace('Server received', [
            ['direction' => 'request', 'summary' => [$requestMethod, $requestUri], 'note' => null,
                'sections' => ['Fields' => ['Query inspect' => $describeField($_GET), 'Body inspect' => $describeField($bodyFields)]],
                'open' => true],
            ['direction' => 'response', 'summary' => [200, 'OK'], 'note' => $isUnlocked ? 'Unlocked' : 'Locked',
                'sections' => ['Result' => ['Result' => $isUnlocked ? 'Unlocked: the server returned the training flag.' : "Locked: this request did not meet the server's condition."]],
                'open' => true],
        ]) ?>
        <?php if ($isUnlocked): ?><p class="trace-flag"><code><?= e($expectedFlag) ?></code></p><?php endif; ?>
    <?php endif; ?>

    <section class="hint-panel" aria-labelledby="hints-heading">
    <h3 id="hints-heading">Progressive hints</h3>
    <p class="hint">Optional. Each hint opens the next one; none gives the flag away.</p>
    <details>
        <summary>Hint 1 — Direction</summary>
        <p><?= e($challenge['hints']['direction']) ?></p>
        <details>
            <summary>Hint 2 — Concept</summary>
            <p><?= e($challenge['hints']['concept']) ?></p>
            <details>
                <summary>Hint 3 — Action</summary>
                <p><?= e($challenge['hints']['action']) ?></p>
            </details>
        </details>
    </details>
    </section>
</section>

<?php if ($solvedAt === false): ?>
<section class="flag-submit">
    <h2>Submit flag</h2>
    <?php if ($error !== null): ?>
        <p id="flag-error" class="alert alert-error" role="alert" tabindex="-1" data-error-summary><?= e($error) ?></p>
    <?php endif; ?>
    <p class="hint">Paste the complete flag, including <code>MHL{}</code>.</p>
    <form method="post" action="/challenge.php?slug=<?= e(rawurlencode($slug)) ?>" novalidate>
        <?= csrf_input() ?>
        <label for="flag">Flag</label>
        <input id="flag" class="flag-input" name="flag" type="text" maxlength="80" autocomplete="off" spellcheck="false" placeholder="MHL{...}" value="<?= e($error !== null ? $submittedFlag : '') ?>"<?= $error !== null ? ' aria-invalid="true" aria-describedby="flag-error"' : '' ?> required>
        <button type="submit">Submit flag</button>
    </form>
</section>
<?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
