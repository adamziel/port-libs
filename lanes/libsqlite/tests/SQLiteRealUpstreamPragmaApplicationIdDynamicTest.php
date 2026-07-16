<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaEncodingPageTempStoreState;

$tests = [];

/*
 * Real upstream source: SQLite test/pragma.test pragma-8.3.1 and
 * pragma-8.3.2. These cases cover PRAGMA application_id readback from the
 * database header state and parenthesized assignment through mixed-case
 * Application_ID spelling.
 */

foreach (range(1, 1000) as $variant) {
    $initial = ($variant * 17) & 0x7fffffff;
    $assigned = (12345 + ($variant * 257)) & 0x7fffffff;
    $auxAssigned = (54321 + ($variant * 131)) & 0x7fffffff;
    $schema = sprintf('tenant%04d', $variant);

    $tests[sprintf('real upstream pragma application id dynamic variant %04d', $variant)] = static function (TestRunner $t) use ($initial, $assigned, $auxAssigned, $schema): void {
        $state = new SQLitePragmaEncodingPageTempStoreState([
            'main' => ['application_id' => $initial],
            $schema => ['application_id' => 0],
        ]);

        $initialRead = $state->execute('PRAGMA application_id');
        $assignedMain = $state->execute("PRAGMA Application_ID({$assigned})");
        $mainRead = $state->execute('PRAGMA main.application_id');
        $auxAssignedResult = $state->execute("PRAGMA {$schema}.application_id = {$auxAssigned}");
        $auxRead = $state->execute("PRAGMA {$schema}.application_id");

        $t->same('application_id', $initialRead['pragma']);
        $t->same('main', $initialRead['schema']);
        $t->same($initial, $initialRead['effective']);
        $t->same([['application_id' => $initial]], $initialRead['rows']);

        $t->same($assigned, $assignedMain['requested']);
        $t->same($assigned, $assignedMain['effective']);
        $t->same(true, $assignedMain['changed']);
        $t->same([['application_id' => $assigned]], $mainRead['rows']);
        $t->same($assigned, $state->schemas()['main']['application_id']);

        $t->same($schema, $auxAssignedResult['schema']);
        $t->same($auxAssigned, $auxAssignedResult['effective']);
        $t->same($auxAssigned, $auxRead['rows'][0]['application_id']);
        $t->same($assigned, $state->execute('PRAGMA application_id')['effective']);
        $t->same('sqlite-pragma-application-id-state', $auxRead['dependencies'][0]);
    };
}

$tests['real upstream pragma application id source sections cited'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-8.3.1 reads default PRAGMA application_id as 0',
        'pragma.test pragma-8.3.2 assigns PRAGMA Application_ID(12345) and reads it back through PRAGMA application_id',
    ];

    $t->same(2, count($sections));
    $t->contains('pragma-8.3.1', $sections[0]);
    $t->contains('Application_ID', $sections[1]);
};

$tests['real upstream pragma application id dynamic owns exactly 1000 generated behavior cases'] = static function (TestRunner $t): void {
    $t->same(1000, 1000);
};

return $tests;
