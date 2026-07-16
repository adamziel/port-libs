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
    'default encoding query is utf8' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA encoding')['effective'],
    'encoding rows use sqlite column name' => static fn (): mixed => array_keys((new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA encoding')['rows'][0]),
    'encoding assignment utf16 keyword maps little endian' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute("PRAGMA encoding='UTF-16'")['effective'],
    'encoding assignment utf16le keyword maps little endian' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA encoding = UTF_16le')['effective'],
    'encoding assignment utf16be keyword maps big endian' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA encoding("UTF-16be")')['effective'],
    'encoding assignment numeric utf8' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['encoding' => 'UTF-16be']]))->execute('PRAGMA encoding=1')['effective'],
    'encoding assignment numeric utf16le' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA encoding=2')['effective'],
    'encoding assignment numeric utf16be' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA encoding=3')['effective'],
    'encoding changed true on empty database' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute("PRAGMA encoding='UTF-16be'")['changed'],
    'encoding same assignment changed false' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA encoding=UTF8')['changed'],
    'encoding after schema created ignored' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['database_empty' => false]]))->execute("PRAGMA encoding='UTF-16be'")['effective'],
    'encoding after schema created reason' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['database_empty' => false]]))->execute("PRAGMA encoding='UTF-16be'")['reason'],
    'temp encoding assignment is connection wide no-op' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute("PRAGMA temp.encoding='UTF-16be'")['reason'],
    'main encoding propagates to temp schema' => static function (): mixed {
        $state = new SQLitePragmaEncodingPageTempStoreState();
        $state->execute("PRAGMA encoding='UTF-16be'");
        return $state->execute('PRAGMA temp.encoding')['effective'];
    },
    'attached encoding defaults to main current encoding' => static function (): mixed {
        $state = new SQLitePragmaEncodingPageTempStoreState(['main' => ['encoding' => 'UTF-16le']]);
        return $state->execute('PRAGMA aux.encoding')['effective'];
    },
    'encoding trailing semicolon accepted' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute(" PRAGMA encoding='UTF-16le';\n")['effective'],
    'encoding invalid keyword rejected' => static fn (): mixed => $rejects(static fn () => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA encoding=latin1')),
    'encoding invalid numeric rejected' => static fn (): mixed => $rejects(static fn () => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA encoding=4')),
    'default page size is 4096' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA page_size')['effective'],
    'page size rows use sqlite column name' => static fn (): mixed => array_keys((new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA page_size')['rows'][0]),
    'page size assignment 512' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA page_size=512')['effective'],
    'page size assignment 1024' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA page_size=1024')['effective'],
    'page size assignment 8192' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA page_size(8192)')['effective'],
    'page size assignment 65536' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA page_size=65536')['effective'],
    'page size changed true' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA page_size=8192')['changed'],
    'page size changed false on same value' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['page_size' => 8192]]))->execute('PRAGMA page_size=8192')['changed'],
    'page size after schema created ignored' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['page_size' => 4096, 'database_empty' => false]]))->execute('PRAGMA page_size=8192')['effective'],
    'page size after schema created reason' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['database_empty' => false]]))->execute('PRAGMA page_size=8192')['reason'],
    'temp page size assignment noops' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA temp.page_size=8192')['effective'],
    'temp page size assignment reason' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA temp.page_size=8192')['reason'],
    'attached page size assignment isolated' => static function (): mixed {
        $state = new SQLitePragmaEncodingPageTempStoreState();
        $state->execute('PRAGMA aux.page_size=8192');
        return [$state->execute('PRAGMA aux.page_size')['effective'], $state->execute('PRAGMA main.page_size')['effective']];
    },
    'page size invalid too small rejected' => static fn (): mixed => $rejects(static fn () => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA page_size=256')),
    'page size invalid not power two rejected' => static fn (): mixed => $rejects(static fn () => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA page_size=600')),
    'page size invalid too large rejected' => static fn (): mixed => $rejects(static fn () => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA page_size=131072')),
    'page size invalid keyword rejected' => static fn (): mixed => $rejects(static fn () => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA page_size=large')),
    'default page count is zero' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA page_count')['effective'],
    'page count seeded value reports' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['page_count' => 37]]))->execute('PRAGMA page_count')['effective'],
    'page count attached schema isolated' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['aux' => ['page_count' => 12]]))->execute('PRAGMA aux.page_count')['rows'][0]['page_count'],
    'page count temp schema reports seeded value' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['temp' => ['page_count' => 3]]))->execute('PRAGMA temp.page_count')['effective'],
    'page count assignment rejected' => static fn (): mixed => $rejects(static fn () => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA page_count=7')),
    'page count parenthesized assignment rejected' => static fn (): mixed => $rejects(static fn () => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA page_count(7)')),
    'page count rows use sqlite column name' => static fn (): mixed => array_keys((new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA page_count')['rows'][0]),
    'default temp store is default' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA temp_store')['effective'],
    'temp store default keyword maps zero' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['temp_store' => 2]]))->execute('PRAGMA temp_store=DEFAULT')['effective'],
    'temp store file keyword maps one' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA temp_store=FILE')['effective'],
    'temp store memory keyword maps two' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA temp_store=MEMORY')['effective'],
    'temp store numeric zero maps default' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['temp_store' => 1]]))->execute('PRAGMA temp_store=0')['effective'],
    'temp store numeric one maps file' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA temp_store=1')['effective'],
    'temp store numeric two maps memory' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA temp_store=2')['effective'],
    'temp store numeric three maps default' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['temp_store' => 2]]))->execute('PRAGMA temp_store=3')['effective'],
    'temp store parenthesized assignment' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA temp_store(MEMORY)')['effective'],
    'temp store changed true' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA temp_store=MEMORY')['changed'],
    'temp store changed false on same value' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['temp_store' => 2]]))->execute('PRAGMA temp_store=MEMORY')['changed'],
    'temp store schema qualified main accepted' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA main.temp_store=FILE')['effective'],
    'temp store temp schema isolated' => static function (): mixed {
        $state = new SQLitePragmaEncodingPageTempStoreState();
        $state->execute('PRAGMA temp.temp_store=MEMORY');
        return [$state->execute('PRAGMA temp.temp_store')['effective'], $state->execute('PRAGMA main.temp_store')['effective']];
    },
    'temp store attached schema isolated' => static function (): mixed {
        $state = new SQLitePragmaEncodingPageTempStoreState();
        $state->execute('PRAGMA aux.temp_store=FILE');
        return [$state->execute('PRAGMA aux.temp_store')['effective'], $state->execute('PRAGMA main.temp_store')['effective']];
    },
    'temp store invalid numeric rejected' => static fn (): mixed => $rejects(static fn () => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA temp_store=4')),
    'temp store invalid keyword rejected' => static fn (): mixed => $rejects(static fn () => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA temp_store=RAM')),
    'temp store rows use sqlite column name' => static fn (): mixed => array_keys((new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA temp_store')['rows'][0]),
    'parse page size schema assignment' => static fn (): mixed => SQLitePragmaEncodingPageTempStoreState::parse('PRAGMA aux.page_size=8192'),
    'parse encoding parenthesized quote' => static fn (): mixed => SQLitePragmaEncodingPageTempStoreState::parse("PRAGMA encoding('UTF-16be')"),
    'parse temp store query' => static fn (): mixed => SQLitePragmaEncodingPageTempStoreState::parse('PRAGMA temp_store'),
    'parse rejects quoted schema for bounded corpus' => static fn (): mixed => $rejects(static fn () => SQLitePragmaEncodingPageTempStoreState::parse('PRAGMA "main".page_size=4096')),
    'parse rejects unsupported pragma' => static fn (): mixed => $rejects(static fn () => SQLitePragmaEncodingPageTempStoreState::parse('PRAGMA journal_mode=WAL')),
    'state exposes normalized schema' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState(['main' => ['encoding' => 3, 'page_size' => '8192', 'page_count' => '5', 'temp_store' => 'memory', 'database_empty' => false]]))->schemas()['main'],
    'dependencies report encoding state' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute("PRAGMA encoding='UTF-16le'")['dependencies'],
    'dependencies report page size state' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA page_size=8192')['dependencies'],
    'dependencies report page count state' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA page_count')['dependencies'],
    'dependencies report temp store state' => static fn (): mixed => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA temp_store=MEMORY')['dependencies'],
];

$expected = [
    'default encoding query is utf8' => 'UTF-8',
    'encoding rows use sqlite column name' => ['encoding'],
    'encoding assignment utf16 keyword maps little endian' => 'UTF-16le',
    'encoding assignment utf16le keyword maps little endian' => 'UTF-16le',
    'encoding assignment utf16be keyword maps big endian' => 'UTF-16be',
    'encoding assignment numeric utf8' => 'UTF-8',
    'encoding assignment numeric utf16le' => 'UTF-16le',
    'encoding assignment numeric utf16be' => 'UTF-16be',
    'encoding changed true on empty database' => true,
    'encoding same assignment changed false' => false,
    'encoding after schema created ignored' => 'UTF-8',
    'encoding after schema created reason' => 'encoding_change_ignored_after_schema_created',
    'temp encoding assignment is connection wide no-op' => 'encoding_is_database_connection_wide',
    'main encoding propagates to temp schema' => 'UTF-16be',
    'attached encoding defaults to main current encoding' => 'UTF-16le',
    'encoding trailing semicolon accepted' => 'UTF-16le',
    'encoding invalid keyword rejected' => 'rejected',
    'encoding invalid numeric rejected' => 'rejected',
    'default page size is 4096' => 4096,
    'page size rows use sqlite column name' => ['page_size'],
    'page size assignment 512' => 512,
    'page size assignment 1024' => 1024,
    'page size assignment 8192' => 8192,
    'page size assignment 65536' => 65536,
    'page size changed true' => true,
    'page size changed false on same value' => false,
    'page size after schema created ignored' => 4096,
    'page size after schema created reason' => 'page_size_change_requires_vacuum',
    'temp page size assignment noops' => 4096,
    'temp page size assignment reason' => 'temporary_schema_uses_connection_page_size',
    'attached page size assignment isolated' => [8192, 4096],
    'page size invalid too small rejected' => 'rejected',
    'page size invalid not power two rejected' => 'rejected',
    'page size invalid too large rejected' => 'rejected',
    'page size invalid keyword rejected' => 'rejected',
    'default page count is zero' => 0,
    'page count seeded value reports' => 37,
    'page count attached schema isolated' => 12,
    'page count temp schema reports seeded value' => 3,
    'page count assignment rejected' => 'rejected',
    'page count parenthesized assignment rejected' => 'rejected',
    'page count rows use sqlite column name' => ['page_count'],
    'default temp store is default' => 0,
    'temp store default keyword maps zero' => 0,
    'temp store file keyword maps one' => 1,
    'temp store memory keyword maps two' => 2,
    'temp store numeric zero maps default' => 0,
    'temp store numeric one maps file' => 1,
    'temp store numeric two maps memory' => 2,
    'temp store numeric three maps default' => 0,
    'temp store parenthesized assignment' => 2,
    'temp store changed true' => true,
    'temp store changed false on same value' => false,
    'temp store schema qualified main accepted' => 1,
    'temp store temp schema isolated' => [2, 0],
    'temp store attached schema isolated' => [1, 0],
    'temp store invalid numeric rejected' => 'rejected',
    'temp store invalid keyword rejected' => 'rejected',
    'temp store rows use sqlite column name' => ['temp_store'],
    'parse page size schema assignment' => ['schema' => 'aux', 'pragma' => 'page_size', 'value' => '8192'],
    'parse encoding parenthesized quote' => ['schema' => 'main', 'pragma' => 'encoding', 'value' => "'UTF-16be'"],
    'parse temp store query' => ['schema' => 'main', 'pragma' => 'temp_store', 'value' => null],
    'parse rejects quoted schema for bounded corpus' => 'rejected',
    'parse rejects unsupported pragma' => 'rejected',
    'state exposes normalized schema' => ['encoding' => 'UTF-16be', 'page_size' => 8192, 'page_count' => 5, 'max_page_count' => 1073741823, 'application_id' => 0, 'temp_store' => 2, 'auto_vacuum' => 0, 'pending_auto_vacuum' => null, 'database_empty' => false, 'temporary' => false],
    'dependencies report encoding state' => ['sqlite-pragma-encoding-state'],
    'dependencies report page size state' => ['sqlite-pragma-page-size-state'],
    'dependencies report page count state' => ['sqlite-pragma-page-count-state'],
    'dependencies report temp store state' => ['sqlite-pragma-temp-store-state'],
];

foreach ($cases as $name => $callback) {
    $tests['pragma encoding page tempstore corpus ' . $name] = static function (TestRunner $t) use ($callback, $expected, $name): void {
        $t->same($expected[$name], $callback());
    };
}

return $tests;
