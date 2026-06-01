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
$malformedOptionOld = str_repeat('c', 40);
$malformedOptionNew = str_repeat('d', 64);
$objectPrefixDiagnosticOld = str_repeat('e', 40);
$objectPrefixDiagnosticNew = str_repeat('f', 64);
$sha1ObjectPrefixOld = str_repeat('a', 40);
$sha1ObjectPrefixNew = str_repeat('b', 40);
$siteAOld = str_repeat('6', 40);
$siteANew = str_repeat('7', 40);
$siteBOld = str_repeat('8', 40);
$siteBNew = str_repeat('9', 40);
$zeroOid = str_repeat('0', 40);
$topicOld = str_repeat('a', 40);
$topicNew = str_repeat('b', 40);
$changeOld = str_repeat('c', 40);
$changeNew = str_repeat('d', 40);

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
        . $packet("option old-oid {$staleHookOld} stale hook diagnostic\n")
        . $packet("option old-oid {$currentHookOld}\r\n")
        . $packet("option new-oid {$newHookObject} accepted by deployment hook\n")
        . $packet("option forced-update true\n")
        . $packet("ng refs/heads/protected\n")
        . $flush,
    'emptyRejectionRef' => [
        'requested' => 'refs/heads/wp-preview',
        'message' => '',
    ],
    'emptyRejectionResponse' => $packet("unpack ok\n")
        . $packet("ng refs/heads/wp-preview \n")
        . $flush,
    'emptyUnpackStatusRef' => [
        'requested' => 'refs/heads/wp-preview',
        'message' => 'index-pack died before hook output',
    ],
    'emptyUnpackStatusResponse' => $packet("unpack \n")
        . $packet("ng refs/heads/wp-preview index-pack died before hook output\n")
        . $flush,
    'valuelessOptionRef' => [
        'requested' => 'refs/for/wp-release',
        'message' => 'accepted without rewrite details',
    ],
    'valuelessOptionResponse' => $packet("unpack ok\n")
        . $packet("ok refs/for/wp-release accepted without rewrite details\n")
        . $packet("option refname\n")
        . $packet("option old-oid\n")
        . $packet("option new-oid \n")
        . $packet("option unknown-future-extension\n")
        . $flush,
    'malformedObjectOptionRef' => [
        'requested' => 'refs/for/wp-release',
        'oldObject' => $malformedOptionOld,
        'newObject' => $malformedOptionNew,
    ],
    'malformedObjectOptionResponse' => $packet("unpack ok\n")
        . $packet("ok refs/for/wp-release accepted after object diagnostics\n")
        . $packet("option old-oid {$malformedOptionOld}\n")
        . $packet("option new-oid not-a-hex-object deployment hook diagnostic\n")
        . $packet('option old-oid ' . str_repeat('f', 63) . "\n")
        . $packet("option new-oid {$malformedOptionNew} accepted by deployment hook\n")
        . $flush,
    'objectPrefixDiagnosticRef' => [
        'requested' => 'refs/for/wp-release',
        'oldObject' => $objectPrefixDiagnosticOld,
        'newObject' => $objectPrefixDiagnosticNew,
    ],
    'objectPrefixDiagnosticResponse' => $packet("unpack ok\n")
        . $packet("ok refs/for/wp-release accepted after hook suffix diagnostics\n")
        . $packet("option old-oid {$objectPrefixDiagnosticOld}#pre-receive-suffix\n")
        . $packet("option new-oid {$objectPrefixDiagnosticNew}:accepted-by-hook\n")
        . $flush,
    'sha1ObjectPrefixRef' => [
        'requested' => 'refs/for/wp-release',
        'oldObject' => $sha1ObjectPrefixOld,
        'newObject' => $sha1ObjectPrefixNew,
    ],
    'sha1ObjectPrefixResponse' => $packet("unpack ok\n")
        . $packet("ok refs/for/wp-release accepted with sha1 status prefix\n")
        . $packet("option old-oid {$sha1ObjectPrefixOld}feed\n")
        . $packet("option new-oid {$sha1ObjectPrefixNew}cafe\n")
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
    'noRefnameMultiReportRef' => [
        'requested' => 'refs/for/main/topic',
        'actual' => [
            'refs/for/main/topic',
            'refs/changes/24/124/1',
            'refs/changes/25/125/1',
        ],
        'oldObjects' => [$topicOld, $zeroOid, $changeOld],
        'newObjects' => [$topicNew, $changeOld, $changeNew],
    ],
    'noRefnameMultiReportResponse' => $packet("unpack ok\n")
        . $packet("ok refs/for/main/topic\n")
        . $packet("option old-oid {$topicOld}\n")
        . $packet("option new-oid {$topicNew}\n")
        . $packet("ok refs/for/main/topic\n")
        . $packet("option refname refs/changes/24/124/1\n")
        . $packet("option old-oid {$zeroOid}\n")
        . $packet("option new-oid {$changeOld}\n")
        . $packet("ok refs/for/main/topic\n")
        . $packet("option refname refs/changes/25/125/1\n")
        . $packet("option old-oid {$changeOld}\n")
        . $packet("option new-oid {$changeNew}\n")
        . $packet("option forced-update\n")
        . $flush,
    'rejectedReportRef' => [
        'requested' => 'refs/for/wp-deploy',
        'actual' => [
            'refs/heads/site-a',
            'refs/heads/site-b',
        ],
        'message' => 'post-receive hook declined',
    ],
    'rejectedReportResponse' => $packet("unpack ok\n")
        . $packet("ok refs/for/wp-deploy accepted by proc-receive\n")
        . $packet("option refname refs/heads/site-a\n")
        . $packet("option old-oid {$siteAOld}\n")
        . $packet("option new-oid {$siteANew}\n")
        . $packet("ok refs/for/wp-deploy accepted by proc-receive\n")
        . $packet("option refname refs/heads/site-b\n")
        . $packet("option old-oid {$siteBOld}\n")
        . $packet("option new-oid {$siteBNew}\n")
        . $packet("ng refs/for/wp-deploy post-receive hook declined\n")
        . $flush,
    'missingExpectedResponse' => $packet("unpack ok\n")
        . $packet("ok refs/heads/ghost ignored by send-pack\n")
        . $flush,
    'unpackOnlyExpectedRefs' => [
        'refs/heads/main',
        'refs/tags/wp-release',
    ],
    'unpackOnlyResponse' => $packet("unpack ok\n")
        . $flush,
    'unrequestedOptionResponse' => $packet("unpack ok\n")
        . $packet("ok refs/heads/main\n")
        . $packet("ok refs/heads/ghost ignored by send-pack\n")
        . $packet("option refname refs/heads/other\n")
        . $flush,
    'oversizedReportStatus' => 'ffff' . str_repeat('x', 0xffff - 4),
    'fatalSidebandResponse' => $packet("\x03pre-receive hook declined deployment\n") . $flush,
    'fatalAfterStatusResponse' => $packet("\x01" . $packet("unpack ok\n"))
        . $packet("\x01" . $packet("ok refs/heads/main\n"))
        . $packet("\x03pre-receive hook declined after deployment status\n")
        . $packet("\x01" . $flush)
        . $flush,
    'emptyErrorSidebandResponse' => $packet("\x03")
        . $packet("\x01" . $packet("unpack ok\n"))
        . $packet("\x01" . $packet("ok refs/heads/main\n"))
        . $packet("\x01" . $flush)
        . $flush,
    'emptyProgressSidebandResponse' => $packet("\x02")
        . $packet("\x02remote: WordPress deployment accepted\n")
        . $packet("\x01" . $packet("unpack ok\n"))
        . $packet("\x02")
        . $packet("\x01" . $packet("ok refs/heads/main\n"))
        . $packet("\x01" . $flush)
        . $flush,
    'responseEndTerminatedResponse' => $packet("unpack ok\n")
        . $packet("ok refs/heads/wp-release\n")
        . '0002',
    'delimiterTerminatedResponse' => $packet("unpack ok\n")
        . $packet("ok refs/heads/wp-preview deployed by hook\n")
        . '0001',
    'carriageReturnStatusResponse' => $packet("unpack ok\n")
        . $packet("ok refs/heads/main\r\n")
        . $flush,
    'emptyPacketLineResponse' => '0004' . $flush,
];
