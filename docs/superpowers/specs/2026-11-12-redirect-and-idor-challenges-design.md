# Redirect and IDOR challenges — design

Date: 2026-11-12
Status: approved in conversation; awaiting written-spec review

## Goal

Extend the challenge set from 5 to 7 with one bridge and one step up:

1. **`redirect-response`** — an HTTP fundamentals challenge: one click can cause several request/response exchanges, and the browser renders only the last.
2. **`object-reference`** — the first access-control challenge: a server that selects data by a client-supplied ID without an ownership check (IDOR), contrasted with the secure baseline's notes.

The second challenge is the first in which the learner *modifies* a request instead of only observing one.

## Constraints

- PHP 8.2+, SQLite, native HTML/CSS/JS; no new dependencies.
- No schema change, no new public route (`router.php` and `.htaccess` unchanged).
- Challenge mechanics must not weaken the secure baseline (README "Security boundary").
- Follow `docs/principles.md`: discovery first, reasoning over guessing, three progressive hints, learning content only after a recorded solve.
- Released slugs are immutable. The two slugs below become immutable once merged.

## Challenge 6: `redirect-response`

| Field | Value |
|---|---|
| slug | `redirect-response` |
| title | Lost in Transit |
| summary | Compare one click with the exchanges it actually caused. |
| category | HTTP Fundamentals |
| method | GET |
| phrase | `r3d1r3cts_t4k3_a_d3t0ur` |

**Mechanic**

- The page renders a plain link, **Open the archive**, to `/challenge.php?slug=redirect-response&go=archive`.
- For an authenticated GET with `go=archive`, the server sends `X-MiniHack-Flag: <flag>` and redirects with the existing `redirect()` helper (`303 See Other`) to `/challenge.php?slug=redirect-response&arrived=1`.
- The 303 body carries no flag. The destination page shows a short "You arrived. What happened on the way?" note and no flag.
- Any other `go` value renders the normal page with no flag header.

**Why a header, not the 303 body:** browser developer tools generally do not display bodies of redirect responses, which would force learners onto `curl` with a copied session cookie. Response headers of the redirect entry are visible in the Network panel.

**Hints**

- Direction: One click. How many responses?
- Concept: A 3xx status with a `Location` header makes the browser send a new request automatically; only the final response is rendered.
- Action: Enable *Preserve log* in the Network panel, open the archive again, and select the 303 entry.

**Learning content (post-solve):** why the flag never appeared on screen; redirects in login flows and Post/Redirect/Get; a redirect is an instruction to the client, not an access-control boundary (prepares the "execution after redirect" idea without implementing it). Takeaways:

- One navigation can contain several request/response exchanges.
- Inspect intermediate responses, not only the final page.
- A redirect tells the client where to go; it does not stop the server from sending data.

## Challenge 7: `object-reference`

| Field | Value |
|---|---|
| slug | `object-reference` |
| title | Someone Else's Record |
| summary | See whether the server checks who a record belongs to. |
| category | Access Control |
| method | GET |
| phrase | `ch3ck_wh0_0wns_1t` |

**Mechanic**

- The page renders a link, **Your record**, to `/challenge.php?slug=object-reference&record=1042`.
- `record` is parsed with the existing `positive_int()`. The server looks the record up **by ID only, with no ownership check** — the intentional flaw.
- Three synthetic records, defined as static data in the registry:

| ID | Owner shown | Content |
|---|---|---|
| 1040 | archived | Empty archived record. |
| 1041 | `lab-reviewer` | Review notes containing the requesting user's flag. |
| 1042 | you | The learner's own training record. |

- Any other or invalid value (`9999`, `abc`, array input) renders "Record not found" with status 200 and no error.
- IDs are sequential, so the learner reaches 1041 by reasoning from their own 1042, not by guessing.
- `lab-reviewer` contains a hyphen, which registration forbids, so it can never match a real account.

**Hints**

- Direction: The address names the record. Who decides whether you may see it?
- Concept: If a server uses a client-supplied identifier to select data without checking ownership, every reachable ID becomes readable.
- Action: Open your record, change the record number in the address to a neighbouring value, and compare the owner.

**Learning content (post-solve):** the lookup used only `id`; Broken Access Control (OWASP #1); unguessable IDs such as UUIDs hide objects but do not authorize access; contrast with `notes.php`, which takes ownership from the session and deletes with `AND user_id = :user_id` — invite the learner to try the same trick on notes and watch it fail. Takeaways:

- Every object request needs a server-side ownership check.
- Unguessable IDs hide objects; they do not authorize access.
- Ownership must come from the session, not the request.

## Integration

**Registry (`includes/challenges.php`)** — both challenges are appended after `request-method-body`, so existing order, slugs and phrases are unchanged and "Next challenge"/"Continue" follow automatically. New data keys:

- `category` (all challenges): `HTTP Fundamentals` for the first six, `Access Control` for `object-reference`.
- `trigger` (optional): `label` and `query` for the page's call-to-action link; used by `redirect-response` and `object-reference`.
- `records` (optional): the synthetic records, keyed by ID; used by `object-reference`.

**Delivery:** `challenge_dispatch_delivery()` gains one `redirect-response` branch (flag header + `redirect()`), alongside the existing header and cookie branches. No generic handler abstraction.

**Template (`challenge.php`):** renders the `trigger` link when present, the "arrived" note for `arrived=1`, and a record card when `records` is present and `record` is supplied. No new `if ($slug === ...)` conditionals; behaviour comes from registry keys.

**Catalog (`challenges.php`):** replaces the hard-coded "HTTP Fundamentals" heading with groups by `category` in registry order; numbering stays continuous (01–07) across groups.

**Unchanged:** database schema, `router.php`, `.htaccess`, `assets/js/app.js`, `learned.php`, Workbench (`index.php`).

## Security

- Both challenges run only inside `challenge.php` after `require_auth()`, with `Cache-Control: no-store`, including the 303 response.
- Flags remain per user (HMAC of `user_id:slug`); users A and B see different flags in record 1041.
- Records are static code data. They contain no real user data and never read notes, solves or users.
- The redirect trigger is a side-effect-free GET; it needs no CSRF token.
- The missing ownership check in `object-reference` is intentional and confined to synthetic data. This boundary is documented in the README "Security boundary" section and `docs/architecture.md`, so it is not mistaken for a baseline defect. (`SECURITY.md` is no longer tracked in the repository.)
- The existing test "Other user cannot read a note through client IDs" keeps guarding the baseline.

## Testing

Regression suite (`tests/regression.php`), test-first:

- `$releasedPhrases` includes both new slugs and phrases; existing ones unchanged.
- Per-challenge loop gains flag extraction for both challenges (303 header; `record=1041` body), so existing per-user, HMAC, CSRF, invalid-flag, idempotent-solve and post-solve gating checks cover them.
- `redirect-response`: `go=archive` → 303, correct `Location`, per-user flag header, no flag in body, `no-store`; `arrived=1` page has no flag; wrong `go` has no flag header; guest → login without flag.
- `object-reference`: 1042 shows own record without flag; 1041 shows the requesting user's flag and differs between users; 9999, `abc` and array input show "Record not found" without 500; guest → login.
- Catalog shows both category headings; progress checks use the registry count instead of a hard-coded `5`.

Browser (Firefox headless): trigger link performs the redirect and lands on the arrived page; record cards render. Manual by the project owner: the 303 entry and its headers are visible in Chrome or Firefox DevTools with *Preserve log*.

## Documentation

- `docs/architecture.md`: new registry keys, seven challenges, IDOR boundary.
- `README.md`: challenge topics and the IDOR boundary in "Security boundary".
- `docs/validation.md`: updated only with checks actually run.

## Out of scope

Execution-after-redirect, learning paths, difficulty labels, additional categories, generic challenge handlers, changes to the inspection-hint wording.
