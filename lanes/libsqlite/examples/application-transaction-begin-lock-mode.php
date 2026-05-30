<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\LibSqlite\SQLitePragmaLockingMode;
use PortLibs\LibSqlite\SQLiteTransactionBeginLockPlan;

$lockingMode = new SQLitePragmaLockingMode();
$lockingMode->execute('PRAGMA locking_mode=exclusive');

$plan = SQLiteTransactionBeginLockPlan::plan(
    'BEGIN IMMEDIATE TRANSACTION',
    $lockingMode,
    'main',
    'wal'
);

echo json_encode([
    'status' => $plan['status'],
    'mode' => $plan['mode'],
    'locking_mode' => $plan['locking_mode'],
    'journal_mode' => $plan['journal_mode'],
    'lock_levels' => array_column($plan['lock_sequence'], 'level'),
    'application_path' => 'wp_options import transaction begin preflight',
    'requires_ext_sqlite' => false,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
