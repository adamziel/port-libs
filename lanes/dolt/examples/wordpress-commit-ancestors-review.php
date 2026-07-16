<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\CommitAncestorsTable;
use PortLibs\Dolt\CommitLogTable;

$fixture = require dirname(__DIR__) . '/fixtures/wp-commit-ancestors-review.php';
$ancestors = (new CommitAncestorsTable())->rows($fixture['commits'], $fixture['headHash']);
$logRows = (new CommitLogTable())->logRows($fixture['commits'], [
    'headHash' => $fixture['headHash'],
    'showParents' => true,
]);
$messagesByHash = array_column($logRows, 'message', 'commit_hash');

return [
    'ancestors' => $ancestors,
    'parentMessages' => array_map(static fn (array $row): array => [
        'parent_index' => $row['parent_index'],
        'parent_hash' => $row['parent_hash'],
        'message' => $messagesByHash[$row['parent_hash']],
    ], $ancestors),
];
