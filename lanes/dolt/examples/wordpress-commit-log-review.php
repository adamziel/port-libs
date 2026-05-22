<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/tools/bootstrap.php';

use PortLibs\Dolt\CommitLogTable;

$fixture = require dirname(__DIR__) . '/fixtures/wp-commit-log-review.php';
$table = new CommitLogTable();

return [
    'log' => $table->logRows($fixture['commits'], [
        'headHash' => $fixture['headHash'],
        'showParents' => true,
        'decorate' => 'short',
    ]),
    'reviewRange' => $table->logRows($fixture['commits'], [
        'headHash' => $fixture['headHash'],
        'revisionSpecs' => ['wp-import-base..wp-merge-media'],
        'showParents' => true,
        'decorate' => 'short',
    ]),
    'mediaPromotionRange' => $table->logRows($fixture['commits'], [
        'headHash' => $fixture['headHash'],
        'revisionSpecs' => ['wp-review-main..wp-merge-media'],
        'showParents' => true,
        'decorate' => 'short',
    ]),
    'commits' => $table->commitsRows($fixture['commits']),
];
