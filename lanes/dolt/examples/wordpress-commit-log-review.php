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
    'latestReviewLog' => $table->logRows($fixture['commits'], [
        'headHash' => $fixture['headHash'],
        'showParents' => true,
        'decorate' => 'short',
        'number' => 1,
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
    'postTableLog' => $table->logRows($fixture['commits'], [
        'headHash' => $fixture['headHash'],
        'tableNames' => ['wp_posts'],
        'decorate' => 'short',
    ]),
    'postMetaTableLog' => $table->logRows($fixture['commits'], [
        'headHash' => $fixture['headHash'],
        'tableNames' => ['wp_postmeta'],
        'decorate' => 'short',
    ]),
    'mergeOnlyLog' => $table->logRows($fixture['commits'], [
        'headHash' => $fixture['headHash'],
        'merges' => true,
        'showParents' => true,
        'decorate' => 'short',
    ]),
    'checkpointLog' => $table->logRows($fixture['commits'], [
        'headHash' => $fixture['headHash'],
        'minParents' => 1,
        'decorate' => 'short',
    ]),
    'allBranchLog' => $table->logRows($fixture['commits'], [
        'headHash' => $fixture['headHash'],
        'includeAll' => true,
        'decorate' => 'short',
    ]),
    'allBranchPostTableLog' => $table->logRows($fixture['commits'], [
        'headHash' => $fixture['headHash'],
        'includeAll' => true,
        'tableNames' => ['wp_posts'],
        'decorate' => 'short',
    ]),
    'commits' => $table->commitsRows($fixture['commits']),
    'cliOnelineStat' => $table->renderLog($fixture['commits'], [
        'headHash' => $fixture['headHash'],
        'oneline' => true,
        'stat' => true,
        'decorate' => 'short',
        'diffStatsByCommit' => $fixture['diffStatsByCommit'],
    ]),
    'cliGraphOneline' => $table->renderLog($fixture['commits'], [
        'headHash' => $fixture['headHash'],
        'graph' => true,
        'oneline' => true,
        'decorate' => 'short',
    ]),
];
