# Validation Report

Date: 2026-11-12
Local environment: Arch-based Linux (CachyOS), PHP 8.5.12, PDO SQLite, Firefox headless (Marionette)

This document distinguishes checks executed in this repository state from checks that remain environment-specific. Re-run them after future changes.

## Automated checks

The following commands passed locally:

~~~bash
git ls-files -z '*.php' | xargs -0 -n1 php -l
php -l learned.php
node --check assets/js/app.js
php tests/run_tests.php
git diff --check
node <design-taste-frontend>/scripts/lint.mjs <changed UI files> --app
~~~

The regression suite reported **695 passed; 0 failed**. It covered:

- isolated, idempotent database/secret initialization and <code>0600</code> permissions;
- developer runtime fingerprint unchanged before and after the complete suite;
- registration, duplicate usernames, password hashing, and invalid password boundaries;
- valid/invalid login, SQL injection attempts, generic errors, session and CSRF rotation, and invalidated pre-login sessions;
- missing, invalid, and valid CSRF for registration, login, note mutation, challenge submission, and logout, including non-mutation assertions;
- session-derived profile/note ownership, cross-user reads/deletes, and ignored client ownership IDs;
- reflected search/login encoding and stored note title/content encoding;
- literal quote, percent, underscore, and backslash search behavior;
- API 200/400/404/405/500, JSON content type, Allow, caching, and field filtering;
- challenge authentication, exact query behavior, document-response header, source comment, per-user/distinct flags, generic invalid submissions, idempotent solves, and isolated progress;
- challenge request lines that show the actual method and URI;
- the "Server received" panel: absent before an experiment, locked results without a flag, query versus body values for the POST challenge, and unlocked results;
- post-solve links to the next challenge, or to the recap after the last one;
- the authenticated, <code>no-store</code> recap: takeaways absent before a solve, present after it, and never shown for another account's progress;
- Workbench onboarding, secure-baseline section, no duplicated catalog, start/continue link, finished-learner recap link, and inspector labelling and live region markup;
- CSP, nosniff, referrer policy, challenge/API caching, and HTTPS/local cookie settings;
- no <code>X-Powered-By</code> header on pages, the API, static assets, router 404s, and 405 responses;
- redirect challenge: <code>go=archive</code> answers 303 to the arrival page with the per-user flag only in uncached redirect headers; the arrival page, other <code>go</code> values, array input, and POST requests carry no flag and are not redirected, and guest requests with <code>go=archive</code> or <code>record=1041</code> go to login without a flag;
- object-reference challenge: record 1042 has no flag, record 1041 shows the requesting user's flag, unknown, zero, negative, leading-zero, non-numeric, and array IDs render "Record not found" without errors, and records never appear on other challenges;
- every challenge declares a category, the catalog renders each category heading once, and progress counts follow the registry;
- private repository paths, hidden files, tests, patch/debug files, path-info, traversal/encoding variants, and instance-secret non-disclosure;
- generic HTML/API database failures and generic missing-secret failure;
- POST-only, CSRF-protected logout, cookie expiry, and destroyed session state.

The design lint reported 0 FAIL and 0 WARN. It scans the stylesheet and script only, not PHP templates.

## Browser checks

The regression suite cannot execute <code>assets/js/app.js</code>. Headless Firefox was driven over Marionette against a separate <code>MINIHACK_DATA_DIR</code> in a temporary directory:

- registration and login through the real forms;
- HTTP Inspector: <code>HTTP 200</code> in the Status cell, the full list of script-readable response headers, the JSON body, and an updated <code>HTTP 404</code> on a second request;
- no horizontal overflow (<code>scrollWidth ≤ innerWidth</code>) for the Workbench (guest and authenticated), catalog, locked and unlocked challenge pages, and recap at 1440, 768, and 507 CSS pixels;
- unlocked "Server received" panel rendered in the browser;
- redirect challenge: clicking **Open the archive** lands on the arrival page, which shows its note and no flag;
- object-reference challenge: **Your record** renders record 1042 without a flag, and record 1041 shows <code>lab-reviewer</code> and the flag;
- no horizontal overflow on the catalog, the redirect arrival page, and record 1041 at 1440, 768, and 500 CSS pixels.

Because the suite always goes through <code>router.php</code>, header removal was also checked against a PHP server started without the router (as Apache would serve the entry points). <code>/</code>, <code>/login.php</code>, and <code>/api/users.php</code> sent no <code>X-Powered-By</code>, while a control file on the same server still did.

## CI configuration

GitHub Actions uses least-privilege <code>contents: read</code>, runs syntax checks from tracked PHP files, and executes the same regression suite on PHP 8.2 and 8.5 with PDO SQLite. Both jobs passed for pull requests #2 and #3. They have not yet run for the redirect and object-reference challenges.

Repository settings cannot be changed from this checkout. Protect <code>main</code> and require the workflow's PHP test checks before merge.

## Not tested

- 390 CSS pixels and the <code>max-width: 480px</code> rules: headless Firefox on this machine would not size its viewport below 500 CSS pixels.
- Developer tools display: whether the 303 entry and its <code>X-MiniHack-Flag</code> header are visible in Chrome or Firefox DevTools with *Preserve log* was not checked.
- At about 500 CSS pixels the catalog progress badge wraps "0 / 7 Solved" onto several lines (observed, not fixed).
- Apache 2.4 was not started. The <code>.htaccess</code> allowlist, including the new <code>learned.php</code> entry, was reviewed, but its behavior depends on <code>mod_rewrite</code>, Apache configuration, and <code>AllowOverride</code>.
- Chromium, Safari, Windows, macOS, mobile Safari, and mobile Chrome were not run.
- Screen-reader output and physical touch/virtual-keyboard behavior were not tested.

No result outside the automated and browser checks above is claimed.
