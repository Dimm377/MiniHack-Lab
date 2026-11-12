# MiniHack Web Lab v0.2

MiniHack Web Lab is a small local PHP application for learning how browser requests, server controls, SQLite, and HTTP responses fit together.

~~~text
MiniHack Web Lab v0.2
├── secure application baseline
└── educational HTTP/security challenges
~~~

The baseline demonstrates defensive registration, authentication, authorization, private notes, public search, and a JSON API. The challenge area asks an authenticated user to recover per-user flags from query behavior, response headers, page source, cookies, request bodies, and redirects without weakening the baseline.

## Stack and support

- PHP 8.2 or newer with PDO SQLite
- SQLite
- Native HTML, CSS, and JavaScript
- Apache 2.4 or PHP's built-in development server
- No framework or Composer dependencies

CI runs the test suite on PHP 8.2 and 8.5.

## Run locally

Check the PHP extensions and initialize the normal runtime data:

~~~bash
php -v
php -m | grep -Ei 'PDO|sqlite'
php scripts/init_db.php
~~~

Initialization is idempotent. It creates <code>database/minihack.sqlite</code> and <code>database/instance_secret</code> only when needed, applies owner-only permissions, and preserves existing data and the challenge secret.

Start the local server through the included allowlist router:

~~~bash
php -S 127.0.0.1:8080 router.php
~~~

Open <code>http://127.0.0.1:8080</code>. Plain HTTP is suitable only for local learning. Session cookies are <code>HttpOnly</code> and <code>SameSite=Lax</code>; PHP adds <code>Secure</code> when the request uses HTTPS.

For Apache, point the document root at this repository, enable PHP and <code>mod_rewrite</code>, and permit the root <code>.htaccess</code> directives. A separate public document root remains the stronger deployment layout; this flat layout exists to keep the learning project easy to inspect.

## What to inspect

- Registration: validation, <code>password_hash()</code>, and duplicate usernames
- Login/logout: generic errors, session rotation, POST, and CSRF
- User search: literal LIKE matching with percent, underscore, quotes, and backslashes
- Notes: server-derived ownership and encoded stored content
- User API: explicit JSON status codes and public fields only
- Challenges: query parameters, response headers, page source, cookies, request methods and bodies, redirects, and per-user solves

Run the isolated regression suite:

~~~bash
php tests/run_tests.php
~~~

The runner creates a unique <code>/tmp/minihack-test-&lt;random&gt;/</code> data directory, passes it through <code>MINIHACK_DATA_DIR</code>, polls until its own PHP server is ready, stops only that process, and deletes only that temporary directory. It fingerprints the normal <code>database/</code> files and fails if they change.

<code>MINIHACK_DATA_DIR</code> is a small runtime path override used by tests. When unset, normal behavior remains <code>database/</code>. It must be an absolute, non-root path.

## Security boundary

The application is a secure baseline plus intentionally observable learning mechanics. Challenge flags are scoped to an authenticated user, derived from a local instance secret, and exposed only by each challenge's documented behavior. They are not credentials.

The `object-reference` challenge deliberately looks up a record by the ID in the URL without checking its owner, to teach insecure direct object references. That flaw applies only to three static, synthetic records defined in code. It never reads accounts, notes, or solve records, and the baseline's own ownership checks are unchanged and covered by regression tests.

Released challenge slugs are immutable. Renaming one requires an explicit migration because the slug identifies solve records and participates in flag derivation.

See [docs/architecture.md](docs/architecture.md) and [docs/principles.md](docs/principles.md).

## License

MIT. See [LICENSE](LICENSE).
