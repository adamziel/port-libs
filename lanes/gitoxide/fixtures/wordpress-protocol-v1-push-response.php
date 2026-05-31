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
$staleHookOld = str_repeat('1', 40);
$currentHookOld = str_repeat('2', 40);
$newHookObject = str_repeat('3', 64);
$siteAOld = str_repeat('6', 40);
$siteANew = str_repeat('7', 40);
$siteBOld = str_repeat('8', 40);
$siteBNew = str_repeat('9', 40);

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
    'compatibilityRef' => [
        'requested' => 'refs/for/wp-release',
        'actual' => 'refs/heads/deploy/wp-release',
        'message' => 'accepted by proc-receive',
        'oldObject' => $currentHookOld,
        'newObject' => $newHookObject,
    ],
    'compatibilityResponse' => $packet("unpack ok\n")
        . $packet("ok refs/for/wp-release accepted by proc-receive\n")
        . $packet("option refname refs/heads/stale-wp-release\n")
        . $packet("option refname refs/heads/deploy/wp-release\n")
        . $packet("option unknown-future-extension ignored\n")
        . $packet("option old-oid {$staleHookOld}\n")
        . $packet("option old-oid {$currentHookOld}\n")
        . $packet("option new-oid {$newHookObject}\n")
        . $packet("option forced-update true\n")
        . $packet("ng refs/heads/protected\n")
        . $flush,
    'expectedRefNames' => [
        'refs/heads/main',
        'refs/for/wp-release',
    ],
    'expectedFilteredRefs' => [
        'refs/heads/main',
        'refs/heads/deploy/wp-release',
    ],
    'expectedFilterResponse' => $packet("unpack ok\n")
        . $packet("ok refs/heads/ghost ignored by send-pack\n")
        . $packet("ng refs/heads/main stale lock\n")
        . $packet("ok refs/for/wp-release accepted by proc-receive\n")
        . $packet("option refname refs/heads/deploy/wp-release\n")
        . $packet("option old-oid {$currentHookOld}\n")
        . $packet("option new-oid {$newHookObject}\n")
        . $packet("ok refs/heads/main post-update hook accepted\n")
        . $flush,
    'multiReportRef' => [
        'requested' => 'refs/for/wp-deploy',
        'actual' => [
            'refs/heads/site-a',
            'refs/heads/site-b',
        ],
        'oldObjects' => [$siteAOld, $siteBOld],
        'newObjects' => [$siteANew, $siteBNew],
    ],
    'multiReportResponse' => $packet("unpack ok\n")
        . $packet("ok refs/for/wp-deploy\n")
        . $packet("option refname refs/heads/site-a\n")
        . $packet("option old-oid {$siteAOld}\n")
        . $packet("option new-oid {$siteANew}\n")
        . $packet("ok refs/for/wp-deploy\n")
        . $packet("option refname refs/heads/site-b\n")
        . $packet("option old-oid {$siteBOld}\n")
        . $packet("option new-oid {$siteBNew}\n")
        . $flush,
    'missingExpectedResponse' => $packet("unpack ok\n")
        . $packet("ok refs/heads/ghost ignored by send-pack\n")
        . $flush,
    'unrequestedOptionResponse' => $packet("unpack ok\n")
        . $packet("ok refs/heads/main\n")
        . $packet("ok refs/heads/ghost ignored by send-pack\n")
        . $packet("option refname refs/heads/other\n")
        . $flush,
    'oversizedReportStatus' => 'ffff' . str_repeat('x', 0xffff - 4),
    'fatalSidebandResponse' => $packet("\x03pre-receive hook declined deployment\n") . $flush,
    'carriageReturnStatusResponse' => $packet("unpack ok\n")
        . $packet("ok refs/heads/main\r\n")
        . $flush,
    'emptyPacketLineResponse' => '0004' . $flush,
];
