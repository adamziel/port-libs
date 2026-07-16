<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$methodNames = [
    'keyValueRowKeyFallbackPlan',
    'v167_scan',
    'v167_assertRow',
    'keyValueRowKeyCaseSensitiveLikePlan',
    'v168_scan',
    'v168_assertRow',
    'keyValueRowKeyYieldReplayPlan',
    'v169_sortedKeys',
    'keyValueRowKeyDuplicateKeyReplayPlan',
    'v171_scan',
    'v171_assertRow',
    'keyValueRowKeyYieldTokenPlan',
    'keyValueRowKeySourcePlan',
    'v173_scan',
    'v173_assertRow',
];

$legacySourceMatches = static function () use ($methodNames): array {
    $reflection = new ReflectionClass(SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::class);
    $file = $reflection->getFileName();
    if ($file === false) {
        throw new RuntimeException('Unable to locate UTF-16 NOCASE LIKE RTRIM source file');
    }

    $lines = file($file);
    if ($lines === false) {
        throw new RuntimeException("Unable to read {$file}");
    }

    $legacyTerms = [
        'wp' . '_options',
        'wp' . '_',
        'opt' . 'ion_id',
        'opt' . 'ion_name',
        'opt' . 'ion_value',
        'auto' . 'load',
        'blog' . '_id',
    ];
    $pattern = '/(?:' . implode('|', array_map(static fn (string $term): string => preg_quote($term, '/'), $legacyTerms)) . ')/';

    $matches = [];
    foreach ($methodNames as $methodName) {
        $method = $reflection->getMethod($methodName);
        $block = implode('', array_slice(
            $lines,
            $method->getStartLine() - 1,
            $method->getEndLine() - $method->getStartLine() + 1
        ));

        if (preg_match_all($pattern, $block, $methodMatches) < 1) {
            continue;
        }

        foreach ($methodMatches[0] as $match) {
            $matches[] = "{$methodName}: {$match}";
        }
    }

    return $matches;
};

return [
    'utf16 nocase like rtrim next167 through next173 source uses generic setting keys' => static fn (TestRunner $t) => $t->same([], $legacySourceMatches()),
];
