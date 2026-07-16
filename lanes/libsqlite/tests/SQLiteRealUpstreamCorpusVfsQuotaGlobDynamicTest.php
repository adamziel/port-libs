<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteVfsIoDynamicPlan;

$tests = [];

$upstreamCases = [
    [1, 'abcdefg', 'abcdefg', true],
    [2, 'abcdefG', 'abcdefg', false],
    [3, 'abcdef', 'abcdefg', false],
    [4, 'abcdefgh', 'abcdefg', false],
    [5, 'abcdef?', 'abcdefg', true],
    [6, 'abcdef?', 'abcdef', false],
    [7, 'abcdef?', 'abcdefgh', false],
    [8, 'abcdefg', 'abcdef?', false],
    [9, 'abcdef?', 'abcdef?', true],
    [10, 'abc/def', 'abc/def', true],
    [11, 'abc//def', 'abc/def', false],
    [12, '*/abc/*', 'x/abc/y', true],
    [13, '*/abc/*', '/abc/', true],
    [16, '*/abc/*', 'x///a/ab/abc', false],
    [17, '*/abc/*', 'x//a/ab/abc/', true],
    [16, '*/abc/*', 'x///a/ab/abc', false],
    [17, '*/abc/*', 'x//a/ab/abc/', true],
    [18, '**/abc/**', 'x//a/ab/abc/', true],
    [19, '*?/abc/*?', 'x//a/ab/abc/y', true],
    [20, '?*/abc/?*', 'x//a/ab/abc/y', true],
    [21, 'abc[cde]efg', 'abcbefg', false],
    [22, 'abc[cde]efg', 'abccefg', true],
    [23, 'abc[cde]efg', 'abcdefg', true],
    [24, 'abc[cde]efg', 'abceefg', true],
    [25, 'abc[cde]efg', 'abcfefg', false],
    [26, 'abc[^cde]efg', 'abcbefg', true],
    [27, 'abc[^cde]efg', 'abccefg', false],
    [28, 'abc[^cde]efg', 'abcdefg', false],
    [29, 'abc[^cde]efg', 'abceefg', false],
    [30, 'abc[^cde]efg', 'abcfefg', true],
    [31, 'abc[c-e]efg', 'abcbefg', false],
    [32, 'abc[c-e]efg', 'abccefg', true],
    [33, 'abc[c-e]efg', 'abcdefg', true],
    [34, 'abc[c-e]efg', 'abceefg', true],
    [35, 'abc[c-e]efg', 'abcfefg', false],
    [36, 'abc[^c-e]efg', 'abcbefg', true],
    [37, 'abc[^c-e]efg', 'abccefg', false],
    [38, 'abc[^c-e]efg', 'abcdefg', false],
    [39, 'abc[^c-e]efg', 'abceefg', false],
    [40, 'abc[^c-e]efg', 'abcfefg', true],
    [41, 'abc[c-e]efg', 'abc-efg', false],
    [42, 'abc[-ce]efg', 'abc-efg', true],
    [43, 'abc[ce-]efg', 'abc-efg', true],
    [44, 'abc[][*?]efg', 'abc]efg', true],
    [45, 'abc[][*?]efg', 'abc*efg', true],
    [46, 'abc[][*?]efg', 'abc?efg', true],
    [47, 'abc[][*?]efg', 'abc[efg', true],
    [48, 'abc[^][*?]efg', 'abc]efg', false],
    [49, 'abc[^][*?]efg', 'abc*efg', false],
    [50, 'abc[^][*?]efg', 'abc?efg', false],
    [51, 'abc[^][*?]efg', 'abc[efg', false],
    [52, 'abc[^][*?]efg', 'abcdefg', true],
    [53, '*[xyz]efg', 'abcxefg', true],
    [54, '*[xyz]efg', 'abcwefg', false],
];

$ordinal = 0;
foreach ($upstreamCases as [$testNumber, $pattern, $text, $expected]) {
    foreach (['direct' => $text, 'backslash' => str_replace('/', '\\', $text)] as $variant => $variantText) {
        $ordinal++;
        $name = sprintf('real upstream corpus vfs quota glob dynamic quota-glob-%02d.%s ordinal %03d', $testNumber, $variant, $ordinal);
        $tests[$name] = static function (TestRunner $t) use ($testNumber, $pattern, $variantText, $expected, $variant): void {
            $profile = SQLiteVfsIoDynamicPlan::quotaGlobProfile('quota-glob-' . $testNumber . '.' . $variant, $pattern, $variantText, $expected);

            $t->same('ok', $profile['status']);
            $t->same('quota-glob.test', $profile['script']);
            $t->same($pattern, $profile['pattern']);
            $t->same(str_replace('\\', '/', $variantText), $profile['normalized_text']);
            $t->same($expected, $profile['expected']);
            $t->same($expected, $profile['matched']);
            $t->same($variant === 'backslash' && str_contains($variantText, '\\'), $profile['path_separator_variant']);
            $t->same(true, in_array('upstream-quota-glob-test', $profile['dependencies'], true));
            $t->same(true, in_array('sqlite-quota-vfs-path-glob', $profile['dependencies'], true));
            $t->same(['quota-glob.test quota-glob-1 through quota-glob-54'], $profile['upstream']);
        };
    }
}

$tests['real upstream corpus vfs quota glob dynamic validates upstream case count'] = static function (TestRunner $t) use ($ordinal): void {
    $t->same(108, $ordinal);
};

$tests['real upstream corpus vfs quota glob dynamic rejects malformed inputs'] = static function (TestRunner $t): void {
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::quotaGlobProfile('', '*', 'abc', true));
    $t->throws(InvalidArgumentException::class, static fn (): array => SQLiteVfsIoDynamicPlan::quotaGlobProfile('quota-glob-empty-pattern', '', 'abc', true));
};

return $tests;
