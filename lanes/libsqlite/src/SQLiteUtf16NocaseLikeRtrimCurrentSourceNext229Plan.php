<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUtf16NocaseLikeRtrimCurrentSourceNext229Plan
{
    private const NON_ASCII_SPACE_NAMES = [
        "\u{00a0}" => 'NO-BREAK SPACE',
        "\u{1680}" => 'OGHAM SPACE MARK',
        "\u{2000}" => 'EN QUAD',
        "\u{2001}" => 'EM QUAD',
        "\u{2002}" => 'EN SPACE',
        "\u{2003}" => 'EM SPACE',
        "\u{2004}" => 'THREE-PER-EM SPACE',
        "\u{2005}" => 'FOUR-PER-EM SPACE',
        "\u{2006}" => 'SIX-PER-EM SPACE',
        "\u{2007}" => 'FIGURE SPACE',
        "\u{2008}" => 'PUNCTUATION SPACE',
        "\u{2009}" => 'THIN SPACE',
        "\u{200a}" => 'HAIR SPACE',
        "\u{202f}" => 'NARROW NO-BREAK SPACE',
        "\u{205f}" => 'MEDIUM MATHEMATICAL SPACE',
        "\u{3000}" => 'IDEOGRAPHIC SPACE',
    ];

    /**
     * @param list<array<string,mixed>> $currentRows
     * @param list<array<string,mixed>> $nextRows
     * @return array<string,mixed>
     */
    public static function wordpressOptionNameUnicodeSpaceRtrimPlan(
        array $currentRows,
        array $nextRows,
        string $pattern = 'plugin!_cache%',
        string $escapeBytes = "!\0",
        int|string $escapeEncoding = 'UTF-16LE',
        int $pageSize = 4,
        int $lastRowid = 0,
        ?string $lastKey = null,
        string $currentSource = 'main.wp_options@228',
        string $nextSource = 'main.wp_options@229',
        int $currentSchemaCookie = 228,
        int $nextSchemaCookie = 229,
    ): array {
        $base = SQLiteUtf16NocaseLikeRtrimCurrentSourceNext224Plan::wordpressOptionNameKeysetResumePlan(
            $currentRows,
            $nextRows,
            $pattern,
            $escapeBytes,
            $escapeEncoding,
            $escapeBytes,
            $escapeEncoding,
            $pageSize,
            $lastRowid,
            $lastKey,
            $currentSource,
            $nextSource,
            $currentSchemaCookie,
            $nextSchemaCookie,
        );

        $currentUnicode = self::unicodeSpaceRows($base['currentRtrimTexts']);
        $nextUnicode = self::unicodeSpaceRows($base['nextRtrimTexts']);
        $currentTexts = self::decodedTexts($currentRows);
        $nextTexts = self::decodedTexts($nextRows);
        $currentAscii = self::asciiSpaceTrimmedRows($currentTexts, $base['currentRtrimTexts']);
        $nextAscii = self::asciiSpaceTrimmedRows($nextTexts, $base['nextRtrimTexts']);
        $currentVisual = self::visualKeys($base['currentRtrimTexts']);
        $nextVisual = self::visualKeys($base['nextRtrimTexts']);
        $currentUnicodeMatched = array_values(array_intersect($base['currentMatchedRowids'], array_keys($currentUnicode)));
        $nextUnicodeMatched = array_values(array_intersect($base['nextMatchedRowids'], array_keys($nextUnicode)));
        $currentVisualPeers = self::visualPeerRowsets($currentVisual);
        $nextVisualPeers = self::visualPeerRowsets($nextVisual);

        $reasons = $base['invalidationReasons'];
        if ($currentUnicodeMatched !== $nextUnicodeMatched) {
            $reasons[] = 'unicode-space-rowset';
        }
        if ($currentAscii !== $nextAscii) {
            $reasons[] = 'ascii-space-rtrim-rowset';
        }
        if (self::rowsetMap($currentVisualPeers) !== self::rowsetMap($nextVisualPeers)) {
            $reasons[] = 'visual-space-peer-rowset';
        }
        $reasons = array_values(array_unique($reasons));

        return array_replace($base, [
            'status' => 'utf16-nocase-like-rtrim-current-source-next229',
            'baseStatus' => $base['status'],
            'expression' => 'rtrim(option_name) COLLATE NOCASE LIKE ? ESCAPE ? /* UTF-16 non-ASCII spaces are not RTRIM spaces */',
            'currentSource' => $currentSource,
            'nextSource' => $nextSource,
            'currentSchemaCookie' => $currentSchemaCookie,
            'nextSchemaCookie' => $nextSchemaCookie,
            'currentUnicodeSpaceRowids' => array_keys($currentUnicode),
            'nextUnicodeSpaceRowids' => array_keys($nextUnicode),
            'currentDecodedTexts' => $currentTexts,
            'nextDecodedTexts' => $nextTexts,
            'currentUnicodeSpaceNames' => $currentUnicode,
            'nextUnicodeSpaceNames' => $nextUnicode,
            'currentUnicodeSpaceMatchedRowids' => $currentUnicodeMatched,
            'nextUnicodeSpaceMatchedRowids' => $nextUnicodeMatched,
            'currentAsciiSpaceTrimmedRowids' => $currentAscii,
            'nextAsciiSpaceTrimmedRowids' => $nextAscii,
            'currentVisualSpaceKeys' => $currentVisual,
            'nextVisualSpaceKeys' => $nextVisual,
            'currentVisualSpacePeerRowids' => $currentVisualPeers,
            'nextVisualSpacePeerRowids' => $nextVisualPeers,
            'unicodeSpacesRetainedByRtrim' => true,
            'asciiSpaceOnlyRtrim' => true,
            'likeResidualRunsAfterUnicodeSpaceRetention' => true,
            'nocaseFoldsUnicodeSpacesNever' => true,
            'cursorInvalidated' => $reasons !== [],
            'cursorReusable' => $reasons === [],
            'staleKeysetResumeRisk' => $reasons !== [],
            'invalidationReasons' => $reasons,
            'baseInvalidationReasons' => $base['invalidationReasons'],
            'dependencies' => [
                'sqlite-utf16-decode',
                'sqlite-like-escape-prefix-range',
                'sqlite-rtrim-ascii-space-only',
                'sqlite-nocase-keyset-resume',
                'sqlite-current-source-next229',
            ],
            'dependency_closure' => 'no new support component needed; reuses native UTF-16 decode, LIKE ESCAPE prefix planning, ASCII-only RTRIM keys, NOCASE keyset resume, and current-source invalidation diagnostics',
            'non_overlap' => 'next229 covers UTF-16 non-ASCII whitespace at the RTRIM/NOCASE LIKE current-source boundary; it avoids accepted next224 keyset rowsets, next212/213 Unicode ESCAPE handling, next190 ASCII-space trim boundaries, Unicode GLOB ranges, malformed UTF-16 guards, and VFS/WAL/B-tree/JSON/SQL clusters',
        ]);
    }

    /** @param array<int,string> $texts @return array<int,list<string>> */
    private static function unicodeSpaceRows(array $texts): array
    {
        $rows = [];
        foreach ($texts as $rowid => $text) {
            $names = [];
            foreach (self::characters($text) as $character) {
                if (isset(self::NON_ASCII_SPACE_NAMES[$character])) {
                    $names[] = self::NON_ASCII_SPACE_NAMES[$character];
                }
            }
            if ($names !== []) {
                $rows[(int) $rowid] = array_values(array_unique($names));
            }
        }

        ksort($rows);

        return $rows;
    }

    /** @param array<int,string> $matchedTexts @param array<int,string> $rtrimTexts @return list<int> */
    private static function asciiSpaceTrimmedRows(array $matchedTexts, array $rtrimTexts): array
    {
        $rowids = [];
        foreach ($matchedTexts as $rowid => $text) {
            if (($rtrimTexts[$rowid] ?? $text) !== $text) {
                $rowids[] = (int) $rowid;
            }
        }
        sort($rowids);

        return $rowids;
    }

    /** @param array<int,string> $texts @return array<int,string> */
    private static function visualKeys(array $texts): array
    {
        $keys = [];
        foreach ($texts as $rowid => $text) {
            $key = str_replace(array_keys(self::NON_ASCII_SPACE_NAMES), ' ', $text);
            $keys[(int) $rowid] = self::asciiLower(rtrim($key, ' '));
        }
        ksort($keys);

        return $keys;
    }

    /** @param array<int,string> $visualKeys @return array<string,list<int>> */
    private static function visualPeerRowsets(array $visualKeys): array
    {
        $peers = [];
        foreach ($visualKeys as $rowid => $key) {
            $peers[$key] ??= [];
            $peers[$key][] = (int) $rowid;
        }
        ksort($peers);

        return array_filter($peers, static fn (array $rowids): bool => count($rowids) > 1);
    }

    /** @param array<string,list<int>> $rowsets @return array<string,list<int>> */
    private static function rowsetMap(array $rowsets): array
    {
        foreach ($rowsets as &$rowids) {
            sort($rowids);
        }
        unset($rowids);
        ksort($rowsets);

        return $rowsets;
    }

    /** @param list<array<string,mixed>> $rows @return array<int,string> */
    private static function decodedTexts(array $rows): array
    {
        $texts = [];
        foreach ($rows as $row) {
            if (!isset($row['option_id'], $row['option_name_bytes'], $row['text_encoding'])) {
                continue;
            }
            try {
                $texts[(int) $row['option_id']] = SQLiteEncodingCollationSourceCursor::decodeText(
                    (string) $row['option_name_bytes'],
                    (int) $row['text_encoding'],
                );
            } catch (\InvalidArgumentException) {
                continue;
            }
        }
        ksort($texts);

        return $texts;
    }

    private static function asciiLower(string $value): string
    {
        return strtr($value, 'ABCDEFGHIJKLMNOPQRSTUVWXYZ', 'abcdefghijklmnopqrstuvwxyz');
    }

    /** @return list<string> */
    private static function characters(string $value): array
    {
        if ($value === '') {
            return [];
        }
        $characters = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);

        return is_array($characters) ? array_values($characters) : str_split($value);
    }
}
