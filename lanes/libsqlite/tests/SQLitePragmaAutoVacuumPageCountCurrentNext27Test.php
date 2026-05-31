<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaEncodingPageTempStoreState;

$tests = [];

$rejects = static function (callable $callback): string {
    try {
        $callback();
    } catch (InvalidArgumentException) {
        return 'rejected';
    }

    return 'accepted';
};

$cases = [
    'default auto vacuum is none' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA auto_vacuum')['effective'],
    'auto vacuum rows use sqlite column name' => static fn (): mixed => array_keys((new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA auto_vacuum')['rows'][0]),
    'auto vacuum numeric full on empty database' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA auto_vacuum=1')['effective'],
    'auto vacuum numeric incremental on empty database' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA auto_vacuum=2')['effective'],
    'auto vacuum keyword full maps one' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA auto_vacuum=FULL')['effective'],
    'auto vacuum keyword incremental maps two' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA auto_vacuum=INCREMENTAL')['effective'],
    'auto vacuum keyword none maps zero' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['auto_vacuum' => 0]]))->execute('PRAGMA auto_vacuum=NONE')['effective'],
    'auto vacuum keyword off maps zero' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['auto_vacuum' => 0]]))->execute('PRAGMA auto_vacuum=OFF')['effective'],
    'auto vacuum parenthesized full accepted' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA auto_vacuum(FULL)')['effective'],
    'auto vacuum parenthesized incremental accepted' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA auto_vacuum(INCREMENTAL)')['effective'],
    'auto vacuum quoted full accepted' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute("PRAGMA auto_vacuum='FULL'")['effective'],
    'auto vacuum quoted incremental accepted' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA auto_vacuum="INCREMENTAL"')['effective'],
    'auto vacuum changed true from none to full' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA auto_vacuum=FULL')['changed'],
    'auto vacuum changed false on same full' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['auto_vacuum' => 1]]))->execute('PRAGMA auto_vacuum=FULL')['changed'],
    'auto vacuum attached schema isolated' => static function (): mixed {
        $state = new SQLitePragmaEncodingPageTempStoreState(['aux' => ['auto_vacuum' => 2]]);
        return [$state->execute('PRAGMA aux.auto_vacuum')['effective'], $state->execute('PRAGMA main.auto_vacuum')['effective']];
    },
    'auto vacuum attached assignment isolated' => static function (): mixed {
        $state = new SQLitePragmaEncodingPageTempStoreState();
        $state->execute('PRAGMA aux.auto_vacuum=FULL');
        return [$state->execute('PRAGMA aux.auto_vacuum')['effective'], $state->execute('PRAGMA main.auto_vacuum')['effective']];
    },
    'auto vacuum temp schema query defaults none' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA temp.auto_vacuum')['effective'],
    'auto vacuum temp schema assignment noops' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA temp.auto_vacuum=FULL')['effective'],
    'auto vacuum temp schema reason' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA temp.auto_vacuum=FULL')['reason'],
    'auto vacuum enable on nonempty none keeps current' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['database_empty' => false, 'page_count' => 9]]))->execute('PRAGMA auto_vacuum=FULL')['effective'],
    'auto vacuum enable on nonempty reports pending full' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['database_empty' => false, 'page_count' => 9]]))->execute('PRAGMA auto_vacuum=FULL')['pending'],
    'auto vacuum enable on nonempty requires vacuum' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['database_empty' => false, 'page_count' => 9]]))->execute('PRAGMA auto_vacuum=FULL')['requires_vacuum'],
    'auto vacuum enable on nonempty reason' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['database_empty' => false, 'page_count' => 9]]))->execute('PRAGMA auto_vacuum=FULL')['reason'],
    'auto vacuum enable incremental on nonempty pending two' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['database_empty' => false, 'page_count' => 9]]))->execute('PRAGMA auto_vacuum=INCREMENTAL')['pending'],
    'auto vacuum full to incremental nonempty changes immediately' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['database_empty' => false, 'auto_vacuum' => 1, 'page_count' => 9]]))->execute('PRAGMA auto_vacuum=INCREMENTAL')['effective'],
    'auto vacuum incremental to full nonempty changes immediately' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['database_empty' => false, 'auto_vacuum' => 2, 'page_count' => 9]]))->execute('PRAGMA auto_vacuum=FULL')['effective'],
    'auto vacuum disable full keeps current until vacuum' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['auto_vacuum' => 1, 'page_count' => 9]]))->execute('PRAGMA auto_vacuum=NONE')['effective'],
    'auto vacuum disable full pending none' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['auto_vacuum' => 1, 'page_count' => 9]]))->execute('PRAGMA auto_vacuum=NONE')['pending'],
    'auto vacuum disable full requires vacuum' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['auto_vacuum' => 1, 'page_count' => 9]]))->execute('PRAGMA auto_vacuum=NONE')['requires_vacuum'],
    'auto vacuum disable full reason' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['auto_vacuum' => 1, 'page_count' => 9]]))->execute('PRAGMA auto_vacuum=NONE')['reason'],
    'auto vacuum disable incremental pending none' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['auto_vacuum' => 2, 'page_count' => 9]]))->execute('PRAGMA auto_vacuum=OFF')['pending'],
    'auto vacuum query returns seeded pending separately' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['auto_vacuum' => 0, 'pending_auto_vacuum' => 2, 'page_count' => 9]]))->execute('PRAGMA auto_vacuum')['pending'],
    'auto vacuum query includes current page count' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['auto_vacuum' => 1, 'page_count' => 44]]))->execute('PRAGMA auto_vacuum')['page_count'],
    'auto vacuum page count remains read only after setting' => static function (): mixed {
        $state = new SQLitePragmaEncodingPageTempStoreState(['main' => ['page_count' => 12]]);
        $state->execute('PRAGMA auto_vacuum=FULL');
        return $state->execute('PRAGMA page_count')['effective'];
    },
    'auto vacuum pending does not change page count' => static function (): mixed {
        $state = new SQLitePragmaEncodingPageTempStoreState(['main' => ['database_empty' => false, 'page_count' => 12]]);
        $state->execute('PRAGMA auto_vacuum=FULL');
        return $state->execute('PRAGMA page_count')['rows'][0]['page_count'];
    },
    'auto vacuum sequence full then incremental clears pending' => static function (): mixed {
        $state = new SQLitePragmaEncodingPageTempStoreState(['main' => ['auto_vacuum' => 1, 'pending_auto_vacuum' => 0, 'database_empty' => false]]);
        return $state->execute('PRAGMA auto_vacuum=INCREMENTAL')['pending'];
    },
    'auto vacuum sequence full then incremental current two' => static function (): mixed {
        $state = new SQLitePragmaEncodingPageTempStoreState(['main' => ['auto_vacuum' => 1, 'pending_auto_vacuum' => 0, 'database_empty' => false]]);
        return $state->execute('PRAGMA auto_vacuum=INCREMENTAL')['effective'];
    },
    'auto vacuum sequence nonempty enable then query current none' => static function (): mixed {
        $state = new SQLitePragmaEncodingPageTempStoreState(['main' => ['database_empty' => false]]);
        $state->execute('PRAGMA auto_vacuum=FULL');
        return $state->execute('PRAGMA auto_vacuum')['effective'];
    },
    'auto vacuum sequence nonempty enable then query pending full' => static function (): mixed {
        $state = new SQLitePragmaEncodingPageTempStoreState(['main' => ['database_empty' => false]]);
        $state->execute('PRAGMA auto_vacuum=FULL');
        return $state->execute('PRAGMA auto_vacuum')['pending'];
    },
    'auto vacuum schema lowercases attached names' => static fn (): mixed => SQLitePragmaEncodingPageTempStoreState::parse('PRAGMA Aux.auto_vacuum=FULL')['schema'],
    'auto vacuum parse query' => static fn (): mixed => SQLitePragmaEncodingPageTempStoreState::parse('PRAGMA auto_vacuum'),
    'auto vacuum parse equals full' => static fn (): mixed => SQLitePragmaEncodingPageTempStoreState::parse('PRAGMA auto_vacuum=FULL'),
    'auto vacuum parse parenthesized incremental' => static fn (): mixed => SQLitePragmaEncodingPageTempStoreState::parse('PRAGMA auto_vacuum(INCREMENTAL)'),
    'auto vacuum parse trailing semicolon' => static fn (): mixed => SQLitePragmaEncodingPageTempStoreState::parse(" PRAGMA main.auto_vacuum=2;\n"),
    'auto vacuum invalid keyword rejected' => static fn (): mixed => $rejects(static fn () => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA auto_vacuum=ON')),
    'auto vacuum invalid numeric rejected' => static fn (): mixed => $rejects(static fn () => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA auto_vacuum=3')),
    'auto vacuum invalid negative parse rejected' => static fn (): mixed => $rejects(static fn () => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA auto_vacuum=-1')),
    'auto vacuum dependencies report state' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA auto_vacuum=FULL')['dependencies'],
    'schema exposes normalized auto vacuum full' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['auto_vacuum' => 'FULL']]))->schemas()['main']['auto_vacuum'],
    'schema exposes normalized pending auto vacuum' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['pending_auto_vacuum' => 'INCREMENTAL']]))->schemas()['main']['pending_auto_vacuum'],
    'state exposes page count beside auto vacuum' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['auto_vacuum' => 2, 'page_count' => 21]]))->schemas()['main'],
];

$expected = [
    'default auto vacuum is none' => 0,
    'auto vacuum rows use sqlite column name' => ['auto_vacuum'],
    'auto vacuum numeric full on empty database' => 1,
    'auto vacuum numeric incremental on empty database' => 2,
    'auto vacuum keyword full maps one' => 1,
    'auto vacuum keyword incremental maps two' => 2,
    'auto vacuum keyword none maps zero' => 0,
    'auto vacuum keyword off maps zero' => 0,
    'auto vacuum parenthesized full accepted' => 1,
    'auto vacuum parenthesized incremental accepted' => 2,
    'auto vacuum quoted full accepted' => 1,
    'auto vacuum quoted incremental accepted' => 2,
    'auto vacuum changed true from none to full' => true,
    'auto vacuum changed false on same full' => false,
    'auto vacuum attached schema isolated' => [2, 0],
    'auto vacuum attached assignment isolated' => [1, 0],
    'auto vacuum temp schema query defaults none' => 0,
    'auto vacuum temp schema assignment noops' => 0,
    'auto vacuum temp schema reason' => 'temporary_schema_auto_vacuum_is_connection_local',
    'auto vacuum enable on nonempty none keeps current' => 0,
    'auto vacuum enable on nonempty reports pending full' => 1,
    'auto vacuum enable on nonempty requires vacuum' => true,
    'auto vacuum enable on nonempty reason' => 'auto_vacuum_enable_requires_vacuum',
    'auto vacuum enable incremental on nonempty pending two' => 2,
    'auto vacuum full to incremental nonempty changes immediately' => 2,
    'auto vacuum incremental to full nonempty changes immediately' => 1,
    'auto vacuum disable full keeps current until vacuum' => 1,
    'auto vacuum disable full pending none' => 0,
    'auto vacuum disable full requires vacuum' => true,
    'auto vacuum disable full reason' => 'auto_vacuum_disable_requires_vacuum',
    'auto vacuum disable incremental pending none' => 0,
    'auto vacuum query returns seeded pending separately' => 2,
    'auto vacuum query includes current page count' => 44,
    'auto vacuum page count remains read only after setting' => 12,
    'auto vacuum pending does not change page count' => 12,
    'auto vacuum sequence full then incremental clears pending' => null,
    'auto vacuum sequence full then incremental current two' => 2,
    'auto vacuum sequence nonempty enable then query current none' => 0,
    'auto vacuum sequence nonempty enable then query pending full' => 1,
    'auto vacuum schema lowercases attached names' => 'aux',
    'auto vacuum parse query' => ['schema' => 'main', 'pragma' => 'auto_vacuum', 'value' => null],
    'auto vacuum parse equals full' => ['schema' => 'main', 'pragma' => 'auto_vacuum', 'value' => 'FULL'],
    'auto vacuum parse parenthesized incremental' => ['schema' => 'main', 'pragma' => 'auto_vacuum', 'value' => 'INCREMENTAL'],
    'auto vacuum parse trailing semicolon' => ['schema' => 'main', 'pragma' => 'auto_vacuum', 'value' => '2'],
    'auto vacuum invalid keyword rejected' => 'rejected',
    'auto vacuum invalid numeric rejected' => 'accepted',
    'auto vacuum invalid negative parse rejected' => 'accepted',
    'auto vacuum dependencies report state' => ['sqlite-pragma-auto-vacuum-state'],
    'schema exposes normalized auto vacuum full' => 1,
    'schema exposes normalized pending auto vacuum' => 2,
    'state exposes page count beside auto vacuum' => [
        'encoding' => 'UTF-8',
        'page_size' => 4096,
        'page_count' => 21,
        'max_page_count' => 1073741823,
        'application_id' => 0,
        'temp_store' => 0,
        'auto_vacuum' => 2,
        'pending_auto_vacuum' => null,
        'database_empty' => false,
        'temporary' => false,
    ],
];

foreach ($cases as $name => $callback) {
    $tests['pragma auto vacuum page count current next27 ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
