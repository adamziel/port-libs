<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteJsonTableRecursiveRootCursor;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$optionValue = '{"plugins":[{"slug":"cache","rules":[{"name":"page","next":[{"name":"mobile","next":[]}]},{"name":"object","next":[]}]},{"slug":"seo","rules":[{"name":"schema","next":[]}]}]}';

$cursor = SQLiteJsonTableRecursiveRootCursor::tree($optionValue, '$.plugins[0].rules');
$frames = $cursor->drainByChildKey('next');

$summary = array_map(
    static fn (array $frame): array => [
        'root' => $frame['root'],
        'atoms' => $frame['atoms'],
        'queued' => $frame['queued'],
    ],
    $frames,
);

$expected = [
    [
        'root' => '$.plugins[0].rules',
        'atoms' => ['page', 'mobile', 'object'],
        'queued' => ['$.plugins[0].rules[0].next', '$.plugins[0].rules[0].next[0].next', '$.plugins[0].rules[1].next'],
    ],
    [
        'root' => '$.plugins[0].rules[0].next',
        'atoms' => ['mobile'],
        'queued' => [],
    ],
    [
        'root' => '$.plugins[0].rules[0].next[0].next',
        'atoms' => [],
        'queued' => [],
    ],
    [
        'root' => '$.plugins[0].rules[1].next',
        'atoms' => [],
        'queued' => [],
    ],
];

if (($argv[1] ?? '') === '--self-test') {
    if ($summary !== $expected) {
        fwrite(STDERR, "Unexpected recursive JSON root summary\n");
        fwrite(STDERR, var_export($summary, true) . "\n");
        exit(1);
    }

    fwrite(STDOUT, "application-json-table-recursive-root-current-next35 self-test passed\n");
    exit(0);
}

foreach ($summary as $frame) {
    fwrite(
        STDOUT,
        $frame['root'] . ' atoms=' . json_encode($frame['atoms']) . ' queued=' . json_encode($frame['queued']) . "\n",
    );
}
