<?php

declare(strict_types=1);

require_once __DIR__ . '/escape.php';

/** A value the current challenge hides: rendered as a hint chip, never as the value itself. */
final class TraceMasked
{
    public function __construct(public readonly string $hint)
    {
    }
}

function trace_masked(string $hint): TraceMasked
{
    return new TraceMasked($hint);
}

/**
 * Renders request/response exchanges as an expandable timeline. Every string is encoded here;
 * callers never pass HTML.
 *
 * @param list<array{
 *     direction: 'request'|'response',
 *     summary: array{0: string|int, 1: string},
 *     note: ?string,
 *     sections: array<string, array<string|int, string|TraceMasked>>,
 *     open: bool
 * }> $exchanges
 */
function http_trace(string $label, array $exchanges): string
{
    $html = '<section class="http-trace" aria-label="' . e($label) . '"><p class="http-trace__label">' . e($label) . '</p><ol>';
    foreach ($exchanges as $exchange) {
        $isRequest = $exchange['direction'] === 'request';
        $html .= '<li class="http-trace__row is-' . ($isRequest ? 'request' : 'response') . '">'
            . '<details' . ($exchange['open'] ? ' open' : '') . '><summary>'
            . '<span class="http-trace__dir" aria-hidden="true">' . ($isRequest ? '→' : '←') . '</span>'
            . '<span class="visually-hidden">' . ($isRequest ? 'Request: ' : 'Response: ') . '</span>'
            . '<b>' . e($exchange['summary'][0]) . '</b> ' . e($exchange['summary'][1])
            . ($exchange['note'] !== null ? '<span class="http-trace__note"> · ' . e($exchange['note']) . '</span>' : '')
            . '</summary><div class="http-trace__detail">';
        foreach ($exchange['sections'] as $title => $entries) {
            $html .= '<h4>' . e($title) . '</h4><dl>';
            foreach ($entries as $name => $value) {
                $html .= '<dt>' . e($name) . '</dt><dd>'
                    . ($value instanceof TraceMasked ? '<span class="http-trace__masked">' . e($value->hint) . '</span>' : e($value))
                    . '</dd>';
            }
            $html .= '</dl>';
        }
        $html .= '</div></details></li>';
    }
    return $html . '</ol></section>';
}
