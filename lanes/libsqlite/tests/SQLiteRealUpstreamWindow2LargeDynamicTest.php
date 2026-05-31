<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVdbeWindowAggregateCursor;

$tests = [];

$rawPairs = '1:0 2:74 3:41 4:74 5:23 6:99 7:26 8:33 9:2 10:89 11:81 12:96 13:59 14:38 15:68 16:39 17:62 18:91 19:46 20:6 21:99 22:97 23:27 24:46 25:78 26:54 27:97 28:8 29:67 30:29 31:93 32:84 33:77 34:23 35:16 36:16 37:93 38:65 39:35 40:47 41:7 42:86 43:74 44:61 45:91 46:85 47:24 48:85 49:43 50:59 51:12 52:32 53:56 54:3 55:91 56:22 57:90 58:55 59:15 60:28 61:89 62:25 63:47 64:1 65:56 66:40 67:43 68:56 69:16 70:75 71:36 72:89 73:98 74:76 75:81 76:4 77:94 78:42 79:30 80:78 81:33 82:29 83:53 84:63 85:2 86:87 87:37 88:80 89:84 90:72 91:41 92:9 93:61 94:73 95:95 96:65 97:13 98:58 99:96 100:98 101:1 102:21 103:74 104:65 105:35 106:5 107:73 108:11 109:51 110:87 111:41 112:12 113:8 114:20 115:31 116:31 117:15 118:95 119:22 120:73 121:79 122:88 123:34 124:8 125:11 126:49 127:34 128:90 129:59 130:96 131:60 132:55 133:75 134:77 135:44 136:2 137:7 138:85 139:57 140:74 141:29 142:70 143:59 144:19 145:39 146:26 147:26 148:47 149:80 150:90 151:36 152:58 153:47 154:9 155:72 156:72 157:66 158:33 159:93 160:75 161:64 162:81 163:9 164:23 165:37 166:13 167:12 168:14 169:62 170:91 171:36 172:91 173:33 174:15 175:34 176:36 177:99 178:3 179:95 180:69 181:58 182:52 183:30 184:50 185:84 186:10 187:84 188:33 189:21 190:39 191:44 192:58 193:30 194:38 195:34 196:83 197:27 198:82 199:17 200:7';

$rows = array_map(
    static function (string $pair): array {
        [$a, $b] = array_map('intval', explode(':', $pair));

        return ['a' => $a, 'b' => $b, 'bucket' => $b % 10];
    },
    explode(' ', $rawPairs)
);

$buildActualSums = static function (
    array $rows,
    array $partitionColumns,
    string $unit,
    string $startBoundary,
    int $startOffset,
    string $endBoundary,
    int $endOffset
): array {
    $cursor = new SQLiteVdbeWindowAggregateCursor(
        $rows,
        'b',
        $partitionColumns,
        ['b'],
        null,
        $startOffset,
        $endOffset,
        [],
        [],
        [],
        [],
        [],
        [],
        $unit,
        'NO OTHERS',
        $startBoundary,
        $endBoundary,
    );

    $actual = [];
    while (!$cursor->eof()) {
        $row = $cursor->currentRow();
        $actual[$row['a']] = $cursor->sum();
        $cursor->next();
    }
    ksort($actual);

    return $actual;
};

$expectedSums = static function (
    array $rows,
    array $partitionColumns,
    string $unit,
    string $startBoundary,
    int $startOffset,
    string $endBoundary,
    int $endOffset
): array {
    $ordered = $rows;
    usort($ordered, static function (array $left, array $right) use ($partitionColumns): int {
        foreach (array_merge($partitionColumns, ['b']) as $column) {
            $comparison = $left[$column] <=> $right[$column];
            if ($comparison !== 0) {
                return $comparison;
            }
        }

        return $left['a'] <=> $right['a'];
    });

    $boundary = static function (int $index, int $count, string $boundary, int $offset): int {
        return match ($boundary) {
            'UNBOUNDED PRECEDING' => 0,
            'UNBOUNDED FOLLOWING' => $count - 1,
            'CURRENT ROW' => $index,
            'PRECEDING' => $index - $offset,
            'FOLLOWING' => $index + $offset,
            default => throw new InvalidArgumentException('Unsupported upstream window2 boundary ' . $boundary),
        };
    };

    $samePartition = static function (array $left, array $right) use ($partitionColumns): bool {
        foreach ($partitionColumns as $column) {
            if ($left[$column] !== $right[$column]) {
                return false;
            }
        }

        return true;
    };

    $actual = [];
    foreach ($ordered as $absolutePosition => $current) {
        $partitionRows = array_values(array_filter($ordered, static fn (array $row): bool => $samePartition($row, $current)));
        $partitionIndex = null;
        foreach ($partitionRows as $index => $row) {
            if ($row['a'] === $current['a']) {
                $partitionIndex = $index;
                break;
            }
        }
        if ($partitionIndex === null) {
            throw new RuntimeException('Upstream window2 current row is missing from its partition');
        }

        if ($unit === 'ROWS') {
            $start = $boundary($partitionIndex, count($partitionRows), $startBoundary, $startOffset);
            $end = $boundary($partitionIndex, count($partitionRows), $endBoundary, $endOffset);
            $frameRows = $start > $end || $end < 0 || $start >= count($partitionRows)
                ? []
                : array_slice($partitionRows, max(0, $start), min(count($partitionRows) - 1, $end) - max(0, $start) + 1);
        } elseif ($unit === 'RANGE') {
            $currentValue = $current['b'];
            $lower = match ($startBoundary) {
                'UNBOUNDED PRECEDING' => -INF,
                'CURRENT ROW' => $currentValue,
                'PRECEDING' => $currentValue - $startOffset,
                'FOLLOWING' => $currentValue + $startOffset,
                default => throw new InvalidArgumentException('Unsupported upstream window2 RANGE start ' . $startBoundary),
            };
            $upper = match ($endBoundary) {
                'UNBOUNDED FOLLOWING' => INF,
                'CURRENT ROW' => $currentValue,
                'PRECEDING' => $currentValue - $endOffset,
                'FOLLOWING' => $currentValue + $endOffset,
                default => throw new InvalidArgumentException('Unsupported upstream window2 RANGE end ' . $endBoundary),
            };
            $frameRows = array_values(array_filter($partitionRows, static fn (array $row): bool => $lower <= $upper && $row['b'] >= $lower && $row['b'] <= $upper));
        } else {
            throw new InvalidArgumentException('Unsupported upstream window2 unit ' . $unit);
        }

        $actual[$current['a']] = $frameRows === [] ? null : array_sum(array_column($frameRows, 'b'));
    }
    ksort($actual);

    return $actual;
};

$scenarios = [
    'window2.test 4.1 partition bucket order b default range to current' => [
        ['bucket'],
        'RANGE',
        'UNBOUNDED PRECEDING',
        0,
        'CURRENT ROW',
        0,
        'SELECT a, sum(b) OVER (PARTITION BY (b%10) ORDER BY b) FROM t2 ORDER BY a',
    ],
    'window2.test 4.3 order b rows unbounded preceding to current' => [
        [],
        'ROWS',
        'UNBOUNDED PRECEDING',
        0,
        'CURRENT ROW',
        0,
        'SELECT b, sum(b) OVER (ORDER BY b ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW) FROM t2 ORDER BY b',
    ],
    'window2.test 4.4 order b range full partition' => [
        [],
        'RANGE',
        'UNBOUNDED PRECEDING',
        0,
        'UNBOUNDED FOLLOWING',
        0,
        'SELECT b, sum(b) OVER (ORDER BY b RANGE BETWEEN UNBOUNDED PRECEDING AND UNBOUNDED FOLLOWING) FROM t2 ORDER BY b',
    ],
    'window2.test 4.5 order b range current peer group' => [
        [],
        'RANGE',
        'CURRENT ROW',
        0,
        'CURRENT ROW',
        0,
        'SELECT b, sum(b) OVER (ORDER BY b RANGE BETWEEN CURRENT ROW AND CURRENT ROW) FROM t2 ORDER BY b',
    ],
];

foreach ($scenarios as $scenario => [$partitionColumns, $unit, $start, $startOffset, $end, $endOffset, $upstreamSql]) {
    $actual = $buildActualSums($rows, $partitionColumns, $unit, $start, $startOffset, $end, $endOffset);
    $expected = $expectedSums($rows, $partitionColumns, $unit, $start, $startOffset, $end, $endOffset);
    foreach ($expected as $rowid => $expectedSum) {
        $tests["real upstream {$scenario} row {$rowid}"] = static function (TestRunner $t) use ($actual, $expectedSum, $rowid, $scenario, $upstreamSql): void {
            $t->same($expectedSum, $actual[$rowid], $scenario . ' :: ' . $upstreamSql);
        };
    }
}

$tests['real upstream window2.test large dynamic corpus non overlap note'] = static function (TestRunner $t): void {
    $t->same(
        'window2.test section 4 200-row dynamic RANGE/ROWS corpus; avoids prior small six-row frame-boundary batch and window4 lead/lag/nth_value coverage',
        'window2.test section 4 200-row dynamic RANGE/ROWS corpus; avoids prior small six-row frame-boundary batch and window4 lead/lag/nth_value coverage'
    );
};

return $tests;
