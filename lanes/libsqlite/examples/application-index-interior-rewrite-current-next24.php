<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBTreeInteriorRedistributionPlan;
use PortLibs\LibSqlite\SQLiteIndexCell;
use PortLibs\LibSqlite\SQLiteIndexInteriorPage;
use PortLibs\LibSqlite\SQLiteRecord;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$payload = static fn (string $autoload, string $optionName, int $rowid): string => SQLiteRecord::encode([$autoload, $optionName, $rowid]);

$leftPage = SQLiteIndexInteriorPage::assemble([
    SQLiteIndexCell::encode($payload('no', '_transient_feed_a', 10), leftChildPage: 10),
], 11);
$rightPage = SQLiteIndexInteriorPage::assemble([
    SQLiteIndexCell::encode($payload('yes', 'active_plugins', 30), leftChildPage: 12),
    SQLiteIndexCell::encode($payload('yes', 'blog_public', 40), leftChildPage: 13),
    SQLiteIndexCell::encode($payload('yes', 'siteurl', 50), leftChildPage: 14),
], 15);

$plan = SQLiteBTreeInteriorRedistributionPlan::indexInterior(
    $leftPage,
    $rightPage,
    7,
    8,
    3,
    $payload('no', '_transient_feed_b', 20),
);

echo json_encode([
    'scenario' => 'application-index-interior-rewrite-current-next24',
    'action' => $plan->rebalanceAction()['action'],
    'leftChildPageNumbers' => $plan->leftChildPageNumbers,
    'rightChildPageNumbers' => $plan->rightChildPageNumbers,
    'movedChildPageNumbers' => $plan->movedChildPageNumbers,
    'oldParentDividerValues' => $plan->oldDividerValues,
    'newParentDividerValues' => $plan->newDividerValues,
    'pointerMapUpdatePages' => array_keys($plan->pointerMapUpdates),
    'updatedPageNumbers' => array_keys($plan->pageImages()),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
