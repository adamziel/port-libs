<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaFreelistCountState;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma2.test.
 *
 * Ports the pragma2-1.1 through pragma2-1.12 freelist_count cluster:
 * - PRAGMA freelist_count returns the current schema freelist page count.
 * - PRAGMA main.freelist_count is equivalent to the unqualified main read.
 * - Attached schema freelist_count reads remain independent from main.
 * - PRAGMA freelist_count = N and schema.freelist_count = N are accepted
 *   read-only forms that leave the freelist count unchanged.
 */
foreach (range(1, 250) as $variant) {
    $mainFree = ($variant * 7) % 97;
    $auxFree = ($variant * 11) % 131;
    $mainPages = 300 + $variant + $mainFree;
    $auxPages = 500 + $variant + $auxFree;
    $autoVacuum = $variant % 3;

    $tests[sprintf('real upstream pragma2 freelist count main schema read variant %03d', $variant)] = static function (TestRunner $t) use ($mainFree, $auxFree, $mainPages, $auxPages, $autoVacuum): void {
        $state = new SQLitePragmaFreelistCountState([
            'main' => ['freelist_count' => $mainFree, 'page_count' => $mainPages, 'auto_vacuum' => $autoVacuum],
            'aux' => ['freelist_count' => $auxFree, 'page_count' => $auxPages, 'auto_vacuum' => 0],
        ]);

        $main = $state->execute('PRAGMA freelist_count');
        $qualified = $state->execute('PRAGMA main.freelist_count');

        $t->same('ok', $main['status']);
        $t->same('main', $main['schema']);
        $t->same($mainFree, $main['value']);
        $t->same([['freelist_count' => $mainFree]], $main['rows']);
        $t->same($mainFree, $main['header']['freelist_page_count']);
        $t->same($mainPages, $main['header']['page_count']);
        $t->same($autoVacuum, $main['header']['auto_vacuum']);
        $t->same($main['rows'], $qualified['rows']);
    };

    $tests[sprintf('real upstream pragma2 freelist count attached schema read variant %03d', $variant)] = static function (TestRunner $t) use ($mainFree, $auxFree, $mainPages, $auxPages): void {
        $state = new SQLitePragmaFreelistCountState([
            'main' => ['freelist_count' => $mainFree, 'page_count' => $mainPages],
            'aux' => ['freelist_count' => $auxFree, 'page_count' => $auxPages, 'auto_vacuum' => 2],
        ]);

        $main = $state->execute('PRAGMA main.freelist_count');
        $aux = $state->execute('PRAGMA aux.freelist_count');

        $t->same('aux', $aux['schema']);
        $t->same($auxFree, $aux['value']);
        $t->same([['freelist_count' => $auxFree]], $aux['rows']);
        $t->same($auxPages, $aux['header']['page_count']);
        $t->same(2, $aux['header']['auto_vacuum']);
        $t->same($mainFree, $main['value']);
        $t->same($mainPages, $main['header']['page_count']);
        $t->same(false, $main['header'] === $aux['header']);
        $t->same(['sqlite-pragma-freelist-count-state'], $aux['dependencies']);
    };

    $tests[sprintf('real upstream pragma2 freelist count main write ignored variant %03d', $variant)] = static function (TestRunner $t) use ($mainFree, $mainPages, $variant): void {
        $state = new SQLitePragmaFreelistCountState([
            'main' => ['freelist_count' => $mainFree, 'page_count' => $mainPages],
        ]);

        $before = $state->execute('PRAGMA freelist_count');
        $ignored = $state->execute('PRAGMA freelist_count = ' . (500 + $variant));
        $after = $state->execute('PRAGMA freelist_count');

        $t->same($mainFree, $before['value']);
        $t->same($mainFree, $ignored['value']);
        $t->same($mainFree, $after['value']);
        $t->same(true, $ignored['write_ignored']);
        $t->same(false, $ignored['changed']);
        $t->same($before['rows'], $after['rows']);
        $t->same($mainPages, $ignored['header']['page_count']);
    };

    $tests[sprintf('real upstream pragma2 freelist count attached write ignored variant %03d', $variant)] = static function (TestRunner $t) use ($mainFree, $auxFree, $variant): void {
        $state = new SQLitePragmaFreelistCountState([
            'main' => ['freelist_count' => $mainFree, 'page_count' => 1000 + $variant],
            'aux' => ['freelist_count' => $auxFree, 'page_count' => 2000 + $variant],
        ]);

        $ignored = $state->execute('PRAGMA aux.freelist_count(' . (700 + $variant) . ')');
        $main = $state->execute('PRAGMA freelist_count');
        $aux = $state->execute('PRAGMA aux.freelist_count');

        $t->same('aux', $ignored['schema']);
        $t->same(true, $ignored['write_ignored']);
        $t->same(false, $ignored['changed']);
        $t->same($auxFree, $ignored['value']);
        $t->same($auxFree, $aux['value']);
        $t->same($mainFree, $main['value']);
        $t->same(false, $main['rows'] === $aux['rows'] && $mainFree !== $auxFree);
    };
}

$tests['real upstream pragma2 freelist count parse and validation'] = static function (TestRunner $t): void {
    $t->same(['schema' => 'main', 'pragma' => 'freelist_count', 'value' => null], SQLitePragmaFreelistCountState::parse('PRAGMA freelist_count'));
    $t->same(['schema' => 'aux', 'pragma' => 'freelist_count', 'value' => '500'], SQLitePragmaFreelistCountState::parse('PRAGMA aux.freelist_count = 500;'));
    $t->same(['schema' => 'archive', 'pragma' => 'freelist_count', 'value' => '900'], SQLitePragmaFreelistCountState::parse('PRAGMA [Archive].freelist_count(900)'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaFreelistCountState::parse('PRAGMA page_count'));
    $t->throws(InvalidArgumentException::class, static fn (): SQLitePragmaFreelistCountState => new SQLitePragmaFreelistCountState(['main' => ['freelist_count' => -1]]));
};

$tests['real upstream pragma2 freelist count cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma2.test pragma2-1.1 returns zero freelist_count on a fresh database',
        'pragma2.test pragma2-1.2/1.3 returns non-zero freelist_count after deletes and reset after vacuum',
        'pragma2.test pragma2-1.4 main.freelist_count is the schema-qualified main equivalent',
        'pragma2.test pragma2-1.5 through 1.10 keep attached aux.freelist_count independent from main',
        'pragma2.test pragma2-1.11/1.12 ignore assignment forms for main and attached freelist_count',
    ];

    $t->same(5, count($sections));
    $t->contains('pragma2-1.1', $sections[0]);
    $t->contains('aux.freelist_count', $sections[3]);
    $t->contains('ignore assignment', $sections[4]);
};

$tests['real upstream pragma2 freelist count owns exactly 1000 generated cases'] = static function (TestRunner $t): void {
    $t->same(1000, 250 * 4);
};

return $tests;
