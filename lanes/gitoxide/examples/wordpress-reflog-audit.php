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

$forward = $store->reflogEntries($fixture['siteRef']);
$reverse = $store->reflogEntriesReverse($fixture['siteRef']);

return [
    'siteRef' => $fixture['siteRef'],
    'lineCount' => count($forward ?? []),
    'forwardMessages' => array_map(static fn ($entry): string => $entry->message, $forward ?? []),
    'reverseNewOids' => array_map(static fn ($entry): string => $entry->newOid, $reverse ?? []),
    'oldestPreviousOid' => $forward[0]->previousOid ?? null,
    'latestNewOid' => $reverse[0]->newOid ?? null,
    'trimmedCommitter' => ($forward[0]->signature->name ?? '') . ' <' . ($forward[0]->signature->email ?? '') . '>',
    'rawReflog' => $store->reflogContents($fixture['siteRef']),
    'wordpressUse' => $fixture['wordpressUse'],
];
