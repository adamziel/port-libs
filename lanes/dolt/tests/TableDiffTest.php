<?php

declare(strict_types=1);

use PortLibs\Dolt\TableDiff;

return [
    'table diff classifies added removed and modified rows by primary key' => static function (TestRunner $t): void {
        $old = [
            ['id' => 1, 'title' => 'Draft'],
            ['id' => 2, 'title' => 'Remove me'],
        ];
        $new = [
            ['id' => 1, 'title' => 'Published'],
            ['id' => 3, 'title' => 'New'],
        ];
        $diff = (new TableDiff())->diff($old, $new, 'id');
        $t->same(1, count($diff['added']));
        $t->same(1, count($diff['removed']));
        $t->same('Published', $diff['modified'][0]['new']['title']);
    },
    'dolt diff table rows match upstream to/from column projection' => static function (TestRunner $t): void {
        $from = [
            ['pk' => 1, 'c1' => 2, 'c2' => 3],
            ['pk' => 2, 'c1' => 4, 'c2' => 5],
        ];
        $to = [
            ['pk' => 1, 'c1' => 2, 'c2' => 0],
            ['pk' => 3, 'c1' => 6, 'c2' => 7],
        ];

        $rows = (new TableDiff())->diffTableRows(
            $from,
            $to,
            'pk',
            ['pk', 'c1', 'c2'],
            'from-hash',
            '2026-05-21 12:00:00',
            'to-hash',
            '2026-05-22 12:00:00',
        );

        $t->same(['modified', 'removed', 'added'], array_column($rows, 'diff_type'));
        $t->same([1, 2, 0, 'to-hash', '2026-05-22 12:00:00', 1, 2, 3, 'from-hash', '2026-05-21 12:00:00', 'modified'], array_values($rows[0]));
        $t->same([null, null, null, 'to-hash', '2026-05-22 12:00:00', 2, 4, 5, 'from-hash', '2026-05-21 12:00:00', 'removed'], array_values($rows[1]));
        $t->same([3, 6, 7, 'to-hash', '2026-05-22 12:00:00', null, null, null, 'from-hash', '2026-05-21 12:00:00', 'added'], array_values($rows[2]));
    },
    'composite primary keys are encoded without string collisions' => static function (TestRunner $t): void {
        $from = [
            ['site_id' => 1, 'option_name' => 'a:bc', 'option_value' => 'old'],
            ['site_id' => 1, 'option_name' => 'ab:c', 'option_value' => 'same'],
        ];
        $to = [
            ['site_id' => 1, 'option_name' => 'a:bc', 'option_value' => 'new'],
            ['site_id' => 1, 'option_name' => 'ab:c', 'option_value' => 'same'],
        ];

        $diff = (new TableDiff())->diff($from, $to, ['site_id', 'option_name']);
        $t->same(1, count($diff['modified']));
        $t->same('a:bc', $diff['modified'][0]['old']['option_name']);
        $t->same('new', $diff['modified'][0]['new']['option_value']);
    },
    'primary key validation rejects missing null and duplicate keys' => static function (TestRunner $t): void {
        $differ = new TableDiff();

        $t->throws(InvalidArgumentException::class, static fn () => $differ->diff([['title' => 'missing']], [], 'id'));
        $t->throws(InvalidArgumentException::class, static fn () => $differ->diff([['id' => null]], [], 'id'));
        $t->throws(InvalidArgumentException::class, static fn () => $differ->diff([['id' => 1], ['id' => 1]], [], 'id'));
    },
    'wordpress posts fixture projects migration changes as dolt diff rows' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-posts-diff.php';
        $rows = (new TableDiff())->diffTableRows(
            $fixture['fromRows'],
            $fixture['toRows'],
            'ID',
            $fixture['columns'],
            $fixture['fromCommit'],
            null,
            $fixture['toCommit'],
            null,
        );

        $t->same($fixture['expectedDiffTypes'], array_column($rows, 'diff_type'));
        $t->same($fixture['expectedChangedIds'], array_map(
            static fn (array $row): int => (int) ($row['to_ID'] ?? $row['from_ID']),
            $rows,
        ));
        $t->same('Published landing', $rows[0]['to_post_title']);
        $t->same('Legacy page', $rows[1]['from_post_title']);
        $t->same('Imported resource', $rows[2]['to_post_title']);
    },
];
