<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

if (current_user() !== null) {
    redirect('/profile.php');
}

$error = null;
$username = '';
if (is_post()) {
    verify_csrf_or_abort();
    $username = trim(request_string($_POST, 'username'));
    $password = request_string($_POST, 'password');
    if ($username === '' || $password === '' || strlen($username) > 30 || strlen($password) > 72 || str_contains($password, "\0")) {
        $error = 'Invalid username or password.';
    } else {
        $statement = db()->prepare('SELECT id, username, password_hash FROM users WHERE username = :username LIMIT 1');
        $statement->execute(['username' => $username]);
        $user = $statement->fetch();

        // Perform password_verify against a dummy hash when the user is not found to prevent timing-based user enumeration.
        $dummyHash = '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ01234';
        $hashToVerify = is_array($user) ? (string) $user['password_hash'] : $dummyHash;
        $isValidPassword = password_verify($password, $hashToVerify);

        if (!is_array($user) || !$isValidPassword) {
            error_log(sprintf('Failed login attempt for username "%s"', $username));
            // The same message is used whether the username or password is wrong.
            $error = 'Invalid username or password.';
        } else {
            log_in((int) $user['id'], (string) $user['username']);
            redirect('/profile.php');
        }
    }
}

$pageTitle = 'Log in';
require __DIR__ . '/includes/header.php';
?>
<section class="form-panel">
    <h1>Log in</h1>
    <?php if ($error !== null): ?><p id="login-error" class="alert alert-error" role="alert" tabindex="-1" data-error-summary><?= e($error) ?></p><?php endif; ?>
    <form method="post" action="/login.php" novalidate>
        <?= csrf_input() ?>
        <label for="username">Username</label>
        <input id="username" name="username" type="text" maxlength="30" autocomplete="username" value="<?= e($username) ?>"<?= $error !== null ? ' aria-invalid="true" aria-describedby="login-error"' : '' ?> required autofocus>
        <label for="password">Password</label>
        <div class="password-row">
            <input id="password" name="password" type="password" maxlength="72" autocomplete="current-password"<?= $error !== null ? ' aria-invalid="true" aria-describedby="login-error"' : '' ?> required>
            <button type="button" class="secondary toggle-password" data-target="password" aria-label="Show password" aria-pressed="false" hidden>Show</button>
        </div>
        <button type="submit">Log in</button>
    </form>
    <p class="form-switch">No account yet? <a href="/register.php">Register</a></p>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
