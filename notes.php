<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';

$user = require_auth();
header('Cache-Control: private, no-store, must-revalidate');
$errors = [];
$title = '';
$content = '';
$action = '';
if (is_post()) {
    verify_csrf_or_abort();
    $action = request_string($_POST, 'action');
    if ($action === 'create') {
        $title = trim(request_string($_POST, 'title'));
        $content = trim(request_string($_POST, 'content'));
        if ($title === '' || mb_strlen($title, 'UTF-8') > 100) {
            $errors[] = 'Title is required and must be 100 characters or fewer.';
        }
        if ($content === '' || mb_strlen($content, 'UTF-8') > 5000) {
            $errors[] = 'Content is required and must be 5,000 characters or fewer.';
        }
        if ($errors === []) {
            $statement = db()->prepare('INSERT INTO notes (user_id, title, content) VALUES (:user_id, :title, :content)');
            $statement->execute(['user_id' => $user['id'], 'title' => $title, 'content' => $content]);
            set_flash('success', 'Note created.');
            redirect('/notes.php');
        }
    } elseif ($action === 'delete') {
        $noteId = positive_int($_POST['note_id'] ?? null);
        if ($noteId === null) {
            $errors[] = 'Invalid note ID.';
        } else {
            // Including user_id in the delete prevents deletion of another user's note.
            $statement = db()->prepare('DELETE FROM notes WHERE id = :id AND user_id = :user_id');
            $statement->execute(['id' => $noteId, 'user_id' => $user['id']]);
            if ($statement->rowCount() !== 1) {
                $errors[] = 'Note not found or not permitted.';
            } else {
                set_flash('success', 'Note deleted.');
                redirect('/notes.php');
            }
        }
    } else {
        $errors[] = 'Unsupported note action.';
    }
}

// Ownership is applied in SQL so private notes never enter another user's result set.
$statement = db()->prepare('SELECT id, title, content, created_at FROM notes WHERE user_id = :user_id ORDER BY created_at DESC, id DESC');
$statement->execute(['user_id' => $user['id']]);
$notes = $statement->fetchAll();

$pageTitle = 'Notes';
require __DIR__ . '/includes/header.php';
?>
<section class="page-heading">
    <h1>Private notes</h1>
    <p>Your observations and working notes. Only your account can read or delete them.</p>
</section>
<div class="notes-layout">
<section class="note-composer">
    <h2>New note</h2>
    <?php if ($errors !== []): ?>
        <div id="note-errors" class="alert alert-error" role="alert" tabindex="-1" data-error-summary><ul>
            <?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach; ?>
        </ul></div>
    <?php endif; ?>
    <form class="note-form" method="post" action="/notes.php" novalidate>
        <?= csrf_input() ?>
        <input type="hidden" name="action" value="create">
        <label for="title">Title</label>
        <input id="title" name="title" type="text" maxlength="100" value="<?= e($title) ?>"<?= $errors !== [] && $action === 'create' ? ' aria-invalid="true" aria-describedby="note-errors"' : '' ?> required>
        <label for="content">Content</label>
        <textarea id="content" class="resize-none" name="content" rows="8" maxlength="5000"<?= $errors !== [] && $action === 'create' ? ' aria-invalid="true" aria-describedby="note-errors"' : '' ?> required><?= e($content) ?></textarea>
        <button type="submit">Create note</button>
    </form>
</section>
<section>
    <div class="section-heading"><h2>Saved notes</h2><span class="hint"><?= count($notes) ?> <?= count($notes) === 1 ? 'note' : 'notes' ?></span></div>
    <?php if ($notes === []): ?>
        <p class="empty-state">No notes yet. Save a request, an observation or a question using the new note form.</p>
    <?php else: ?>
        <div class="notes-list">
            <?php foreach ($notes as $note): ?>
                <article class="note">
                    <div><h3><?= e($note['title']) ?></h3><p class="note-meta"><?= e($note['created_at']) ?> UTC</p></div>
                    <p class="note-content"><?= nl2br(e($note['content']), false) ?></p>
                    <details class="delete-confirmation">
                    <summary>Delete note</summary>
                    <p>Delete “<?= e($note['title']) ?>”? This cannot be undone. Close this disclosure to cancel.</p>
                    <form method="post" action="/notes.php" novalidate>
                        <?= csrf_input() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="note_id" value="<?= e($note['id']) ?>">
                        <button class="danger" type="submit">Delete permanently</button>
                    </form>
                    </details>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
