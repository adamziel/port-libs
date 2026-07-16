<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\BranchesTable;

$fixture = require dirname(__DIR__) . '/fixtures/wp-branch-review.php';
$table = new BranchesTable();
$branches = $table->rows($fixture['branches']);
$activity = $table->activityRows($fixture['branches'], $fixture['activity'], [
    'systemStartTime' => $fixture['systemStartTime'],
    'activeSessions' => $fixture['activeSessions'],
]);
$activityByBranch = array_column($activity, null, 'branch');

return [
    'activeBranch' => $table->activeBranch($fixture['branches'], $fixture['currentBranch']),
    'branches' => $branches,
    'activity' => $activity,
    'reviewQueue' => array_values(array_map(
        static fn (array $row): array => [
            'branch' => $row['name'],
            'hash' => $row['hash'],
            'active_sessions' => $activityByBranch[$row['name']]['active_sessions'] ?? 0,
            'dirty' => $row['dirty'],
            'latest_commit_message' => $row['latest_commit_message'],
        ],
        array_filter(
            $branches,
            static fn (array $row): bool => $row['dirty'] || (($activityByBranch[$row['name']]['active_sessions'] ?? 0) > 0)
        )
    )),
];
