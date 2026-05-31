<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\CommitSignature;
use PortLibs\Gitoxide\ReferenceStore;
use PortLibs\Gitoxide\ReferenceTarget;

$fixture = require __DIR__ . '/../fixtures/wordpress-reflog-audit.php';
$dir = sys_get_temp_dir() . '/port-libs-wp-reflog-audit-' . bin2hex(random_bytes(4));
$store = new ReferenceStore($dir);

$committer = new CommitSignature(
    $fixture['committer']['name'],
    $fixture['committer']['email'],
    $fixture['committer']['time'],
);

$store->appendReflog(
    $fixture['siteRef'],
    ReferenceTarget::object($fixture['previousCommit']),
    ReferenceTarget::object($fixture['publishedCommit']),
    $committer,
    $fixture['messages'][0],
);
$store->appendReflog(
    $fixture['siteRef'],
    ReferenceTarget::object($fixture['publishedCommit']),
    ReferenceTarget::object($fixture['rolledBackCommit']),
    $committer,
    $fixture['messages'][1],
);
$symbolicUpdate = $store->updateWithReport(
    $fixture['symbolicSiteRef'],
    ReferenceTarget::symbolic($fixture['symbolicReferentRef']),
    ReferenceStore::PREVIOUS_EXISTING_MUST_MATCH,
    ReferenceTarget::object($fixture['publishedCommit']),
    false,
    'sha1',
    $committer,
    $fixture['symbolicMessage'],
);

$corruptLogPath = $dir . '/logs/' . $fixture['corruptSiteRef'];
$corruptLogDir = dirname($corruptLogPath);
if (!is_dir($corruptLogDir)) {
    mkdir($corruptLogDir, 0777, true);
}
file_put_contents(
    $corruptLogPath,
    $fixture['corruptLine'] . "\n" . (string) $store->reflogContents($fixture['siteRef']),
);

$forward = $store->reflogEntries($fixture['siteRef']);
$reverse = $store->reflogEntriesReverse($fixture['siteRef']);
$boundedReverse = $store->reflogEntryResultsReverseBounded($fixture['siteRef'], $fixture['boundedReverseBuffer']) ?? [];
$smallBufferDiagnostics = $store->reflogEntryResultsReverseBounded($fixture['siteRef'], $fixture['smallReverseBuffer']) ?? [];
$diagnostics = $store->reflogEntryResults($fixture['corruptSiteRef']) ?? [];

return [
    'siteRef' => $fixture['siteRef'],
    'lineCount' => count($forward ?? []),
    'forwardMessages' => array_map(static fn ($entry): string => $entry->message, $forward ?? []),
    'reverseNewOids' => array_map(static fn ($entry): string => $entry->newOid, $reverse ?? []),
    'boundedReverseMessages' => array_map(static fn (array $result): ?string => $result['entry']->message ?? null, $boundedReverse),
    'oldestPreviousOid' => $forward[0]->previousOid ?? null,
    'latestNewOid' => $reverse[0]->newOid ?? null,
    'trimmedCommitter' => ($forward[0]->signature->name ?? '') . ' <' . ($forward[0]->signature->email ?? '') . '>',
    'rawReflog' => $store->reflogContents($fixture['siteRef']),
    'symbolicRef' => $symbolicUpdate->reference->name,
    'symbolicTarget' => $symbolicUpdate->reference->target->value,
    'symbolicReferentExists' => $store->looseStore()->tryRead($fixture['symbolicReferentRef']) !== null,
    'symbolicReflogMessages' => array_map(static fn ($entry): string => $entry->message, $store->reflogEntries($fixture['symbolicSiteRef']) ?? []),
    'symbolicReflogPreviousOids' => array_map(static fn ($entry): string => $entry->previousOid, $store->reflogEntries($fixture['symbolicSiteRef']) ?? []),
    'symbolicReflogNewOids' => array_map(static fn ($entry): string => $entry->newOid, $store->reflogEntries($fixture['symbolicSiteRef']) ?? []),
    'smallBufferReverseDiagnostics' => array_map(
        static fn (array $result): array => [
            'ok' => $result['ok'],
            'line' => $result['line'],
            'fromEnd' => $result['fromEnd'],
            'bufferTooSmall' => $result['bufferTooSmall'] ?? false,
            'error' => $result['error'] ?? null,
        ],
        $smallBufferDiagnostics,
    ),
    'corruptLineDiagnostics' => array_map(
        static fn (array $result): array => [
            'ok' => $result['ok'],
            'line' => $result['line'],
            'error' => $result['error'] ?? null,
        ],
        $diagnostics,
    ),
    'wordpressUse' => $fixture['wordpressUse'],
];
