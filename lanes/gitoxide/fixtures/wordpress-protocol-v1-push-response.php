<?php

declare(strict_types=1);

$packet = static fn (string $payload): string => sprintf('%04x', strlen($payload) + 4) . $payload;
$flush = '0000';

$progress = [
    'Resolving deltas: 100% (3/3), completed with 1 local object.',
    'WordPress deployment refs updated.',
];

return [
    'refs' => [
        'refs/heads/main',
        'refs/tags/wp-release',
    ],
    'progress' => $progress,
    'response' => $packet("\x02{$progress[0]}\n")
        . $packet("\x01" . $packet("unpack ok\n"))
        . $packet("\x01" . $packet("ok refs/heads/main\n"))
        . $packet("\x01" . $packet("ok refs/tags/wp-release\n"))
        . $packet("\x02{$progress[1]}\n")
        . $packet("\x01" . $flush)
        . $flush,
];
