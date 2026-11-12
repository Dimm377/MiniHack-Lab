# Architecture

MiniHack Web Lab v0.2 keeps one small PHP application boundary:

~~~text
Browser
  → allowlist router / Apache rules
  → public PHP entry point
  → session, CSRF, authorization, and input boundary
  → parameterized PDO query
  → encoded HTML or explicit JSON response
~~~

The secure application baseline and educational challenge mechanics share focused helpers but have separate responsibilities.

## Request and file boundaries

Root PHP entry points render pages; <code>api/users.php</code> returns JSON. <code>includes/</code> owns shared sessions, authentication, CSRF, rendering helpers, and the challenge registry. <code>config/</code> owns the SQLite path and connection. <code>scripts/init_db.php</code> is CLI-only. <code>assets/</code> contains native CSS and JavaScript. <code>docs/</code> contains maintained project documentation.

The PHP development <code>router.php</code> is a public-resource allowlist. It serves only known page/API entry points and a fixed list of assets: the stylesheet, the script and the five font files. Each asset carries its full <code>Content-Type</code>; text assets declare UTF-8, fonts are sent as <code>font/woff2</code>. Everything else returns a plain 404. The Apache <code>.htaccess</code> mirrors that public set and also disables indexes, MultiViews, and path-info routing.

## Data paths and initialization

<code>data_directory()</code> resolves to <code>database/</code> by default. An absolute, non-root <code>MINIHACK_DATA_DIR</code> overrides it for isolated processes. Both <code>database_path()</code> and <code>instance_secret_path()</code> derive from this one boundary, so a test cannot isolate one and accidentally reuse the other.

The initializer creates its selected directory with owner-only permissions, enables SQLite foreign keys, creates the schema idempotently, and applies <code>0600</code> to the database and secret. The secret uses exclusive file creation so concurrent initializers cannot overwrite an existing value.

The schema has three focused tables:

- <code>users</code>: unique username, password hash, and creation time
- <code>notes</code>: owner ID, title, content, and creation time
- <code>solves</code>: owner ID, challenge slug, and solve time, unique per user/slug

## Authentication and authorization

Registration validates public usernames and passwords at the server boundary, then hashes passwords. The 72-byte password cap matches the current bcrypt limit, and null bytes are rejected. Login performs an exact prepared lookup, uses a generic failure message, verifies the hash, regenerates the session ID, and rotates the CSRF token.

The session contains only the numeric user ID and username. Profile and note queries derive identity from this server-side session. Note listing filters by owner, and deletion requires both the note ID and authenticated owner ID. Client IDs may select a resource but never establish ownership.

Logout is POST-only, verifies CSRF, clears the session, expires its cookie, and destroys server-side state.

## Output and API

HTML entry points encode untrusted values with <code>htmlspecialchars()</code>. Stored note newlines are added only after encoding. Search escapes SQLite LIKE metacharacters and returns a maximum of 20 public results.

<code>GET /api/users.php?id=&lt;positive integer&gt;</code> returns only <code>id</code> and <code>username</code>. It returns JSON for 200, 400, 404, 405, and 500 outcomes, sets <code>Cache-Control: no-store</code>, and hides PDO errors, filesystem paths, and private fields.

Shared HTML responses send CSP, <code>X-Content-Type-Options: nosniff</code>, and <code>Referrer-Policy: same-origin</code>. Because <code>expose_php</code> is a <code>php.ini</code> setting, the router, shared page bootstrap, and API each remove <code>X-Powered-By</code> so responses do not disclose the PHP version. Exceptions are logged server-side and rendered as a generic 500 page.

## Typography and the HTTP trace

IBM Plex Sans (prose and controls) and JetBrains Mono (HTTP data, code and flags) are self-hosted as <code>.woff2</code> files in <code>assets/fonts/</code>, served through the same allowlist as the other assets, with their OFL licences beside them. Plex uses IBM's official Latin subset unmodified because its licence reserves the font name; JetBrains Mono is subset locally. Font sizes and spacing come from tokens in <code>:root</code> of <code>assets/css/style.css</code>; the colour palette is unchanged.

<code>includes/trace.php</code> renders request/response exchanges as an HTTP trace: a timeline of native <code>&lt;details&gt;</code> rows that expand into headers, fields and bodies, encoding every string itself. Values a challenge hides are passed as <code>trace_masked()</code> and render as a hint chip, never as the value. The "Server received" panel and the object-reference record card use it on the server; the Workbench inspector builds the same markup in <code>assets/js/app.js</code> with <code>textContent</code> only. A trace that would reveal an unsolved challenge's mechanism (such as the redirect hop) belongs only in post-solve content.

## Challenge registry and flags

<code>includes/challenges.php</code> is the code-defined challenge registry. A database catalog would add migration and query complexity without helping this small lab. Each slug-keyed definition contains a flag phrase, title, summary, HTTP method, category, objective, investigation instructions, three static hints (direction, concept, action), and static learning content (why it worked, real-world relevance, and takeaways). Interactive challenges also declare an <code>inspection</code> entry (form method and expected value); it drives both the inspection form and the unlock check, so those mechanics live in data rather than slug conditionals. An optional <code>trigger</code> entry (label, query and optional arrival text) renders the page's call-to-action link. The catalog groups challenges by <code>category</code> in registry order, and registry order is the learning order.

<code>challenge.php</code> renders hints as three nested native disclosures, so each level becomes available after the preceding one is opened. Its request line shows the actual request method and URI. The query and body challenges offer native inspection forms with a small set of candidate inputs. After any inspection attempt, a "Server received" panel echoes the actual method, the encoded query and body <code>inspect</code> values, and a locked or unlocked result; it never reveals the expected value. Learning content is emitted only when the existing <code>user_id</code>/<code>challenge_slug</code> query finds a solve, followed by a link to the next challenge in registry order (or to the recap after the last one). There is no hint persistence, JavaScript dependency, or additional schema. See [Learning principles](principles.md) for the educational contract.

<code>learned.php</code> is an authenticated, <code>no-store</code> recap of takeaways for the viewer's own solved challenges. It reuses the solve query, so takeaways of unsolved challenges never appear in it.

The <code>object-reference</code> challenge declares static <code>records</code> in the registry. <code>challenge.php</code> parses <code>record</code> with <code>positive_int()</code> and selects a record by that ID alone, without an ownership check: an intentional access-control flaw confined to synthetic code data. Record 1041 (owner <code>lab-reviewer</code>, a name registration cannot produce) shows the requesting user's flag; unknown or invalid IDs render "Record not found". The flaw never touches users, notes, or solves.

Released slugs are immutable. Renaming one requires an explicit migration of solve records and a decision about compatibility with flags derived from the old slug.

For a known slug and authenticated user, the application computes:

~~~text
MHL{ <challenge_phrase> _ <first 12 hex characters of HMAC-SHA256(user_id:slug, instance_secret)> }
~~~

Flags remain deterministically scoped to the authenticated user and challenge. The visible suffix is truncated to 48 bits, so its collision and brute-force properties are not identical to the previous 96-bit visible format.

The query challenge releases the flag only for the exact documented query value. The header challenge adds <code>X-MiniHack-Flag</code> to the document response. The redirect challenge answers <code>go=archive</code> with a <code>303 See Other</code> to an arrival page and carries <code>X-MiniHack-Flag</code> only on that redirect response, which the browser never renders. The source challenge places the flag in an HTML comment. Valid submissions use constant-time comparison and an idempotent insert. All challenge responses use <code>Cache-Control: no-store</code>.

## Test lifecycle

<code>tests/run_tests.php</code> creates a cryptographically unique temporary directory, sets <code>MINIHACK_DATA_DIR</code>, disables inherited built-in-server workers, and uses a separate session directory. Before initialization it asserts that both runtime paths resolve inside that test directory.

The runner initializes twice to test idempotency, starts one PHP process on an OS-selected loopback port, and polls until both the endpoint and its session file exist. It stops that exact process, closes SQLite, recursively removes only the fresh test directory, and verifies the normal developer-data fingerprint is unchanged. <code>tests/regression.php</code> exercises the application through real HTTP requests and uses the isolated database only for setup-independent assertions and safe failure injection.
