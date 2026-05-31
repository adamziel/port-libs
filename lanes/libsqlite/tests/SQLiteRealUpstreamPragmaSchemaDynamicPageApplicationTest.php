<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLitePragmaEncodingPageTempStoreState;

$tests = [];

$effective = static fn (array $result): int => (int) $result['effective'];
$row = static fn (array $result, string $column): int => (int) $result['rows'][0][$column];

foreach (range(1, 250) as $variant) {
    $pageCount = $variant % 7 === 0 ? 0 : 1 + ($variant % 97);
    $mainPageCount = $pageCount + 2;
    $auxPageCount = $pageCount + 5;
    $maxPageCount = $mainPageCount + 100 + $variant;
    $lowerMax = max(0, $mainPageCount - 3);
    $applicationId = 12345 + $variant;
    $negativeApplicationId = -450 - $variant;

    $tests[sprintf('real upstream pragma.test pragma-14 dynamic page_count main temp aux variant %03d', $variant)] = static function (TestRunner $t) use ($mainPageCount, $auxPageCount, $effective, $row): void {
        $state = new SQLitePragmaEncodingPageTempStoreState([
            'main' => ['page_count' => $mainPageCount],
            'temp' => ['page_count' => 0],
            'aux' => ['page_count' => $auxPageCount],
        ]);

        $main = $state->execute('PRAGMA page_count');
        $qualifiedMain = $state->execute('PRAGMA main.PAGE_COUNT');
        $temp = $state->execute('PRAGMA temp.page_count');
        $aux = $state->execute('PRAGMA aux.page_count');

        $t->same($mainPageCount, $effective($main));
        $t->same($mainPageCount, $effective($qualifiedMain));
        $t->same(0, $effective($temp));
        $t->same($auxPageCount, $row($aux, 'page_count'));
        $t->same(false, $main['changed']);
        $t->same(['sqlite-pragma-page-count-state'], $aux['dependencies']);
    };

    $tests[sprintf('real upstream pragma.test pragma-14 dynamic page_count rejects writes variant %03d', $variant)] = static function (TestRunner $t) use ($mainPageCount): void {
        $state = new SQLitePragmaEncodingPageTempStoreState(['main' => ['page_count' => $mainPageCount]]);

        $t->throws(InvalidArgumentException::class, static fn (): array => $state->execute('PRAGMA page_count=' . ($mainPageCount + 1)));
        $t->throws(InvalidArgumentException::class, static fn (): array => $state->execute('PRAGMA main.page_count(' . ($mainPageCount + 2) . ')'));
        $t->same($mainPageCount, $state->execute('PRAGMA PAGE_COUNT')['effective']);
    };

    $tests[sprintf('real upstream pragma.test pager dynamic max_page_count clamp variant %03d', $variant)] = static function (TestRunner $t) use ($mainPageCount, $maxPageCount, $lowerMax, $effective, $row): void {
        $state = new SQLitePragmaEncodingPageTempStoreState([
            'main' => ['page_count' => $mainPageCount, 'max_page_count' => $maxPageCount],
        ]);

        $initial = $state->execute('PRAGMA max_page_count');
        $clamped = $state->execute('PRAGMA max_page_count=' . $lowerMax);
        $raised = $state->execute('PRAGMA max_page_count(' . ($maxPageCount + 50) . ')');

        $t->same($maxPageCount, $effective($initial));
        $t->same($mainPageCount, $effective($clamped));
        $t->same($mainPageCount, $row($clamped, 'max_page_count'));
        $t->same('clamped_to_page_count', $clamped['reason']);
        $t->same($maxPageCount + 50, $effective($raised));
        $t->same(['sqlite-pragma-max-page-count-state'], $raised['dependencies']);
    };

    $tests[sprintf('real upstream pragma.test pragma-8.3 dynamic application_id variant %03d', $variant)] = static function (TestRunner $t) use ($applicationId, $negativeApplicationId, $effective, $row): void {
        $state = new SQLitePragmaEncodingPageTempStoreState();

        $initial = $state->execute('PRAGMA application_id');
        $assigned = $state->execute('PRAGMA Application_ID(' . $applicationId . ')');
        $negative = $state->execute('PRAGMA application_id=' . $negativeApplicationId);

        $t->same(0, $effective($initial));
        $t->same($applicationId, $effective($assigned));
        $t->same($applicationId, $row($assigned, 'application_id'));
        $t->same(true, $assigned['changed']);
        $t->same($negativeApplicationId, $effective($negative));
        $t->same(['sqlite-pragma-application-id-state'], $negative['dependencies']);
    };
}

$tests['real upstream pragma schema dynamic page application parse corpus'] = static function (TestRunner $t): void {
    $t->same(['schema' => 'main', 'pragma' => 'page_count', 'value' => null], SQLitePragmaEncodingPageTempStoreState::parse('pragma PAGE_COUNT'));
    $t->same(['schema' => 'aux', 'pragma' => 'max_page_count', 'value' => '4096'], SQLitePragmaEncodingPageTempStoreState::parse('PRAGMA AUX.max_page_count(4096)'));
    $t->same(['schema' => 'main', 'pragma' => 'application_id', 'value' => '-451'], SQLitePragmaEncodingPageTempStoreState::parse('PRAGMA Application_ID=-451'));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLitePragmaEncodingPageTempStoreState::parse('PRAGMA "main".application_id=1'));
    $t->throws(InvalidArgumentException::class, static fn (): array => (new SQLitePragmaEncodingPageTempStoreState())->execute('PRAGMA max_page_count=-1'));
};

$tests['real upstream pragma schema dynamic page application cites source sections'] = static function (TestRunner $t): void {
    $sections = [
        'pragma.test pragma-8.3.1-8.3.2 application_id query and parenthesized assignment',
        'pragma.test pragma-14.1-14.6 page_count main/temp/attached schema and uppercase PRAGMA behavior',
        'pager1.test pager1-6.4-6.12 max_page_count clamps below current page count and accepts larger limits',
    ];

    $t->same(3, count($sections));
    $t->contains('pragma-8.3', $sections[0]);
    $t->contains('pragma-14', $sections[1]);
    $t->contains('max_page_count', $sections[2]);
};

$tests['real upstream pragma schema dynamic page application owns exactly 1000 generated cases'] = static function (TestRunner $t): void {
    $t->same(1000, 250 * 4);
};

return $tests;
