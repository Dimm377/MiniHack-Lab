<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$sessionUser = require_auth();
// The profile ID comes only from the authenticated session, never from the URL.
$statement = db()->prepare('SELECT id, username, created_at FROM users WHERE id = :id');
$statement->execute(['id' => $sessionUser['id']]);
$profile = $statement->fetch();
if (!is_array($profile)) {
    log_out();
    redirect('/login.php');
}

$pageTitle = 'Profile';
require __DIR__ . '/includes/header.php';
?>
<section class="profile-view">
    <h1>Your profile</h1>
    <dl class="details">
        <dt>User ID</dt><dd class="tabular-data"><?= e($profile['id']) ?></dd>
        <dt>Username</dt><dd><?= e($profile['username']) ?></dd>
        <dt>Registered</dt><dd class="tabular-data"><?= e($profile['created_at']) ?> UTC</dd>
    </dl>
    <p class="hint">Your ID and username are public. Notes and challenge progress are private.</p>
    <p><a href="/notes.php">Open private notes</a> · <a href="/challenges.php">View challenge progress</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
