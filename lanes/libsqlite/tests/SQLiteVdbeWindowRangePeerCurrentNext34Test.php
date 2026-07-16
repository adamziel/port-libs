<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteBlobValue;
use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$rows = [
    ['rowid' => 1, 'site' => 1, 'bucket' => 1.0, 'name' => 'siteurl', 'bytes' => 10, 'ok' => 1],
    ['rowid' => 2, 'site' => 1, 'bucket' => 1.0, 'name' => 'home', 'bytes' => 20, 'ok' => 0],
    ['rowid' => 3, 'site' => 1, 'bucket' => 1.25, 'name' => 'blogname', 'bytes' => 30, 'ok' => '1'],
    ['rowid' => 4, 'site' => 1, 'bucket' => 1.5, 'name' => 'rewrite_rules', 'bytes' => 40, 'ok' => true],
    ['rowid' => 5, 'site' => 1, 'bucket' => 2.0, 'name' => '_transient_a', 'bytes' => null, 'ok' => null],
    ['rowid' => 6, 'site' => 1, 'bucket' => 2.0, 'name' => '_transient_b', 'bytes' => 60, 'ok' => '2'],
    ['rowid' => 7, 'site' => 2, 'bucket' => 1.0, 'name' => 'network_siteurl', 'bytes' => 70, 'ok' => 1],
    ['rowid' => 8, 'site' => 2, 'bucket' => 1.4, 'name' => 'network_home', 'bytes' => 80, 'ok' => '0'],
    ['rowid' => 9, 'site' => 2, 'bucket' => 1.4, 'name' => 'network_plugin', 'bytes' => 90, 'ok' => 1],
    ['rowid' => 10, 'site' => 2, 'bucket' => 2.1, 'name' => 'network_cache', 'bytes' => 100, 'ok' => 1],
];

$cursorFor = static fn (float|int $preceding = 0.0, float|int $following = 0.5, string $unit = 'RANGE'): SQLiteVdbeWindowAggregateCursor => new SQLiteVdbeWindowAggregateCursor(
    $rows,
    'bytes',
    ['site'],
    ['bucket'],
    'ok',
    $preceding,
    $following,
    'D',
    [],
    'D',
    [],
    [],
    [],
    $unit,
);

$tests = [];

$cases = [
    'range current next row1 includes current peers and following band' => [static fn () => array_column($cursorFor()->currentFrameRows(false), 'rowid'), [1, 2, 3, 4]],
    'range current next row1 filtered rows keep sql truthy peers' => [static fn () => array_column($cursorFor()->currentFrameRows(true), 'rowid'), [1, 3, 4]],
    'range current next row1 count all includes null and false filter rows' => [static fn () => $cursorFor()->countAll(), 4],
    'range current next row1 count value applies filter before null skipping' => [static fn () => $cursorFor()->countValue(), 3],
    'range current next row1 sum applies filter after peer frame' => [static fn () => $cursorFor()->sum(), 80],
    'range current next row1 total is floating aggregate' => [static fn () => $cursorFor()->total(), 80.0],
    'range current next row1 avg uses filtered non null values' => [static fn () => $cursorFor()->avg(), 80 / 3],
    'range current next row1 group concat preserves frame order' => [static fn () => $cursorFor()->groupConcat('|'), '10|30|40'],
    'range current next row1 unfiltered values preserve false peer' => [static fn () => $cursorFor()->currentValues(false), [10, 20, 30, 40]],
    'range current next row1 filtered values drop false peer' => [static fn () => $cursorFor()->currentValues(true), [10, 30, 40]],
    'range current next row2 sees same peer frame as row1' => [static function () use ($cursorFor): array {
        $cursor = $cursorFor();
        $cursor->next();
        return array_column($cursor->currentFrameRows(false), 'rowid');
    }, [1, 2, 3, 4]],
    'range current next row3 starts at current key and reaches next singleton peer' => [static function () use ($cursorFor): array {
        $cursor = $cursorFor();
        $cursor->next();
        $cursor->next();
        return array_column($cursor->currentFrameRows(false), 'rowid');
    }, [3, 4]],
    'range current next row4 reaches duplicate 2.0 peers' => [static function () use ($cursorFor): array {
        $cursor = $cursorFor();
        for ($i = 0; $i < 3; $i++) {
            $cursor->next();
        }
        return array_column($cursor->currentFrameRows(false), 'rowid');
    }, [4, 5, 6]],
    'range current next row5 peer includes row6 only' => [static function () use ($cursorFor): array {
        $cursor = $cursorFor();
        for ($i = 0; $i < 4; $i++) {
            $cursor->next();
        }
        return array_column($cursor->currentFrameRows(false), 'rowid');
    }, [5, 6]],
    'range current next row5 filtered frame keeps truthy row6' => [static function () use ($cursorFor): array {
        $cursor = $cursorFor();
        for ($i = 0; $i < 4; $i++) {
            $cursor->next();
        }
        return array_column($cursor->currentFrameRows(true), 'rowid');
    }, [6]],
    'range current next row5 count value skips filtered null' => [static function () use ($cursorFor): int {
        $cursor = $cursorFor();
        for ($i = 0; $i < 4; $i++) {
            $cursor->next();
        }
        return $cursor->countValue();
    }, 1],
    'range current next row5 sum is row6 only' => [static function () use ($cursorFor): mixed {
        $cursor = $cursorFor();
        for ($i = 0; $i < 4; $i++) {
            $cursor->next();
        }
        return $cursor->sum();
    }, 60],
    'range current next row7 starts new partition without crossing site' => [static function () use ($cursorFor): array {
        $cursor = $cursorFor();
        for ($i = 0; $i < 6; $i++) {
            $cursor->next();
        }
        return array_column($cursor->currentFrameRows(false), 'rowid');
    }, [7, 8, 9]],
    'range current next row8 duplicate peer frame excludes row7' => [static function () use ($cursorFor): array {
        $cursor = $cursorFor();
        for ($i = 0; $i < 7; $i++) {
            $cursor->next();
        }
        return array_column($cursor->currentFrameRows(false), 'rowid');
    }, [8, 9]],
    'range current next row9 shares duplicate peer frame' => [static function () use ($cursorFor): array {
        $cursor = $cursorFor();
        for ($i = 0; $i < 8; $i++) {
            $cursor->next();
        }
        return array_column($cursor->currentFrameRows(false), 'rowid');
    }, [8, 9]],
    'range current next row10 clamps at partition end' => [static function () use ($cursorFor): array {
        $cursor = $cursorFor();
        for ($i = 0; $i < 9; $i++) {
            $cursor->next();
        }
        return array_column($cursor->currentFrameRows(false), 'rowid');
    }, [10]],
    'range preceding current row3 includes earlier peer band' => [static function () use ($cursorFor): array {
        $cursor = $cursorFor(0.25, 0.0);
        $cursor->next();
        $cursor->next();
        return array_column($cursor->currentFrameRows(false), 'rowid');
    }, [1, 2, 3]],
    'range preceding current row4 starts at row3' => [static function () use ($cursorFor): array {
        $cursor = $cursorFor(0.25, 0.0);
        for ($i = 0; $i < 3; $i++) {
            $cursor->next();
        }
        return array_column($cursor->currentFrameRows(false), 'rowid');
    }, [3, 4]],
    'range preceding current duplicate peers include current peer group' => [static function () use ($cursorFor): array {
        $cursor = $cursorFor(0.5, 0.0);
        for ($i = 0; $i < 4; $i++) {
            $cursor->next();
        }
        return array_column($cursor->currentFrameRows(false), 'rowid');
    }, [4, 5, 6]],
    'range zero current peers groups row1 row2' => [static fn () => array_column($cursorFor(0.0, 0.0)->currentFrameRows(false), 'rowid'), [1, 2]],
    'range tiny current next keeps row1 peer group only' => [static fn () => array_column($cursorFor(0.0, 0.1)->currentFrameRows(false), 'rowid'), [1, 2]],
    'range exact boundary includes row3 for 0.25 following' => [static fn () => array_column($cursorFor(0.0, 0.25)->currentFrameRows(false), 'rowid'), [1, 2, 3]],
    'range summary reports expanded peer start' => [static fn () => $cursorFor()->currentSummary()['frameStart'], 0],
    'range summary reports expanded following end' => [static fn () => $cursorFor()->currentSummary()['frameEnd'], 3],
    'range summary reports frame row count' => [static fn () => $cursorFor()->currentSummary()['frameRows'], 4],
    'range summary reports filtered row count' => [static fn () => $cursorFor()->currentSummary()['filteredRows'], 3],
    'range drain summaries reports peer frame totals' => [static fn () => array_column($cursorFor()->drainSummaries('|'), 'total'), [80.0, 80.0, 70.0, 100.0, 60.0, 60.0, 160.0, 90.0, 90.0, 100.0]],
    'range drain summaries reports peer group concat values' => [static fn () => array_column($cursorFor()->drainSummaries('|'), 'groupConcat'), ['10|30|40', '10|30|40', '30|40', '40|60', '60', '60', '70|90', '90', '90', '100']],
    'range rewind returns to first peer frame after drain' => [static function () use ($cursorFor): array {
        $cursor = $cursorFor();
        $cursor->drainSummaries('|');
        $cursor->rewind();
        return array_column($cursor->currentFrameRows(false), 'rowid');
    }, [1, 2, 3, 4]],
    'groups current next row1 includes next peer group only' => [static fn () => array_column($cursorFor(0, 1, 'GROUPS')->currentFrameRows(false), 'rowid'), [1, 2, 3]],
    'groups current next row3 includes following singleton group' => [static function () use ($cursorFor): array {
        $cursor = $cursorFor(0, 1, 'GROUPS');
        $cursor->next();
        $cursor->next();
        return array_column($cursor->currentFrameRows(false), 'rowid');
    }, [3, 4]],
    'groups current next row4 includes duplicate following group' => [static function () use ($cursorFor): array {
        $cursor = $cursorFor(0, 1, 'GROUPS');
        for ($i = 0; $i < 3; $i++) {
            $cursor->next();
        }
        return array_column($cursor->currentFrameRows(false), 'rowid');
    }, [4, 5, 6]],
    'groups preceding current duplicate peers include previous group' => [static function () use ($cursorFor): array {
        $cursor = $cursorFor(1, 0, 'GROUPS');
        for ($i = 0; $i < 4; $i++) {
            $cursor->next();
        }
        return array_column($cursor->currentFrameRows(false), 'rowid');
    }, [4, 5, 6]],
    'groups current next never crosses partition' => [static function () use ($cursorFor): array {
        $cursor = $cursorFor(0, 1, 'GROUPS');
        for ($i = 0; $i < 5; $i++) {
            $cursor->next();
        }
        return array_column($cursor->currentFrameRows(false), 'rowid');
    }, [5, 6]],
    'rows mode remains positional for current next' => [static fn () => array_column($cursorFor(0, 1, 'ROWS')->currentFrameRows(false), 'rowid'), [1, 2]],
    'range lowercase unit is accepted' => [static fn () => array_column($cursorFor(0.0, 0.25, 'range')->currentFrameRows(false), 'rowid'), [1, 2, 3]],
    'range wider following reaches duplicate future peers' => [static function () use ($cursorFor): array {
        $cursor = $cursorFor(0.0, 0.75);
        $cursor->next();
        $cursor->next();
        return array_column($cursor->currentFrameRows(false), 'rowid');
    }, [3, 4, 5, 6]],
    'range bool order values use numeric keys' => [static function (): array {
        $cursor = new SQLiteVdbeWindowAggregateCursor(
            [
                ['rowid' => 1, 'site' => 1, 'bucket' => false, 'bytes' => 1],
                ['rowid' => 2, 'site' => 1, 'bucket' => false, 'bytes' => 2],
                ['rowid' => 3, 'site' => 1, 'bucket' => true, 'bytes' => 3],
            ],
            'bytes',
            ['site'],
            ['bucket'],
            null,
            0.0,
            0.5,
            'D',
            [],
            'D',
            [],
            [],
            [],
            'RANGE',
        );

        return array_column($cursor->currentFrameRows(false), 'rowid');
    }, [1, 2]],
    'range rejects multiple order columns' => [static function () use ($rows): string {
        try {
            new SQLiteVdbeWindowAggregateCursor($rows, 'bytes', ['site'], ['bucket', 'rowid'], null, 0, 1, 'D', [], 'DD', [], [], [], 'RANGE');
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    }, 'SQLite VDBE window aggregate RANGE frame requires one ORDER BY column'],
    'range rejects text order values when frame is read' => [static function (): string {
        try {
            $cursor = new SQLiteVdbeWindowAggregateCursor(
                [['rowid' => 1, 'site' => 1, 'bucket' => '1.0', 'bytes' => 1]],
                'bytes',
                ['site'],
                ['bucket'],
                null,
                0.0,
                0.5,
                'D',
                [],
                'D',
                [],
                [],
                [],
                'RANGE',
            );
            $cursor->currentFrameRows(false);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    }, 'SQLite VDBE window aggregate RANGE frame requires numeric ORDER BY values'],
    'range rejects blob order values when frame is read' => [static function (): string {
        try {
            $cursor = new SQLiteVdbeWindowAggregateCursor(
                [['rowid' => 1, 'site' => 1, 'bucket' => new SQLiteBlobValue('1'), 'bytes' => 1]],
                'bytes',
                ['site'],
                ['bucket'],
                null,
                0.0,
                0.5,
                'D',
                [],
                'D',
                [],
                [],
                [],
                'RANGE',
            );
            $cursor->currentFrameRows(false);
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    }, 'SQLite VDBE window aggregate RANGE frame requires numeric ORDER BY values'],
    'rows rejects fractional following bound' => [static function () use ($rows): string {
        try {
            new SQLiteVdbeWindowAggregateCursor($rows, 'bytes', ['site'], ['bucket'], null, 0, 0.5, 'D', [], 'D', [], [], [], 'ROWS');
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    }, 'SQLite VDBE window aggregate ROWS frame bounds must be integers'],
    'groups rejects fractional preceding bound' => [static function () use ($rows): string {
        try {
            new SQLiteVdbeWindowAggregateCursor($rows, 'bytes', ['site'], ['bucket'], null, 0.5, 1, 'D', [], 'D', [], [], [], 'GROUPS');
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    }, 'SQLite VDBE window aggregate GROUPS frame bounds must be integers'],
    'unsupported frame unit is rejected' => [static function () use ($rows): string {
        try {
            new SQLiteVdbeWindowAggregateCursor($rows, 'bytes', ['site'], ['bucket'], null, 0, 1, 'D', [], 'D', [], [], [], 'BOUNDS');
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    }, 'SQLite VDBE window aggregate frame unit is not supported'],
    'negative range following remains rejected' => [static function () use ($rows): string {
        try {
            new SQLiteVdbeWindowAggregateCursor($rows, 'bytes', ['site'], ['bucket'], null, 0, -0.5, 'D', [], 'D', [], [], [], 'RANGE');
        } catch (InvalidArgumentException $exception) {
            return $exception->getMessage();
        }

        return 'missing exception';
    }, 'SQLite VDBE window aggregate frame bounds must be non-negative'],
];

foreach ($cases as $name => [$callback, $expected]) {
    $tests['vdbe window range peer current next34 ' . $name] = static function (TestRunner $t) use ($callback, $expected): void {
        $t->same($expected, $callback());
    };
}

return $tests;
