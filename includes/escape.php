<?php

declare(strict_types=1);

// Side-effect free so CLI code (tests, helpers) can encode without starting a session.
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
