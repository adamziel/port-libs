<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteSelectSql;

require dirname(__DIR__, 3) . '/tools/bootstrap.php';

$tables = [
    'wp_options' => [
        ['option_id' => 1, 'option_name' => 'siteurl', 'option_value' => '{"links":[2,3],"kind":"root"}'],
        ['option_id' => 2, 'option_name' => 'blogname', 'option_value' => '{"links":[3],"kind":"branch"}'],
        ['option_id' => 3, 'option_name' => 'theme_mods', 'option_value' => '{"links":[],"kind":"leaf"}'],
    ],
];

$sql = <<<'SQL'
WITH RECURSIVE walk(id, depth) AS (
    VALUES (1, 0)
    UNION
    SELECT CAST(j.value AS INTEGER), walk.depth + 1
    FROM walk
    JOIN wp_options ON wp_options.option_id = walk.id
    JOIN json_each(wp_options.option_value, '$.links') AS j
    WHERE walk.depth < 2
)
SELECT
    id,
    depth,
    row_number() OVER (ORDER BY depth, id) AS rn,
    lead(id, 1, -1) OVER (ORDER BY depth, id) AS next_id,
    count(*) OVER (ORDER BY depth, id ROWS BETWEEN CURRENT ROW AND 1 FOLLOWING) AS frame_count
FROM walk
ORDER BY depth, id
SQL;

$rows = SQLiteSelectSql::execute($sql, $tables);

$expected = [
    ['id' => 1, 'depth' => 0, 'rn' => 1, 'next_id' => 2, 'frame_count' => 2],
    ['id' => 2, 'depth' => 1, 'rn' => 2, 'next_id' => 3, 'frame_count' => 2],
    ['id' => 3, 'depth' => 1, 'rn' => 3, 'next_id' => 3, 'frame_count' => 2],
    ['id' => 3, 'depth' => 2, 'rn' => 4, 'next_id' => -1, 'frame_count' => 1],
];

if (($argv[1] ?? '') === '--self-test') {
    if ($rows !== $expected) {
        fwrite(STDERR, "Unexpected recursive JSON window SELECT summary\n");
        fwrite(STDERR, var_export($rows, true) . "\n");
        exit(1);
    }

    fwrite(STDOUT, "application-select-recursive-json-window-current-next50 self-test passed\n");
    exit(0);
}

foreach ($rows as $row) {
    fwrite(
        STDOUT,
        sprintf(
            "option_id=%d depth=%d row_number=%d next_id=%d frame_count=%d\n",
            $row['id'],
            $row['depth'],
            $row['rn'],
            $row['next_id'],
            $row['frame_count'],
        ),
    );
}
