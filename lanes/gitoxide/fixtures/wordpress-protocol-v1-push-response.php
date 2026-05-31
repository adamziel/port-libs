<?php

declare(strict_types=1);

$packet = static fn (string $payload): string => sprintf('%04x', strlen($payload) + 4) . $payload;
$flush = '0000';

$progress = [
    'Resolving deltas: 100% (3/3), completed with 1 local object.',
    'WordPress deployment refs updated.',
];
$oldSha256 = str_repeat('a', 64);
$newSha256 = str_repeat('b', 64);

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
    'rewrittenRef' => [
        'requested' => 'refs/for/wp-release',
        'actual' => 'refs/heads/deploy/wp-release',
        'oldObject' => $oldSha256,
        'newObject' => $newSha256,
    ],
    'rewrittenResponse' => $packet("unpack ok\n")
        . $packet("ok refs/for/wp-release\n")
        . $packet("option refname refs/heads/deploy/wp-release\n")
        . $packet("option old-oid {$oldSha256}\n")
        . $packet("option new-oid {$newSha256}\n")
        . $packet("option forced-update\n")
        . $flush,
    'fallThroughRef' => [
        'requested' => 'refs/for/wp-maintenance',
    ],
    'fallThroughResponse' => $packet("unpack ok\n")
        . $packet("ok refs/for/wp-maintenance\n")
        . $packet("option fall-through\n")
        . $flush,
    'oversizedReportStatus' => 'ffff' . str_repeat('x', 0xffff - 4),
    'fatalSidebandResponse' => $packet("\x03pre-receive hook declined deployment\n") . $flush,
    'carriageReturnStatusResponse' => $packet("unpack ok\n")
        . $packet("ok refs/heads/main\r\n")
        . $flush,
    'emptyPacketLineResponse' => '0004' . $flush,
];
