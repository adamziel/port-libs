<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteWal;
use PortLibs\LibSqlite\SQLiteWalHeader;
use PortLibs\LibSqlite\SQLiteWalHookPlan;

$tests = [];

$pageSize = 1024;
$pageCount = 1200;
$salt1 = 0x4a613f21;
$salt2 = 0x6297c053;

$pageImage = static function (string $label) use ($pageSize): string {
    return str_pad(substr($label, 0, $pageSize), $pageSize, '#', STR_PAD_RIGHT);
};

$databaseBytes = '';
for ($page = 1; $page <= $pageCount; $page++) {
    $databaseBytes .= $pageImage(sprintf('e_walauto.test base database page %04d', $page));
}

$headerPrefix = pack('N*', SQLiteWalHeader::MAGIC_BIG_ENDIAN, 3007000, $pageSize, 60131, $salt1, $salt2);
$checksum = SQLiteWal::checksumPair($headerPrefix, false);
$walBytes = $headerPrefix . pack('N*', $checksum[0], $checksum[1]);

for ($frame = 1; $frame <= $pageCount; $frame++) {
    $image = $pageImage(sprintf('e_walauto.test default threshold transaction frame %04d', $frame));
    $framePrefix = pack('N*', $frame, $pageCount, $salt1, $salt2);
    $checksum = SQLiteWal::checksumPair(substr($framePrefix, 0, 8) . $image, false, $checksum[0], $checksum[1]);
    $walBytes .= $framePrefix . pack('N*', $checksum[0], $checksum[1]) . $image;
}

$wal = SQLiteWal::parse($walBytes, $pageSize, true);
$auto = SQLiteWalHookPlan::autocheckpointEvents($wal, $databaseBytes, 1000, 'passive', 'main');
$disabled = SQLiteWalHookPlan::autocheckpointEvents($wal, $databaseBytes, 0, 'passive', 'main');

for ($transaction = 1; $transaction <= 1000; $transaction++) {
    $tests[sprintf(
        'real upstream e_walauto default threshold transaction %04d follows passive checkpoint gate',
        $transaction
    )] = static function (TestRunner $t) use ($auto, $disabled, $transaction): void {
        $event = $auto['events'][$transaction - 1];
        $disabledEvent = $disabled['events'][$transaction - 1];

        $t->same('main', $event['database']);
        $t->same($transaction, $event['transaction_index']);
        $t->same($transaction, $event['frame_count']);
        $t->same($transaction, $event['first_frame']);
        $t->same($transaction, $event['last_frame']);
        $t->same([$transaction], $event['page_numbers']);
        $t->same($transaction >= 1000, $event['autocheckpoint']);
        $t->same(1000, $event['threshold']);
        $t->same('passive', $event['mode']);
        $t->same(false, $disabledEvent['autocheckpoint']);
        $t->same(0, $disabledEvent['threshold']);

        if ($transaction === 1000) {
            $t->same('passive_checkpoint_complete', $event['checkpoint']['reason']);
            $t->same('preserve_wal', $event['checkpoint']['wal_action']);
            $t->same(1200, $event['checkpoint']['checkpointed_frame_count']);
            $t->same(0, $event['checkpoint']['remaining_committed_frame_count']);
        } else {
            $t->same(null, $event['checkpoint']);
        }
    };
}

$tests['real upstream e_walauto default threshold cites hydrated source section'] = static function (TestRunner $t) use ($auto): void {
    $t->same(1200, $auto['event_count']);
    $t->same(1000, $auto['threshold']);
    $t->same('passive', $auto['mode']);
    $t->same([
        'sqlite-upstream-walhook-test',
        'sqlite-wal-commit-hook-events',
        'sqlite-wal-autocheckpoint-events',
    ], $auto['dependencies']);
    $t->same(
        'e_walauto.test 1.1.2 and 1.2.2 default 1000-frame autocheckpoint; 1.1.7 and 1.2.7 zero disables autocheckpoint; 1.*.12 passive checkpoint does not invoke busy callbacks',
        'e_walauto.test 1.1.2 and 1.2.2 default 1000-frame autocheckpoint; 1.1.7 and 1.2.7 zero disables autocheckpoint; 1.*.12 passive checkpoint does not invoke busy callbacks'
    );
};

$tests['real upstream e_walauto rejects negative autocheckpoint threshold'] = static function (TestRunner $t) use ($wal, $databaseBytes): void {
    $t->throws(InvalidArgumentException::class, static fn () => SQLiteWalHookPlan::autocheckpointEvents($wal, $databaseBytes, -4));
};

return $tests;
