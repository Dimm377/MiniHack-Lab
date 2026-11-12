# Redirect and IDOR Challenges Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add challenge 6 `redirect-response` (HTTP fundamentals) and challenge 7 `object-reference` (IDOR on synthetic records), and group the catalog by category.

**Architecture:** Everything stays inside the code-defined registry (`includes/challenges.php`) and `challenge.php`. New behaviour is driven by optional registry keys (`category`, `trigger`, `records`) plus one `redirect-response` branch in `challenge_dispatch_delivery()`. No new route, schema, dependency or JavaScript.

**Tech Stack:** PHP 8.2+ (`declare(strict_types=1)`), PDO SQLite, native HTML/CSS; tests are real HTTP requests in `tests/regression.php` run by `php tests/run_tests.php`.

**Spec:** `docs/superpowers/specs/2026-11-12-redirect-and-idor-challenges-design.md`

## Global Constraints

- Existing slugs, phrases and registry order are unchanged; new challenges are appended after `request-method-body`.
- Slugs/phrases (immutable once merged): `redirect-response` / `r3d1r3cts_t4k3_a_d3t0ur`; `object-reference` / `ch3ck_wh0_0wns_1t`.
- Titles: `Lost in Transit`; `Someone Else's Record`. Categories: `HTTP Fundamentals` (first six), `Access Control` (`object-reference`).
- No new `if ($slug === ...)` in templates; no change to schema, `router.php`, `.htaccess`, `assets/js/app.js`, `learned.php`, `index.php`.
- Every untrusted value is output through `e()`; challenge responses keep `Cache-Control: no-store`.
- Records are static code data; they never read `users`, `notes` or `solves`. Owner `lab-reviewer` (hyphen, impossible as a real username).
- Hints follow direction/concept/action; learning content appears only after a recorded solve; 2–4 takeaways.
- Commits carry no AI attribution trailers. Run the whole suite (`php tests/run_tests.php`) at every verify step.
- The index holds the owner's own staged deletions (`DESIGN.md`, `SECURITY.md`) and `.gitignore` edit. Never commit them: always commit with an explicit pathspec, `git add <paths> && git commit -m "<msg>" -- <paths>`.

## Review Focus

- `record=01041` (leading zero): `positive_int()` rejects it → "Record not found", no flag. Test in Task 3.
- `record=1041` sent to a *different* challenge (e.g. `query-parameters`): no record card, no `object-reference` flag. Test in Task 3.
- `go[]=archive` (array): no redirect, no flag header. Test in Task 2.
- POST to `?slug=redirect-response&go=archive` (e.g. a flag submission): must not redirect; flag submission still works. Test in Task 2.
- `record=0` / `record=-1`: "Record not found", status 200, no 500. Test in Task 3.

---

### Task 1: Registry categories and grouped catalog

**Files:**
- Modify: `includes/challenges.php` (docblock + `category` on all five definitions)
- Modify: `challenges.php` (replace hard-coded `<h2>HTTP Fundamentals</h2>` at line 31 with category groups)
- Test: `tests/regression.php`

**Interfaces:**
- Produces: every definition has `'category' => string`; docblock adds `category: string`.

- [ ] **Step 1: Write the failing tests** — after the catalog-progress check (line ~326) add:

```php
$catalog = get('/challenges.php', $a['cookie'])['body'];
check(array_filter($definitions, static fn ($d) => !is_string($d['category'] ?? null) || $d['category'] === '') === [], 'Every challenge declares a category');
check(substr_count($catalog, '<h2>HTTP Fundamentals</h2>') === 1, 'Catalog renders each category heading once');
```

Also replace the hard-coded counts so they follow the registry: line 324 `=== 5` → `=== count($definitions)`; lines 326, 336, 338 `'5 / 5'` / `'0 / 5'` → `count($definitions) . ' / ' . count($definitions)` and `'0 / ' . count($definitions)`.

- [ ] **Step 2: Run** `php tests/run_tests.php 2>&1 | grep -E '^FAIL|passed;'` — Expected: FAIL "Every challenge declares a category" (the heading check passes today; it guards Task 3's second group).

- [ ] **Step 3: Implement** — add `'category' => 'HTTP Fundamentals'` to the five definitions. In `challenges.php`, iterate definitions in order and open a new `<div class="section-heading"><h2>…</h2></div>` + `<div class="challenge-list">` whenever `category` changes; keep one running `$num` so numbering stays continuous.

- [ ] **Step 4: Run** the suite — Expected: `0 failed`.

- [ ] **Step 5: Commit** — `git add includes/challenges.php challenges.php tests/regression.php && git commit -m "feat: group challenge catalog by registry category" -- includes/challenges.php challenges.php tests/regression.php`

### Task 2: `redirect-response` challenge

**Files:**
- Modify: `includes/challenges.php` (new definition; `trigger` docblock; `challenge_dispatch_delivery()` branch)
- Modify: `challenge.php` (render `trigger` link and `arrival` note)
- Modify: `docs/architecture.md`, `README.md` (challenge list, new keys)
- Test: `tests/regression.php`

**Interfaces:**
- Consumes: `category` (Task 1); existing `redirect(string $path): void` (303 + exit), `request_string()`, `is_post()`.
- Produces: optional key `'trigger' => array{label: string, query: string, arrival?: string}`; template renders `<a href="/challenge.php?slug=<slug>&amp;<query>">label</a>` and, when `$_GET['arrived'] === '1'` and `arrival` is set, the `arrival` text. Task 3 reuses `trigger` without `arrival`.

- [ ] **Step 1: Write the failing tests**
  - Add `'redirect-response' => 'r3d1r3cts_t4k3_a_d3t0ur'` to `$releasedPhrases` and append `'redirect-response'` to the loop list at line 175.
  - In the `$responseA/$responseB` block (line ~236), use `&go=archive` for this slug.
  - Add a flag-extraction branch before the final `else` (line ~256):

```php
} elseif ($slug === 'redirect-response') {
    $flagA = $responseA['headers']['x-minihack-flag'] ?? '';
    $flagB = $responseB['headers']['x-minihack-flag'] ?? '';
    check($responseA['status'] === 303 && ($responseA['headers']['location'] ?? '') === '/challenge.php?slug=redirect-response&arrived=1', 'Archive link answers with a 303 to the arrival page');
    check($flagA !== '' && !str_contains($responseA['body'], $flagA) && str_contains($responseA['headers']['cache-control'] ?? '', 'no-store'), 'Redirect flag is only in the uncached 303 headers');
    $arrived = get($path . '&arrived=1', $a['cookie']);
    check(str_contains($arrived['body'], 'What happened on the way?') && !str_contains($arrived['body'], $flagA) && !isset($arrived['headers']['x-minihack-flag']), 'Arrival page shows no flag');
    foreach (['&go=elsewhere', '&go[]=archive'] as $query) {
        check(!isset(get($path . $query, $a['cookie'])['headers']['x-minihack-flag']), "Redirect requires go=archive exactly: $query");
    }
    check(post($path . '&go=archive', ['flag' => 'MHL{invalid}', 'csrf_token' => $a['csrf']], $a['cookie'])['status'] === 200, 'POST with go=archive is not redirected');
    check(str_contains($plain['body'], 'href="/challenge.php?slug=redirect-response&amp;go=archive"'), 'Challenge page links to the archive');
```

- [ ] **Step 2: Run** the suite — Expected: FAILs for "Released challenge slugs remain unchanged", the 303 checks and the link check.

- [ ] **Step 3: Implement**
  - Append the definition with the spec's table values, `'method' => 'GET'`, `'category' => 'HTTP Fundamentals'`, `'trigger' => ['label' => 'Open the archive', 'query' => 'go=archive', 'arrival' => 'You arrived. What happened on the way?']`, the spec's three hints verbatim, and learning content per the spec (why the flag never appeared; login redirects and Post/Redirect/Get; a redirect is an instruction to the client, not an access-control boundary) with the spec's three takeaways verbatim.
  - In `challenge_dispatch_delivery()` add: `elseif ($slug === 'redirect-response' && !is_post() && request_string($_GET, 'go') === 'archive') { header('X-MiniHack-Flag: ' . $flag); redirect('/challenge.php?slug=redirect-response&arrived=1'); }`
  - In `challenge.php`, below the investigation list, render the `trigger` link (query encoded with `e()`) and the `arrival` note.
  - Docs: README challenge topics; `docs/architecture.md` registry keys (`category`, `trigger`) and the redirect delivery.

- [ ] **Step 4: Run** the suite — Expected: `0 failed`.

- [ ] **Step 5: Commit** — `git add includes/challenges.php challenge.php tests/regression.php README.md docs/architecture.md && git commit -m "feat: add redirect-response challenge" -- includes/challenges.php challenge.php tests/regression.php README.md docs/architecture.md`

### Task 3: `object-reference` challenge

**Files:**
- Modify: `includes/challenges.php` (new definition with `records`; docblock)
- Modify: `challenge.php` (record card)
- Modify: `README.md` ("Security boundary"), `docs/architecture.md` (IDOR boundary)
- Test: `tests/regression.php`

**Interfaces:**
- Consumes: `category` (Task 1), `trigger` (Task 2), existing `positive_int(mixed): ?int`, `$expectedFlag` in `challenge.php`.
- Produces: optional key `'records' => array<int, array{owner: string, content: string, holds_flag: bool}>`. When the key exists and `record` is present in `$_GET`, the template renders the record (`Record #<id>`, owner, content, plus `e($expectedFlag)` when `holds_flag`) or the text `Record not found`.

- [ ] **Step 1: Write the failing tests**
  - Add `'object-reference' => 'ch3ck_wh0_0wns_1t'` to `$releasedPhrases`; append the slug to the loop list; use `&record=1041` for its `$responseA/$responseB`.
  - Add a branch before the final `else`:

```php
} elseif ($slug === 'object-reference') {
    preg_match('/(MHL\{[a-z0-9_]+_[a-f0-9]{12}\})/', $responseA['body'], $matchA);
    preg_match('/(MHL\{[a-z0-9_]+_[a-f0-9]{12}\})/', $responseB['body'], $matchB);
    $flagA = $matchA[1] ?? '';
    $flagB = $matchB[1] ?? '';
    check(str_contains($responseA['body'], 'lab-reviewer'), 'Record 1041 belongs to lab-reviewer');
    $own = get($path . '&record=1042', $a['cookie']);
    check($own['status'] === 200 && str_contains($own['body'], 'Record #1042') && !preg_match('/MHL\{[a-z0-9_]+_[a-f0-9]{12}\}/', $own['body']), 'Own record has no flag');
    foreach (['record=9999', 'record=abc', 'record=01041', 'record=0', 'record=-1', 'record[]=1041'] as $query) {
        $missing = get($path . '&' . $query, $a['cookie']);
        check($missing['status'] === 200 && str_contains($missing['body'], 'Record not found') && !preg_match('/MHL\{[a-z0-9_]+_[a-f0-9]{12}\}/', $missing['body']), "Unknown record is not found: $query");
    }
    check(str_contains($plain['body'], 'href="/challenge.php?slug=object-reference&amp;record=1042"'), 'Challenge page links to the learner record');
```

  - After the loop: `check(!str_contains(get('/challenge.php?slug=query-parameters&record=1041', $a['cookie'])['body'], challenge_flag($idA, 'object-reference')), 'Records exist only on the object-reference challenge');`
  - Extend Task 1's heading check: `substr_count($catalog, '<h2>Access Control</h2>') === 1`.

- [ ] **Step 2: Run** the suite — Expected: FAILs for released slugs, record checks and the Access Control heading.

- [ ] **Step 3: Implement**
  - Append the definition with the spec's table values, `'trigger' => ['label' => 'Your record', 'query' => 'record=1042']`, the three records from the spec (1040 archived/empty, 1041 `lab-reviewer` with `holds_flag => true`, 1042 `you`), the spec's hints verbatim, learning content per the spec (lookup used only `id`; OWASP Broken Access Control; UUIDs hide but do not authorize; contrast with `notes.php` session ownership and `AND user_id = :user_id`, inviting the learner to try it there), and the spec's three takeaways verbatim.
  - In `challenge.php`: `$recordId = positive_int($_GET['record'] ?? null);` and a record card shown only when `isset($challenge['records']) && array_key_exists('record', $_GET)`. Comment the lookup as the intentional, synthetic-only flaw.
  - Docs: README "Security boundary" and `docs/architecture.md` state that `object-reference` intentionally skips the ownership check over static synthetic records only.

- [ ] **Step 4: Run** the suite — Expected: `0 failed`.

- [ ] **Step 5: Commit** — `git add includes/challenges.php challenge.php tests/regression.php README.md docs/architecture.md && git commit -m "feat: add object-reference access-control challenge" -- includes/challenges.php challenge.php tests/regression.php README.md docs/architecture.md`

### Task 4: Browser verification and validation record

**Files:**
- Modify: `docs/validation.md`

- [ ] **Step 1: Run** `git ls-files -z '*.php' | xargs -0 -n1 php -l`, `php tests/run_tests.php`, `git diff --check` — Expected: no syntax errors, `0 failed`, no output.
- [ ] **Step 2: Browser check** — headless Firefox (scratchpad Marionette script, isolated `MINIHACK_DATA_DIR`): clicking **Open the archive** lands on `…&arrived=1` with the arrival note; **Your record** shows record 1042; changing to 1041 shows the flag; no horizontal overflow on `/challenges.php` and both challenge pages at 1440, 768 and 507 CSS px. Expected: all checks PASS.
- [ ] **Step 3: Update** `docs/validation.md` with the new test count and only the checks run in Steps 1–2; list the manual DevTools check (303 entry visible with *Preserve log*) under "Not tested".
- [ ] **Step 4: Commit** — `git add docs/validation.md && git commit -m "docs: record validation for redirect and IDOR challenges" -- docs/validation.md`
