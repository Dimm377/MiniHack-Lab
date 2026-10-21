<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/database.php';

/**
 * Released challenge slugs are immutable. Renames require an explicit migration
 * of solve records and consideration of flags derived from the old slug.
 *
 * @return array<string, array{
 *     phrase: string,
 *     title: string,
 *     summary: string,
 *     method: string,
 *     objective: string,
 *     instructions: list<string>,
 *     hints: array{direction: string, concept: string, action: string},
 *     learning: array{
 *         why_it_worked: string,
 *         real_world_relevance: string,
 *         takeaways: list<string>
 *     }
 * }>
 */
function challenge_definitions(): array
{
    return [
        'query-parameters' => [
            'phrase' => 'qu3ry_p4r4m3t3rs_m4tt3r',
            'title' => 'What\'s in the URL?',
            'summary' => 'Compare how the same page responds to different inputs.',
            'method' => 'GET',
            'objective' => 'This page accepts an inspection target. Find an input that changes its response and recover the training flag.',
            'instructions' => [
                'Use the inspection form below. Before sending, predict what will change.',
                'Compare the address and returned page between attempts. Change one thing at a time.',
            ],
            'hints' => [
                'direction' => 'Compare two attempts: what changed on the way to the server, and what changed on the way back?',
                'concept' => 'A GET form puts named values in the URL query string. The server can read those values when building its response.',
                'action' => 'Try a different inspection value while keeping the rest of the request unchanged, then compare the response.',
            ],
            'learning' => [
                'why_it_worked' => 'The GET form sent its inspect field in the URL query string. This challenge reads that field and returns the training flag only when its value is exactly request. Other values leave the output locked. The changed response came from a server-side condition, not from the browser changing the page on its own.',
                'real_world_relevance' => 'Query parameters routinely control searches, filters, and pagination; they are not vulnerabilities. Comparing responses while changing one parameter helps you understand an application during debugging or security testing. A flaw appears if a server trusts a client-supplied value for a sensitive decision, such as granting access to another account. Authorization must use the authenticated identity and server-side checks.',
                'takeaways' => [
                    'Treat the URL as input to the application.',
                    'Change one value at a time and compare responses.',
                    'Client-controlled input must not establish authorization.',
                ],
            ],
        ],
        'response-headers' => [
            'phrase' => 'r34d_th3_h34d3rs',
            'title' => 'Check the Fine Print',
            'summary' => 'Compare a page visit with the exchange that delivered it.',
            'method' => 'GET',
            'objective' => 'Loading this challenge returns a training flag to your browser. Recover it by investigating the exchange for this page.',
            'instructions' => [
                'Reload the page while observing its request in browser developer tools.',
                'Compare what the browser received with what you can see on the page.',
            ],
            'hints' => [
                'direction' => 'What else can you inspect about this page visit beyond the visible result?',
                'concept' => 'An HTTP response includes a status, headers, and a body. The browser does not render all of them as page content.',
                'action' => 'In the Network panel, reload and select this challenge document. Examine its response headers, including custom fields.',
            ],
            'learning' => [
                'why_it_worked' => 'For a normal page visit, this challenge adds X-MiniHack-Flag to the HTTP response headers. That field travels alongside the HTML body, so the browser receives the flag without displaying it as page text. Inspecting the document response exposes the value; inspecting a stylesheet request would show a different response.',
                'real_world_relevance' => 'Response headers are ordinary HTTP metadata used for content types, caching, redirects, and security policies. Inspecting them helps diagnose browser behavior and assess application configuration. A custom header is not itself a vulnerability. Sending confidential data in one can disclose it to the recipient, just as sending it in the body can; lack of visible page text provides no protection.',
                'takeaways' => [
                    'Inspect the complete response, including its status and headers.',
                    'Match your evidence to the correct request.',
                    'Data sent to a client is accessible to that client.',
                ],
            ],
        ],
        'page-source' => [
            'phrase' => 'v13w_s0urc3_n3v3r_l13s',
            'title' => 'Behind the Curtain',
            'summary' => 'Compare what a browser receives with what it displays.',
            'method' => 'GET',
            'objective' => 'This page arrives with a training flag, but the visible content is only part of the evidence. Recover the flag from what your browser received.',
            'instructions' => [
                'Load the challenge and compare the delivered document with the visible page.',
                'Look for differences you can explain through browser behavior.',
            ],
            'hints' => [
                'direction' => 'Does everything delivered to a browser have to become visible page text?',
                'concept' => 'HTML is interpreted before it is displayed. Comments remain in the delivered document but are not rendered as text.',
                'action' => 'Open View Page Source or the document response in the Network panel. Look for an HTML comment associated with this challenge.',
            ],
            'learning' => [
                'why_it_worked' => 'The server placed the training flag in an HTML comment in this challenge response. The browser received those bytes but skipped the comment when drawing the page. Reading the delivered HTML revealed content that rendering had left out. This exposes the generated HTML, not the PHP source that ran on the server.',
                'real_world_relevance' => 'Comparing response HTML with the rendered page helps debug missing content and distinguish server output from later browser changes. During an application assessment, source inspection can also reveal data unintentionally shipped to clients. HTML comments are normal markup, not vulnerabilities. Placing confidential information in comments or hidden elements can cause disclosure because visual hiding does not enforce access control.',
                'takeaways' => [
                    'The rendered page is one representation of the response.',
                    'Response HTML and the live DOM can differ.',
                    'Keep confidential data out of responses to unauthorized clients.',
                ],
            ],
        ],
        'cookie-state' => [
            'phrase' => 'c00k13s_r3m3mb3r',
            'title' => 'Crumbs Left Behind',
            'summary' => 'Observe what a page visit leaves in the browser.',
            'method' => 'GET',
            'objective' => 'Visiting this challenge leaves training state in your browser. Find what the visit leaves behind and recover the training flag.',
            'instructions' => [
                'Observe the browser state for this site, then load the challenge again.',
                'Compare the response with what the browser retains for later requests.',
            ],
            'hints' => [
                'direction' => 'The page looks the same after a reload. What could the browser be carrying between visits?',
                'concept' => 'A Set-Cookie response header asks the browser to store a value. Matching later requests can send it back in the Cookie header.',
                'action' => 'Inspect this site in the browser cookie storage panel after loading the challenge. Look at the minihack_training value and compare it with the response Set-Cookie field.',
            ],
            'learning' => [
                'why_it_worked' => 'This challenge sends the training flag in a cookie named minihack_training. The browser stores it and can send it back on matching requests to /challenge.php. The server sets the value again on later visits, so it need not change on reload. Reading stored state revealed what the visible page did not show. This training cookie is separate from the login session cookie.',
                'real_world_relevance' => 'Cookies are an ordinary mechanism for preferences and sessions. Inspecting their values, paths, and attributes helps debug state and assess session handling. HttpOnly limits script access; Secure restricts transmission to HTTPS; neither replaces server authorization. A vulnerability can arise when a server accepts an editable cookie as proof of a role or ownership. The training cookie here grants no account privileges.',
                'takeaways' => [
                    'Compare Set-Cookie responses with later Cookie requests.',
                    'Cookie scope and attributes affect browser behavior.',
                    'Stored client state is not proof of permission.',
                ],
            ],
        ],
        'request-method-body' => [
            'phrase' => 'us3_th3_r1ght_m3th0d',
            'title' => 'Say It Properly',
            'summary' => 'Compare a page visit with data sent from a form.',
            'method' => 'POST',
            'objective' => 'This challenge accepts inspection attempts as well as page visits. Find an attempt that changes the server response and recover the training flag.',
            'instructions' => [
                'Try the inspection form and compare its request with a normal page visit.',
                'Predict where the input travels, then inspect what was sent and returned.',
            ],
            'hints' => [
                'direction' => 'Two requests to the same address need not ask the server to do the same thing.',
                'concept' => 'A POST form sends named values in the request body. The server can check the method and body separately from the URL query string.',
                'action' => 'Try sending inspect=body in the request body, then compare that POST request with the same field and value placed in the URL query string.',
            ],
            'learning' => [
                'why_it_worked' => 'The inspection form sent a POST with form-urlencoded inspect=body in the request body. This challenge checks both the POST method and that exact parsed body value before returning the training flag. Putting the same value in a GET query string does not satisfy that condition. The exploration returns a response without recording a solve; a valid flag submission records it separately.',
                'real_world_relevance' => 'Methods and bodies are normal parts of HTTP, not vulnerabilities. A URL alone does not describe a complete request: debugging and security testing often require comparing methods, content types, and submitted fields. POST does not encrypt data or establish permission. Real applications still need HTTPS, input validation, authentication, authorization, and appropriate CSRF protection for state-changing operations.',
                'takeaways' => [
                    'Compare the method and body as well as the URL.',
                    'Query fields and body fields are different inputs.',
                    'Using POST does not make a request trusted or confidential.',
                ],
            ],
        ],
    ];
}

/** @return array|null A definition from challenge_definitions(), or null for an unknown slug. */
function challenge_definition(string $slug): ?array
{
    $definitions = challenge_definitions();
    return $definitions[$slug] ?? null;
}

function instance_secret_path(): string
{
    return data_directory() . '/instance_secret';
}

function load_instance_secret(): string
{
    $encodedSecret = @file_get_contents(instance_secret_path());
    if (!is_string($encodedSecret)) {
        throw new RuntimeException('The challenge instance secret has not been initialized.');
    }

    $encodedSecret = trim($encodedSecret);
    if (preg_match('/\A[0-9a-f]{64}\z/', $encodedSecret) !== 1) {
        throw new RuntimeException('The challenge instance secret is invalid.');
    }

    $secret = hex2bin($encodedSecret);
    if (!is_string($secret)) {
        throw new RuntimeException('The challenge instance secret could not be decoded.');
    }

    return $secret;
}

function challenge_flag(int $userId, string $slug): string
{
    $definition = challenge_definition($slug);
    if ($userId < 1 || $definition === null) {
        throw new InvalidArgumentException('Cannot generate a flag for an unknown challenge or user.');
    }

    $digest = hash_hmac('sha256', $userId . ':' . $slug, load_instance_secret());
    return 'MHL{' . $definition['phrase'] . '_' . substr($digest, 0, 12) . '}';
}

/**
 * Protocol delivery hook for challenges conveying flags via HTTP response mechanics.
 */
function challenge_dispatch_delivery(string $slug, string $flag): void
{
    if ($slug === 'response-headers' && !is_post()) {
        header('X-MiniHack-Flag: ' . $flag);
    } elseif ($slug === 'cookie-state') {
        $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== '' && $_SERVER['HTTPS'] !== 'off';
        setcookie('minihack_training', $flag, [
            'expires' => 0,
            'path' => '/challenge.php',
            'secure' => $isHttps,
            'httponly' => false,
            'samesite' => 'Lax'
        ]);
    }
}

/**
 * Evaluates whether the request satisfies the dynamic unlock criteria for an interactive challenge.
 */
function challenge_is_unlocked(string $slug): bool
{
    return match ($slug) {
        'query-parameters' => request_string($_GET, 'inspect') === 'request',
        'request-method-body' => is_post() && request_string($_POST, 'inspect') === 'body',
        default => false,
    };
}

/**
 * Checks whether this challenge provides an inspection workbench form.
 */
function challenge_has_inspection_form(string $slug): bool
{
    return in_array($slug, ['query-parameters', 'request-method-body'], true);
}

/**
 * Returns the HTML comment flag for source inspection challenges.
 */
function challenge_source_comment(string $slug, string $flag): ?string
{
    if ($slug === 'page-source') {
        return '<!-- MiniHack challenge flag: ' . e($flag) . ' -->';
    }
    return null;
}
