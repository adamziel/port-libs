<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$legacySourceMatches = static function (): array {
    $reflection = new ReflectionClass(SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::class);
    $file = $reflection->getFileName();
    if ($file === false) {
        throw new RuntimeException('Unable to locate UTF-16 NOCASE LIKE RTRIM source file');
    }

    $lines = file($file);
    if ($lines === false) {
        throw new RuntimeException("Unable to read {$file}");
    }

    $blocks = [
        [
            $reflection->getMethod('keyValueRowKeyEscapeRebindPlan')->getStartLine(),
            $reflection->getMethod('keyValueRowKeyAsciiSpaceRtrimPlan')->getStartLine() - 1,
        ],
        [
            $reflection->getMethod('keyValueRowKeyNullPatternRebindPlan')->getStartLine(),
            $reflection->getMethod('keyValueRowKeyEmbeddedNulTokenPlan')->getStartLine() - 1,
        ],
    ];

    $source = '';
    foreach ($blocks as [$start, $end]) {
        $source .= implode('', array_slice($lines, $start - 1, $end - $start + 1));
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

    if (preg_match_all($pattern, $source, $matches) < 1) {
        return [];
    }

    return $matches[0];
};

return [
    'utf16 nocase like rtrim next200 through next208 source uses generic setting keys' => static fn (TestRunner $t) => $t->same([], $legacySourceMatches()),
];
