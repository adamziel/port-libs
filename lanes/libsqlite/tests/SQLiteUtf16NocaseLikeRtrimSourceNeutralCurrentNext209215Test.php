<?php

declare(strict_types=1);

use PortLibs\LibSqlite\SQLiteEncodingCollationSourceCursor;
use PortLibs\LibSqlite\SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan;

$methodNames = [
    'keyValueRowKeyAsciiSpaceRtrimPlan',
    'v209_decodeRows',
    'v209_suffixDiagnostics',
    'v209_unicodeCaseDiagnostics',
    'v209_asciiLower',
    'v209_unicodeCasePrefixApprox',
    'keyValueRowKeyEmbeddedNulPlan',
    'v210_decodeRows',
    'v210_nulDiagnostics',
    'v210_hexMap',
    'v210_retained',
    'v210_exited',
    'v210_entered',
    'keyValueRowKeySourceRefreshPlan',
    'v211_scan',
    'v211_assertRow',
    'v211_inRange',
    'v211_sortRows',
    'v211_rowids',
    'v211_sortedDiff',
    'v211_sortedIntersect',
    'v211_mapByRowid',
    'v211_selectMap',
    'v211_byteOrderOnlyRowids',
    'v211_changedRowids',
    'v211_byRowid',
    'v211_encodingName',
    'v211_asciiLower',
    'keyValueRowKeyUnicodeEscapePlan',
    'v212_decodePreparedText',
    'v212_replaceEscapeCharacter',
    'v212_characters',
    'v212_sqliteTextLength',
    'v212_isAscii',
    'v212_encodingId',
    'v212_encodingName',
    'keyValueRowKeySelfEscapedEscapePlan',
    'likeEscapeTokens',
    'likeEscapeTokenKind',
    'likeEscapeEscapedEscapeOffsets',
    'likeEscapeEscapedWildcardOffsets',
    'likeEscapeFirstWildcardOffset',
    'likeEscapeTokenOffsets',
    'decodePreparedEscapeText',
    'likeEscapeCharacters',
    'sqliteLikeTextLength',
    'preparedEscapeEncodingId',
    'preparedEscapeEncodingName',
    'keyValueRowKeyEmbeddedNulTokenPlan',
    'v215_scan',
    'v215_assertRow',
    'v215_normalizeToken',
    'v215_beforeOrAtToken',
    'v215_afterToken',
    'v215_compareToken',
    'v215_truncatedKeyCollisions',
    'v215_inRange',
    'v215_sortRows',
    'v215_rowids',
    'v215_map',
    'v215_truncateAtNul',
    'v215_asciiLower',
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
        'opt' . 'ionRowName',
        'opt' . 'ionName',
        'opt' . 'ionValue',
        'opt' . 'ionId',
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

$row = static fn (int $id, string $key, int|string $encoding): array => [
    'setting_id' => $id,
    'key_name_bytes' => SQLiteEncodingCollationSourceCursor::encodeText($key, $encoding),
    'text_encoding' => match ($encoding) {
        'UTF-8', 1 => 1,
        'UTF-16LE', 2 => 2,
        'UTF-16BE', 3 => 3,
    },
];

return [
    'utf16 nocase like rtrim next209 through next215 source uses setting keys' => static fn (TestRunner $t) => $t->same([], $legacySourceMatches()),
    'utf16 nocase like rtrim next209 generic row shape executes' => static function (TestRunner $t) use ($row): void {
        $plan = SQLiteUtf16NocaseLikeRtrimCurrentSourceNextPlan::keyValueRowKeyAsciiSpaceRtrimPlan(
            [
                $row(1, 'Plugin_Cache  ', 'UTF-16LE'),
                $row(2, 'module_cache', 'UTF-16BE'),
            ],
            [
                $row(1, 'Plugin_Cache', 'UTF-16BE'),
                $row(3, 'plugin_queue', 'UTF-16LE'),
            ],
            'plugin%',
        );

        $t->same('rtrim(key_name) COLLATE NOCASE LIKE ? ESCAPE ? /* ASCII-space RTRIM only */', $plan['expression']);
        $t->same([1], $plan['currentMatchedRowids']);
        $t->same([1, 3], $plan['nextMatchedRowids']);
    },
];
