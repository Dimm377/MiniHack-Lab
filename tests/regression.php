<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

function count_rows(PDO $pdo, string $table): int
{
    return (int) $pdo->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
}

function register_user(string $username): array
{
    $page = get('/register.php');
    $session = cookie($page);
    $response = post('/register.php', [
        'username' => $username, 'password' => 'test-password-123',
        'password_confirmation' => 'test-password-123', 'csrf_token' => csrf($page),
    ], $session);
    check($response['status'] === 303 && ($response['headers']['location'] ?? '') === '/login.php', "Register $username");
    return ['cookie' => $session, 'csrf' => csrf(get('/login.php', $session))];
}

function login_user(string $username, array $guest): array
{
    $response = post('/login.php', [
        'username' => $username, 'password' => 'test-password-123', 'csrf_token' => $guest['csrf'],
    ], $guest['cookie']);
    $session = cookie($response);
    check($response['status'] === 303 && ($response['headers']['location'] ?? '') === '/profile.php', "Log in $username");
    check($session !== '' && $session !== $guest['cookie'], "Login regenerates $username session");
    check(get('/profile.php', $guest['cookie'])['status'] === 303, 'Old session cannot access profile');
    $profile = get('/profile.php', $session);
    check(csrf($profile) !== $guest['csrf'], 'Login rotates CSRF token');
    return ['cookie' => $session, 'csrf' => csrf($profile)];
}

$guestPage = get('/register.php');
$guestCookie = cookie($guestPage);
$guestToken = csrf($guestPage);
$cookieHeader = strtolower($guestPage['headers']['set-cookie'] ?? '');
check(str_contains($cookieHeader, 'httponly') && str_contains($cookieHeader, 'samesite=lax'), 'Session cookie has HttpOnly and SameSite=Lax');
check(!str_contains($cookieHeader, 'secure'), 'Local HTTP cookie does not claim HTTPS');
foreach ([null, 'invalid', ['array-token']] as $token) {
    $data = ['username' => 'csrf_rejected', 'password' => 'test-password-123', 'password_confirmation' => 'test-password-123'];
    if ($token !== null) {
        $data['csrf_token'] = $token;
    }
    check(post('/register.php', $data, $guestCookie)['status'] === 403, 'Registration rejects missing, invalid or array CSRF');
    check(count_rows($pdo, 'users') === 0, 'Rejected registration does not create a user');
}
foreach ([str_repeat('a', 73), "valid-password\0suffix"] as $badPassword) {
    $response = post('/register.php', [
        'username' => 'bad_password', 'password' => $badPassword,
        'password_confirmation' => $badPassword, 'csrf_token' => $guestToken,
    ], $guestCookie);
    check($response['status'] === 200 && count_rows($pdo, 'users') === 0, 'Unsupported bcrypt password is rejected without creating a user or 500');
}
$guestA = register_user('user_alpha');
$guestB = register_user('userbeta');
$ids = $pdo->query('SELECT username, id FROM users')->fetchAll(PDO::FETCH_KEY_PAIR);
$idA = (int) $ids['user_alpha'];
$idB = (int) $ids['userbeta'];
$hash = $pdo->query("SELECT password_hash FROM users WHERE username = 'user_alpha'")->fetchColumn();
check($hash !== 'test-password-123' && password_verify('test-password-123', $hash), 'Registration stores a verifiable password hash');
$duplicate = post('/register.php', [
    'username' => 'user_alpha', 'password' => 'test-password-123',
    'password_confirmation' => 'test-password-123', 'csrf_token' => $guestA['csrf'],
], $guestA['cookie']);
check($duplicate['status'] === 200 && str_contains($duplicate['body'], 'unavailable') && count_rows($pdo, 'users') === 2, 'Duplicate username is rejected without mutation');
foreach ([null, 'invalid'] as $token) {
    $data = ['username' => 'user_alpha', 'password' => 'test-password-123'];
    if ($token !== null) {
        $data['csrf_token'] = $token;
    }
    check(post('/login.php', $data, $guestA['cookie'])['status'] === 403, 'Login rejects missing or invalid CSRF');
    check(get('/profile.php', $guestA['cookie'])['status'] === 303, 'Rejected login does not authenticate');
}
foreach (['user_alpha', 'missing_user', "' OR '1'='1", '<script>alert(1)</script>'] as $username) {
    $response = post('/login.php', ['username' => $username, 'password' => 'wrong', 'csrf_token' => $guestA['csrf']], $guestA['cookie']);
    check($response['status'] === 200 && str_contains($response['body'], 'Invalid username or password.'), 'Invalid credentials and SQLi fail generically');
    check(!str_contains($response['body'], '<script>alert(1)</script>'), 'Reflected login input is encoded');
    check(get('/profile.php', $guestA['cookie'])['status'] === 303, 'Invalid credentials never establish a session');
}
$a = login_user('user_alpha', $guestA);
$b = login_user('userbeta', $guestB);
$profile = get('/profile.php?id=' . $idB . '&user_id=' . $idB, $a['cookie']);
check(str_contains($profile['body'], 'user_alpha') && !str_contains($profile['body'], 'userbeta'), 'Client profile IDs cannot switch identity');
foreach (['/profile.php', '/notes.php', '/challenges.php', '/challenge.php?slug=page-source', '/learned.php'] as $path) {
    check(get($path)['status'] === 303, "Authentication required: $path");
}

$title = '<img src=x onerror=alert(1)>';
$content = '<script>alert("private")</script>' . "\n" . str_repeat('long_note_', 35);
$noteData = ['action' => 'create', 'title' => $title, 'content' => $content, 'user_id' => $idB];
foreach ([null, 'invalid'] as $token) {
    $data = $noteData;
    if ($token !== null) {
        $data['csrf_token'] = $token;
    }
    check(post('/notes.php', $data, $a['cookie'])['status'] === 403 && count_rows($pdo, 'notes') === 0, 'Rejected note creation does not mutate state');
}
$created = post('/notes.php', $noteData + ['csrf_token' => $a['csrf']], $a['cookie']);
check($created['status'] === 303 && count_rows($pdo, 'notes') === 1, 'Valid note creation succeeds');
$note = $pdo->query('SELECT * FROM notes')->fetch();
$noteId = (int) $note['id'];
check((int) $note['user_id'] === $idA, 'Client-provided owner is ignored on creation');
$notesA = get('/notes.php', $a['cookie']);
check(str_contains($notesA['body'], '&lt;img src=x onerror=alert(1)&gt;') && str_contains($notesA['body'], '&lt;script&gt;alert(&quot;private&quot;)&lt;/script&gt;'), 'Stored note title and content are encoded');
check(!str_contains($notesA['body'], $title) && !str_contains($notesA['body'], '<script>alert'), 'No executable stored markup');
$notesB = get('/notes.php?user_id=' . $idA . '&note_id=' . $noteId, $b['cookie']);
check(!str_contains($notesB['body'], 'long_note_'), 'Other user cannot read a note through client IDs');
$deleteB = post('/notes.php', ['action' => 'delete', 'note_id' => $noteId, 'user_id' => $idA, 'csrf_token' => $b['csrf']], $b['cookie']);
check(str_contains($deleteB['body'], 'not found or not permitted') && count_rows($pdo, 'notes') === 1, 'Other user cannot delete a note');
foreach ([null, 'invalid'] as $token) {
    $data = ['action' => 'delete', 'note_id' => $noteId];
    if ($token !== null) {
        $data['csrf_token'] = $token;
    }
    check(post('/notes.php', $data, $a['cookie'])['status'] === 403 && count_rows($pdo, 'notes') === 1, 'Rejected deletion preserves the note');
}
$invalidNote = post('/notes.php', ['action' => 'create', 'title' => 'Draft title', 'content' => '', 'csrf_token' => $a['csrf']], $a['cookie']);
check(str_contains($invalidNote['body'], 'Draft title') && count_rows($pdo, 'notes') === 1, 'Invalid note preserves draft and does not insert');

foreach (["'", '%', '\\', '"'] as $query) {
    $response = get('/search.php?q=' . rawurlencode($query));
    check($response['status'] === 200 && str_contains($response['body'], 'No matching users found'), 'Search treats quote, percent and backslash literally: ' . $query);
}
$underscore = get('/search.php?q=_');
check(str_contains($underscore['body'], 'user_alpha') && !str_contains($underscore['body'], 'userbeta'), 'Underscore searches literally, not as a wildcard');
$searchXss = get('/search.php?q=' . rawurlencode('<script>alert(1)</script>'));
check(str_contains($searchXss['body'], '&lt;script&gt;') && !str_contains($searchXss['body'], '<script>alert'), 'Search reflection is encoded');
check(get('/search.php?q[]=invalid')['status'] === 200, 'Array search input is handled');
check(get('/search.php?q=' . str_repeat('x', 31))['status'] === 200, 'Oversize search is handled');

foreach ([
    ['GET', '/api/users.php?id=' . $idA, 200],
    ['GET', '/api/users.php', 400],
    ['GET', '/api/users.php?id=0', 400],
    ['GET', '/api/users.php?id=-1', 400],
    ['GET', '/api/users.php?id=1%20OR%201=1', 400],
    ['GET', '/api/users.php?id[]=1', 400],
    ['GET', '/api/users.php?id=999999', 404],
    ['POST', '/api/users.php?id=' . $idA, 405],
    ['DELETE', '/api/users.php?id=' . $idA, 405],
] as [$method, $path, $status]) {
    $response = request($method, $path);
    check($response['status'] === $status, "API $method $path returns $status");
    check(str_starts_with($response['headers']['content-type'] ?? '', 'application/json'), 'API content type is JSON');
    check(str_contains($response['headers']['cache-control'] ?? '', 'no-store'), 'API responses are not cached');
    $payload = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
    check(array_keys($payload) === ($status === 200 ? ['id', 'username'] : ['error']), 'API exposes only public fields or a generic error');
    if ($status === 200) {
        check($payload === ['id' => $idA, 'username' => 'user_alpha'], 'API returns the requested public user');
    }
    if ($status === 405) {
        check(($response['headers']['allow'] ?? '') === 'GET', 'API advertises GET only');
    }
    check(!str_contains($response['body'], 'password_hash') && !str_contains($response['body'], 'SQLSTATE') && !str_contains($response['body'], 'MHL{'), 'API never leaks private data or internals');
}

$flags = [];
$releasedPhrases = [
    'query-parameters' => 'qu3ry_p4r4m3t3rs_m4tt3r',
    'response-headers' => 'r34d_th3_h34d3rs',
    'page-source' => 'v13w_s0urc3_n3v3r_l13s',
    'cookie-state' => 'c00k13s_r3m3mb3r',
    'request-method-body' => 'us3_th3_r1ght_m3th0d',
];
$definitions = challenge_definitions();
check(array_keys($definitions) === array_keys($releasedPhrases), 'Released challenge slugs remain unchanged');
foreach (['query-parameters', 'response-headers', 'page-source', 'cookie-state', 'request-method-body'] as $slug) {
    $path = '/challenge.php?slug=' . $slug;
    $plain = get($path, $a['cookie']);
    $definition = $definitions[$slug];
    $hints = $definition['hints'] ?? [];
    $learning = $definition['learning'] ?? [];
    $objective = $definition['objective'] ?? '';
    check($objective !== '' && str_contains($plain['body'], htmlspecialchars($objective, ENT_QUOTES, 'UTF-8')), "$slug objective is visible before solve");
    check(array_keys($hints) === ['direction', 'concept', 'action']
        && count(array_filter($hints, static fn ($hint) => is_string($hint) && trim($hint) !== '')) === 3,
        "$slug has exactly three non-empty progressive hints");
    check(substr_count($plain['body'], '<details>') === 3
        && preg_match('/<details>\s*<summary>Hint 1.*?<details>\s*<summary>Hint 2.*?<details>\s*<summary>Hint 3.*?<\/details>\s*<\/details>\s*<\/details>/s', $plain['body']) === 1,
        "$slug hints are collapsed and progressively nested without JavaScript");
    check(count($hints) === 3 && count(array_filter($hints, static fn ($hint) => str_contains($plain['body'], htmlspecialchars($hint, ENT_QUOTES, 'UTF-8')))) === 3,
        "$slug renders its own hint content");
    $takeaways = $learning['takeaways'] ?? [];
    check(trim($learning['why_it_worked'] ?? '') !== '' && trim($learning['real_world_relevance'] ?? '') !== ''
        && count($takeaways) >= 2 && count($takeaways) <= 4
        && count(array_filter($takeaways, static fn ($item) => is_string($item) && trim($item) !== '')) === count($takeaways),
        "$slug has complete post-solve metadata and two to four takeaways");
    $postSolveText = [$learning['why_it_worked'] ?? '', $learning['real_world_relevance'] ?? '', ...$takeaways];
    $headings = ['Why It Worked', 'Real-World Relevance', 'What To Remember'];
    foreach ([...$headings, ...array_filter($postSolveText)] as $text) {
        check(!str_contains($plain['body'], htmlspecialchars($text, ENT_QUOTES, 'UTF-8')), "$slug keeps post-solve text out of unsolved HTML");
    }
    $anonymous = get($path);
    check($anonymous['status'] === 303 && ($anonymous['headers']['location'] ?? '') === '/login.php'
        && !str_contains($anonymous['body'], 'MHL{') && !isset($anonymous['headers']['x-minihack-flag']),
        "$slug learning and flags require authentication");
    if (in_array($slug, ['query-parameters', 'request-method-body'], true)) {
        $method = $slug === 'query-parameters' ? 'get' : 'post';
        check(preg_match('/<form[^>]*method="' . $method . '"[^>]*>.*?name="inspect".*?<button[^>]*type="submit"/s', $plain['body']) === 1,
            "$slug provides a native experiment form");
        check(str_contains($plain['body'], 'page, request, body'), "$slug offers a bounded set of experiments without requiring hints or guessing");
        $inspectionInput = '"><script>alert(1)</script>';
        $inspectionResponse = $method === 'get'
            ? get($path . '&inspect=' . rawurlencode($inspectionInput), $a['cookie'])
            : post($path, ['inspect' => $inspectionInput], $a['cookie']);
        check(str_contains($inspectionResponse['body'], htmlspecialchars($inspectionInput, ENT_QUOTES, 'UTF-8'))
            && !str_contains($inspectionResponse['body'], $inspectionInput), "$slug safely encodes retained inspection input");
        check(!str_contains($plain['body'], 'Server received'), "$slug shows no server echo before an experiment");
        $locked = get($path . '&inspect=' . ($method === 'get' ? 'page' : 'body'), $a['cookie']);
        check(str_contains($locked['body'], 'Server received') && str_contains($locked['body'], 'Locked')
            && !preg_match('/MHL\{[a-z0-9_]+_[a-f0-9]{12}\}/', $locked['body']), "$slug echoes a locked experiment without a flag");
        if ($method === 'post') {
            check(str_contains($locked['body'], '<dt>Query inspect</dt><dd>&quot;body&quot;</dd>')
                && str_contains($locked['body'], '<dt>Body inspect</dt><dd>not sent</dd>'), 'Body challenge shows a query value is not a body value');
            $lockedPost = post($path, ['inspect' => 'page'], $a['cookie']);
            check(str_contains($lockedPost['body'], '<span class="method">POST</span>')
                && str_contains($lockedPost['body'], '<dt>Body inspect</dt><dd>&quot;page&quot;</dd>'), 'Body challenge echoes the actual POST method and body field');
        }
    }
    check(str_contains($plain['body'], '<span class="method">GET</span> <code>/challenge.php?slug=' . $slug . '</code>'), "$slug request line shows the actual method and URI");
    
    if ($slug === 'request-method-body') {
        $solvesBeforeExploration = count_rows($pdo, 'solves');
        $responseA = post($path, ['inspect' => 'body'], $a['cookie']);
        $responseB = post($path, ['inspect' => 'body'], $b['cookie']);
        check(count_rows($pdo, 'solves') === $solvesBeforeExploration, 'Exploration POST does not mutate solve state');
    } else {
        $responseA = get($path . ($slug === 'query-parameters' ? '&inspect=request' : ''), $a['cookie']);
        $responseB = get($path . ($slug === 'query-parameters' ? '&inspect=request' : ''), $b['cookie']);
    }
    
    if (in_array($slug, ['query-parameters', 'request-method-body'], true)) {
        check(str_contains($responseA['body'], 'Unlocked'), "$slug reports an unlocked experiment");
    }
    check($plain['status'] === 200 && str_contains($plain['headers']['cache-control'] ?? '', 'no-store'), "$slug has Cache-Control no-store");
    if ($slug === 'response-headers') {
        $flagA = $responseA['headers']['x-minihack-flag'] ?? '';
        $flagB = $responseB['headers']['x-minihack-flag'] ?? '';
        check(!str_contains($responseA['body'], $flagA) && $flagA !== '', 'Header challenge flag is outside the HTML body');
    } elseif ($slug === 'cookie-state') {
        $cookieHeaderA = urldecode($responseA['headers']['set-cookie'] ?? '');
        $cookieHeaderB = urldecode($responseB['headers']['set-cookie'] ?? '');
        preg_match('/minihack_training=(MHL\{[a-z0-9_]+_[a-f0-9]{12}\})/', $cookieHeaderA, $matchA);
        preg_match('/minihack_training=(MHL\{[a-z0-9_]+_[a-f0-9]{12}\})/', $cookieHeaderB, $matchB);
        $flagA = $matchA[1] ?? '';
        $flagB = $matchB[1] ?? '';
        check($flagA !== '', 'Cookie state flag is in set-cookie header');
    } else {
        $pattern = $slug === 'page-source' ? '/<!-- MiniHack challenge flag: (MHL\{[a-z0-9_]+_[a-f0-9]{12}\}) -->/' : '/(MHL\{[a-z0-9_]+_[a-f0-9]{12}\})/';
        preg_match($pattern, $responseA['body'], $matchA);
        preg_match($pattern, $responseB['body'], $matchB);
        $flagA = $matchA[1] ?? '';
        $flagB = $matchB[1] ?? '';
        if ($slug === 'query-parameters') {
            check(!preg_match('/MHL\{[a-z0-9_]+_[a-f0-9]{12}\}/', $plain['body']), 'Query flag is absent until unlocked');
            foreach (['Request', 'wrong', 'request%00', ''] as $inspect) {
                check(!preg_match('/MHL\{[a-z0-9_]+_[a-f0-9]{12}\}/', get($path . '&inspect=' . $inspect, $a['cookie'])['body']), 'Query challenge requires the exact parameter');
            }
        } elseif ($slug === 'request-method-body') {
            check(!preg_match('/MHL\{[a-z0-9_]+_[a-f0-9]{12}\}/', $plain['body']), 'POST body flag is absent until unlocked');
            foreach ([get($path . '&inspect=body', $a['cookie']), post($path, ['inspect' => 'request'], $a['cookie']), post($path, ['inspect' => ['body']], $a['cookie'])] as $wrongExperiment) {
                check(!str_contains($wrongExperiment['body'], $flagA), 'Body challenge still requires POST and the exact scalar body value');
            }
        } else {
            check(!str_contains(strip_tags($responseA['body']), $flagA) && $flagA !== '', 'Page-source flag is inside an HTML comment');
        }
    }
    check((bool) preg_match('/\AMHL\{[a-z0-9_]+_[a-f0-9]{12}\}\z/', $flagA) && $flagA !== $flagB, "$slug flags differ per user");
    $canonicalDigest = hash_hmac('sha256', $idA . ':' . $slug, hex2bin(trim(file_get_contents($testDirectory . '/instance_secret'))));
    check($flagA === 'MHL{' . $releasedPhrases[$slug] . '_' . substr($canonicalDigest, 0, 12) . '}'
        && challenge_flag($idA, $slug) === $flagA, "$slug preserves its phrase, canonical HMAC-SHA256 input and 12-hex suffix");
    foreach ([...$headings, ...array_filter($postSolveText)] as $text) {
        check(!str_contains($responseA['body'], htmlspecialchars($text, ENT_QUOTES, 'UTF-8')), "$slug discovery alone does not unlock explanation");
    }
    $flags[] = $flagA;
    $before = count_rows($pdo, 'solves');
    foreach ([null, 'invalid'] as $token) {
        $data = ['flag' => $flagA];
        if ($token !== null) {
            $data['csrf_token'] = $token;
        }
        $rejected = post($path, $data, $a['cookie']);
        check($rejected['status'] === 403 && count_rows($pdo, 'solves') === $before, 'Challenge CSRF rejection never records a solve');
        check(str_contains($rejected['headers']['cache-control'] ?? '', 'no-store'), 'Rejected challenge responses are not cached');
    }
    foreach ([$flagB, 'MHL{invalid}', '', str_repeat('x', 81), strtoupper($flagA), substr($flagA, 0, -2) . '}', $flagA . 'extra'] as $invalid) {
        $rejected = post($path, ['flag' => $invalid, 'csrf_token' => $a['csrf'], 'user_id' => $idB], $a['cookie']);
        check(str_contains($rejected['body'], 'The submitted flag is not correct.') && count_rows($pdo, 'solves') === $before, 'Invalid or another user flag fails generically');
        check(!str_contains($rejected['body'], 'Real-World Relevance'), 'Invalid flag does not unlock learning');
    }
    $firstTakeaway = htmlspecialchars($takeaways[0], ENT_QUOTES, 'UTF-8');
    check(!str_contains(get('/learned.php', $a['cookie'])['body'], $firstTakeaway), "$slug takeaways stay off the recap until solved");
    $valid = post($path, ['flag' => $flagA, 'csrf_token' => $a['csrf'], 'user_id' => $idB], $a['cookie']);
    check($valid['status'] === 303 && count_rows($pdo, 'solves') === $before + 1, 'Valid flag records a solve');
    $duplicate = post($path, ['flag' => $flagA, 'csrf_token' => $a['csrf']], $a['cookie']);
    check($duplicate['status'] === 303 && count_rows($pdo, 'solves') === $before + 1, 'Duplicate solve is idempotent');
    $solved = get($path, $a['cookie']);
    $slugs = array_keys($definitions);
    $next = $slugs[array_search($slug, $slugs, true) + 1] ?? null;
    check(str_contains($solved['body'], 'href="' . ($next === null ? '/learned.php' : '/challenge.php?slug=' . $next) . '"'), "$slug links onward after a solve");
    check(str_contains(get('/learned.php', $a['cookie'])['body'], $firstTakeaway), "$slug takeaways appear on the recap after a solve");
    $solveRow = $pdo->query('SELECT user_id, challenge_slug, solved_at FROM solves WHERE user_id = ' . $idA . ' AND challenge_slug = ' . $pdo->quote($slug))->fetch();
    check($solveRow !== false && $solveRow['challenge_slug'] === $slug && str_contains($solved['body'], 'solved ' . $solveRow['solved_at'] . ' UTC'), 'Solve timestamp persists and is visible on a subsequent request');
    foreach ([...$headings, ...array_filter($postSolveText)] as $text) {
        check(str_contains($solved['body'], htmlspecialchars($text, ENT_QUOTES, 'UTF-8')), "$slug renders its post-solve explanation after submission");
    }
    foreach ($definitions as $otherSlug => $otherDefinition) {
        if ($otherSlug !== $slug && isset($otherDefinition['learning']['real_world_relevance'])) {
            check(!str_contains($solved['body'], htmlspecialchars($otherDefinition['learning']['real_world_relevance'], ENT_QUOTES, 'UTF-8')),
                "$slug does not render $otherSlug explanation");
        }
    }
    $otherUser = get($path . '&user_id=' . $idA . '&solved=1', $b['cookie']);
    check(!str_contains($otherUser['body'], 'Real-World Relevance') && !str_contains($otherUser['body'], $flagA), "$slug cannot borrow another user's solved state or flag");
}
check(count(array_unique($flags)) === 5, 'Each challenge uses a distinct flag');
check((int) $pdo->query('SELECT COUNT(*) FROM solves WHERE user_id = ' . $idB)->fetchColumn() === 0, 'Another user has no recorded progress');
check(str_contains(get('/challenges.php', $a['cookie'])['body'], '5 / 5') && str_contains(get('/challenges.php', $b['cookie'])['body'], '0 / 5'), 'Catalog progress is isolated');
$learnedB = get('/learned.php', $b['cookie']);
$anyTakeaway = static fn (string $body): bool => array_filter($definitions, static fn ($d) => str_contains($body, htmlspecialchars($d['learning']['takeaways'][0], ENT_QUOTES, 'UTF-8'))) !== [];
check($learnedB['status'] === 200 && str_contains($learnedB['headers']['cache-control'] ?? '', 'no-store') && !$anyTakeaway($learnedB['body']), 'Recap shows only the viewer\'s own solves');
$guestHome = get('/');
check(str_contains($guestHome['body'], 'How this lab works') && str_contains($guestHome['body'], 'Secure baseline')
    && !str_contains($guestHome['body'], 'challenge-row'), 'Workbench explains the lab and baseline without duplicating the catalog');
check(preg_match('/<input id="api-user-id"[^>]*aria-label="User ID"/', $guestHome['body']) === 1
    && str_contains($guestHome['body'], 'aria-live="polite"') && str_contains($guestHome['body'], 'id="api-headers"'), 'Inspector input is labelled and results are announced');
$homeB = get('/', $b['cookie']);
check(str_contains($homeB['body'], '0 / 5') && str_contains($homeB['body'], 'href="/challenge.php?slug=query-parameters"'), 'Workbench offers the next unsolved challenge');
$homeA = get('/', $a['cookie']);
check(str_contains($homeA['body'], '5 / 5') && str_contains($homeA['body'], 'href="/learned.php"'), 'Workbench links a finished learner to the recap');
check(get('/challenge.php?slug=unknown', $a['cookie'])['status'] === 404, 'Unknown challenge returns 404');
check(get('/challenge.php?slug[]=page-source', $a['cookie'])['status'] === 404, 'Array challenge slug returns 404');

foreach ([
    '/README.md', '/SECURITY.md', '/docs/architecture.md', '/docs/principles.md', '/router.php',
    '/database', '/database/minihack.sqlite', '/database/instance_secret',
    '/config/database.php', '/includes/challenges.php', '/scripts/init_db.php',
    '/tests/run_tests.php', '/tests/bootstrap.php', '/tests/debug_test.php', '/patch.php',
    '/.git/config', '/.github/workflows/php.yml', '/.env', '/.htaccess',
    '/%2egit/config', '/assets/../database/instance_secret',
    '/assets/%2e%2e/database/instance_secret', '/%252e%252e/database/instance_secret',
    '/assets%2f..%2fdatabase%2finstance_secret', '/assets%5c..%5cdatabase%5cinstance_secret',
    '/index.php/../database/instance_secret', '/index.php/extra', '/database/instance_secret%00.css',
] as $path) {
    $response = get($path);
    check($response['status'] === 404 && !str_contains($response['body'], trim(file_get_contents($testDirectory . '/instance_secret'))), "Private path blocked: $path");
}
foreach (['/', '/assets/css/style.css', '/assets/js/app.js'] as $path) {
    check(get($path)['status'] === 200, "Public route still works: $path");
}
foreach (['minihack.sqlite', 'instance_secret'] as $file) {
    check((fileperms($testDirectory . '/' . $file) & 0777) === 0600, "$file has owner-only permissions");
}

// Failure injection is confined to the test database and test-owned secret.
$pdo->exec('ALTER TABLE users RENAME TO temporarily_unavailable_users');
try {
    $response = get('/api/users.php?id=' . $idA);
    check($response['status'] === 500 && json_decode($response['body'], true) === ['error' => 'Unexpected server failure.'], 'API database errors return generic JSON');
    check(str_starts_with($response['headers']['content-type'] ?? '', 'application/json'), 'API 500 keeps JSON content type');
    $response = get('/profile.php', $a['cookie']);
    check($response['status'] === 500 && !str_contains($response['body'], 'SQLSTATE') && !str_contains($response['body'], $testDirectory), 'HTML database errors hide paths and SQL details');
} finally {
    $pdo->exec('ALTER TABLE temporarily_unavailable_users RENAME TO users');
}
rename($testDirectory . '/instance_secret', $testDirectory . '/secret-backup');
try {
    $response = get('/challenge.php?slug=page-source', $a['cookie']);
    check($response['status'] === 500 && !str_contains($response['body'], 'instance_secret') && !str_contains($response['body'], 'MHL{'), 'Missing challenge secret fails generically without a flag');
} finally {
    rename($testDirectory . '/secret-backup', $testDirectory . '/instance_secret');
}
$httpsOptions = json_decode(run_php(['-d', 'session.save_path=' . $testDirectory . '/sessions', '-r',
    '$_SERVER["HTTPS"] = "on"; require ' . var_export($root . '/includes/functions.php', true) . '; echo json_encode(session_get_cookie_params()); session_destroy();',
]), true, 512, JSON_THROW_ON_ERROR);
check($httpsOptions['secure'] === true && $httpsOptions['httponly'] === true && $httpsOptions['samesite'] === 'Lax', 'HTTPS server context enables Secure session cookies');

foreach (['/', '/search.php', '/api/users.php?id=1', '/challenges.php'] as $path) {
    $response = get($path, $a['cookie']);
    check(($response['headers']['x-content-type-options'] ?? '') === 'nosniff', "nosniff header: $path");
    check(($response['headers']['referrer-policy'] ?? '') === 'same-origin', "Referrer policy: $path");
    check(str_contains($response['headers']['content-security-policy'] ?? '', "frame-ancestors 'none'"), "CSP frame protection: $path");
}
// expose_php is a php.ini setting, so every response path must remove the header itself.
foreach (['/', '/api/users.php?id=1', '/assets/css/style.css', '/missing-page', '/logout.php'] as $path) {
    check(!isset(get($path, $a['cookie'])['headers']['x-powered-by']), "No X-Powered-By version disclosure: $path");
}
check(post('/notes.php', ['action' => 'delete', 'note_id' => $noteId, 'csrf_token' => $a['csrf']], $a['cookie'])['status'] === 303 && count_rows($pdo, 'notes') === 0, 'Owner can delete their note');
check(get('/logout.php', $a['cookie'])['status'] === 405, 'Logout is POST-only');
foreach ([null, 'invalid'] as $token) {
    $data = $token === null ? [] : ['csrf_token' => $token];
    check(post('/logout.php', $data, $a['cookie'])['status'] === 403, 'Logout rejects missing or invalid CSRF');
    check(get('/profile.php', $a['cookie'])['status'] === 200, 'Rejected logout preserves authentication');
}
$logout = post('/logout.php', ['csrf_token' => $a['csrf']], $a['cookie']);
check($logout['status'] === 303 && str_contains($logout['headers']['set-cookie'] ?? '', 'Max-Age=0'), 'Valid logout expires the cookie');
check(get('/profile.php', $a['cookie'])['status'] === 303, 'Logged-out session cannot be reused');
check(get('/profile.php', $b['cookie'])['status'] === 200, 'Logout leaves other sessions intact');
check(hash_file('sha256', $testDirectory . '/instance_secret') === $secretBefore, 'Requests do not change the instance secret');
