<?php

declare(strict_types=1);

use PortLibs\Dolt\CommitAncestorsTable;
use PortLibs\Dolt\CommitLogTable;

$ancestorGraph = static function (): array {
    return [
        [
            'commit_hash' => 'init',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:00:00',
            'message' => 'Initialize data repository',
            'parents' => [],
        ],
        [
            'commit_hash' => 'branch1-base',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:01:00',
            'message' => 'test commit 1',
            'parents' => ['init'],
        ],
        [
            'commit_hash' => 'branch2-tip',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:02:00',
            'message' => 'test commit 2',
            'parents' => ['branch1-base'],
        ],
        [
            'commit_hash' => 'branch1-tip',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:03:00',
            'message' => 'test commit 3',
            'parents' => ['branch1-base'],
        ],
        [
            'commit_hash' => 'merge-head',
            'committer' => 'root',
            'email' => 'root@localhost',
            'date' => '2026-05-22 10:04:00',
            'message' => 'Merge branch2',
            'parents' => ['branch1-tip', 'branch2-tip'],
            'refs' => ['refs/heads/main'],
        ],
    ];
};

return [
    'dolt commit ancestors rows expose root null parent and parent indexes' => static function (TestRunner $t) use ($ancestorGraph): void {
        $rows = (new CommitAncestorsTable())->rows($ancestorGraph());

        $t->same(CommitAncestorsTable::COLUMNS, array_keys($rows[0]));
        $t->same(['merge-head', 'merge-head', 'branch1-tip', 'branch2-tip', 'branch1-base', 'init'], array_column($rows, 'commit_hash'));
        $t->same(['branch1-tip', 'branch2-tip'], array_column(array_slice($rows, 0, 2), 'parent_hash'));
        $t->same([0, 1], array_column(array_slice($rows, 0, 2), 'parent_index'));
        $t->same([
            'commit_hash' => 'init',
            'parent_hash' => null,
            'parent_index' => 0,
        ], $rows[5]);
    },
    'dolt commit ancestors commit hash filter preserves all merge parents' => static function (TestRunner $t) use ($ancestorGraph): void {
        $ancestors = (new CommitAncestorsTable())->rows($ancestorGraph(), 'merge-head');
        $logRows = (new CommitLogTable())->logRows($ancestorGraph(), [
            'headHash' => 'merge-head',
            'showParents' => true,
        ]);
        $messagesByHash = array_column($logRows, 'message', 'commit_hash');

        $joined = array_map(static fn (array $row): array => [
            'parent_index' => $row['parent_index'],
            'message' => $messagesByHash[$row['parent_hash']],
        ], $ancestors);

        $t->same([
            ['commit_hash' => 'merge-head', 'parent_hash' => 'branch1-tip', 'parent_index' => 0],
            ['commit_hash' => 'merge-head', 'parent_hash' => 'branch2-tip', 'parent_index' => 1],
        ], $ancestors);
        $t->same([
            ['parent_index' => 0, 'message' => 'test commit 3'],
            ['parent_index' => 1, 'message' => 'test commit 2'],
        ], $joined);
        $t->same('branch1-tip, branch2-tip', $logRows[0]['parents']);
    },
    'dolt commit ancestors handles missing filters limits and invalid graphs' => static function (TestRunner $t) use ($ancestorGraph): void {
        $table = new CommitAncestorsTable();

        $t->same([], $table->rows($ancestorGraph(), 'missing'));
        $t->same([
            ['commit_hash' => 'merge-head', 'parent_hash' => 'branch1-tip', 'parent_index' => 0],
        ], $table->rows($ancestorGraph(), 'merge-head', 1));
        $t->throws(InvalidArgumentException::class, static fn () => $table->rows($ancestorGraph(), 'merge-head', -1));
        $t->throws(RuntimeException::class, static fn () => $table->rows([
            ['commit_hash' => 'child', 'parents' => ['missing']],
        ]));
        $t->throws(RuntimeException::class, static fn () => $table->rows([
            ['commit_hash' => 'a', 'parents' => ['b']],
            ['commit_hash' => 'b', 'parents' => ['a']],
        ]));
    },
    'wordpress commit ancestors fixture links import merge parents to log rows' => static function (TestRunner $t): void {
        $fixture = require __DIR__ . '/../fixtures/wp-commit-ancestors-review.php';
        $ancestors = (new CommitAncestorsTable())->rows($fixture['commits'], $fixture['headHash']);
        $logRows = (new CommitLogTable())->logRows($fixture['commits'], [
            'headHash' => $fixture['headHash'],
            'showParents' => true,
        ]);
        $messagesByHash = array_column($logRows, 'message', 'commit_hash');
        $parentMessages = array_map(static fn (array $row): array => [
            'parent_index' => $row['parent_index'],
            'parent_hash' => $row['parent_hash'],
            'message' => $messagesByHash[$row['parent_hash']],
        ], $ancestors);
        $example = require __DIR__ . '/../examples/wordpress-commit-ancestors-review.php';

        $t->same($fixture['expectedAncestorRows'], $ancestors);
        $t->same($fixture['expectedParentMessages'], $parentMessages);
        $t->same($fixture['expectedParentMessages'], $example['parentMessages']);
        $t->same($fixture['expectedAncestorRows'], $example['ancestors']);
    },
];
